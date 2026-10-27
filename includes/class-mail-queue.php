<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Envoi des messages — même principe que Seliweb_Mail_Queue côté Seliweb :
 * un cron à la minute qui dépile par lots, cadence réglable (onglet Mails).
 *
 * Particularité ici : pas de table de file séparée — la file, c'est
 * seliwebmedia_message_destinataires (statut_envoi='attente'), construite
 * seulement au moment où un message doit réellement partir (voir
 * expedier_message()), jamais avant. Le même passage à la minute :
 * 1) repère les messages programmés dont l'heure est arrivée et les fait
 *    démarrer (construit leurs destinataires) ;
 * 2) dépile un lot de destinataires "en attente", tous messages confondus ;
 * 3) referme les messages dont tous les destinataires ont été traités.
 */
class Seliweb_Media_Mail_Queue {

    const CRON_HOOK  = 'seliwebmedia_cron_mail_queue';
    const CRON_ARCHIVAGE = 'seliwebmedia_cron_archivage';

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'ajouter_intervalle' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'traiter' ) );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'seliwebmedia_minute', self::CRON_HOOK );
        }

        add_action( self::CRON_ARCHIVAGE, array( __CLASS__, 'archiver_et_purger' ) );
        if ( ! wp_next_scheduled( self::CRON_ARCHIVAGE ) ) {
            wp_schedule_event( strtotime( 'tomorrow 01:00' ), 'daily', self::CRON_ARCHIVAGE );
        }
    }

    public static function ajouter_intervalle( $schedules ) {
        $schedules['seliwebmedia_minute'] = array( 'interval' => 60, 'display' => __( 'Toutes les minutes (file de mails SeliwebMedia)', 'seliwebmedia' ) );
        return $schedules;
    }

    // Démarre l'envoi d'un message : marque "en_cours", construit ses
    // destinataires (jamais avant cet instant). Appelée immédiatement à la
    // création d'un message sans date d'envoi, ou par traiter() quand la
    // date programmée est atteinte.
    public static function expedier_message( $message_id ) {
        global $wpdb;
        $tm = $wpdb->prefix . 'seliwebmedia_messages';

        $wpdb->update( $tm, array(
            'statut'         => 'en_cours',
            'date_envoi_reel' => current_time( 'mysql' ),
        ), array( 'id' => $message_id ) );

        Seliweb_Media_Messages::construire_destinataires( $message_id );
    }

    public static function traiter() {
        global $wpdb;
        $tm = $wpdb->prefix . 'seliwebmedia_messages';
        $td = $wpdb->prefix . 'seliwebmedia_message_destinataires';

        // 1) Messages programmés dont l'heure est arrivée.
        $a_demarrer = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM $tm WHERE statut='programme' AND date_envoi_prevue IS NOT NULL AND date_envoi_prevue <= %s",
            current_time( 'mysql' )
        ) );
        foreach ( $a_demarrer as $message_id ) {
            self::expedier_message( (int) $message_id );
        }

        // 2) Dépile un lot de destinataires en attente, tous messages confondus.
        $lot = Seliweb_Media_Parametres::cadence_minute();
        $lignes = $wpdb->get_results( $wpdb->prepare(
            "SELECT d.*, m.sujet, m.corps, m.format
             FROM $td d JOIN $tm m ON m.id = d.message_id
             WHERE d.statut_envoi='attente'
             ORDER BY d.id ASC LIMIT %d",
            $lot
        ) );

        $messages_touches = array();
        foreach ( $lignes as $d ) {
            $ok = self::envoyer_destinataire( $d );
            $wpdb->update( $td, array(
                'statut_envoi' => $ok ? 'envoye' : 'echec',
                'date_envoi'   => current_time( 'mysql' ),
            ), array( 'id' => $d->id ) );
            $messages_touches[ (int) $d->message_id ] = true;
        }

        // 3) Referme les messages entièrement traités (plus aucune ligne en attente).
        foreach ( array_keys( $messages_touches ) as $message_id ) {
            $reste = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $td WHERE message_id=%d AND statut_envoi='attente'", $message_id ) );
            if ( $reste === 0 ) {
                $wpdb->update( $tm, array( 'statut' => 'envoye' ), array( 'id' => $message_id, 'statut' => 'en_cours' ) );
            }
        }
    }

    private static function envoyer_destinataire( $d ) {
        $sujet = str_replace( array( '{prenom}', '{nom}' ), array( $d->prenom, $d->nom ), $d->sujet );
        $corps = str_replace( array( '{prenom}', '{nom}' ), array( $d->prenom, $d->nom ), $d->corps );

        $lien_desabonnement = $d->token_desabonnement
            ? home_url( '/?swm_desabonner=' . rawurlencode( $d->token_desabonnement ) )
            : '';

        $headers = array();
        $from_email = Seliweb_Media_Parametres::from_email();
        $from_name  = Seliweb_Media_Parametres::from_name();
        if ( $from_email && is_email( $from_email ) ) {
            $headers[] = 'From: ' . ( $from_name ? $from_name . ' <' . $from_email . '>' : $from_email );
        }

        if ( $d->format === 'html' ) {
            $corps_html = Seliweb_Media_Messages::normaliser_corps_html( $corps );
            $lien_desabonnement_tpl = $lien_desabonnement;
            ob_start();
            include SELIWEBMEDIA_DIR . 'templates/mail-html.php';
            $html = ob_get_clean();

            add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
            $ok = wp_mail( $d->email, $sujet, $html, $headers );
            remove_all_filters( 'wp_mail_content_type' );
        } else {
            if ( $lien_desabonnement ) {
                $corps .= "\n\n" . __( 'Se désabonner :', 'seliwebmedia' ) . ' ' . $lien_desabonnement;
            }
            $ok = wp_mail( $d->email, $sujet, $corps, $headers );
        }

        return $ok;
    }

    // ----------------------------------------------------------------
    // Archivage (1 mois, fixe) et purge (délai réglable, onglet Mails) —
    // cron quotidien séparé, pas besoin d'une cadence à la minute pour ça.
    // ----------------------------------------------------------------
    public static function archiver_et_purger() {
        global $wpdb;
        $tm  = $wpdb->prefix . 'seliwebmedia_messages';
        $tmc = $wpdb->prefix . 'seliwebmedia_message_cibles';
        $td  = $wpdb->prefix . 'seliwebmedia_message_destinataires';

        // Archivage : 1 mois après l'envoi réel, fixe (pas de réglage).
        $wpdb->query( $wpdb->prepare(
            "UPDATE $tm SET archive=1, date_archivage=%s
             WHERE archive=0 AND statut='envoye' AND date_envoi_reel IS NOT NULL
               AND date_envoi_reel <= DATE_SUB(%s, INTERVAL 1 MONTH)",
            current_time( 'mysql' ), current_time( 'mysql' )
        ) );

        // Purge : délai réglable après archivage.
        $delai = Seliweb_Media_Parametres::delai_purge_mois();
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM $tm WHERE archive=1 AND date_archivage <= DATE_SUB(%s, INTERVAL %d MONTH)",
            current_time( 'mysql' ), $delai
        ) );
        foreach ( $ids as $id ) {
            $wpdb->delete( $td,  array( 'message_id' => $id ) );
            $wpdb->delete( $tmc, array( 'message_id' => $id ) );
            $wpdb->delete( $tm,  array( 'id' => $id ) );
        }
    }
}
