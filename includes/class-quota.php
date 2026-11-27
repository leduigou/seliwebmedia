<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Version gratuite et déblocage.
 *
 * Sans clé de déblocage valide (Seliweb_Media_Licence), SeliwebMedia
 * fonctionne en version gratuite : un nombre limité de mails par mois
 * calendaire, tous messages confondus (mails, newsletters), et une mention
 * « Envoyé avec SeliwebMedia — connex-asso.net » en bas des messages.
 *
 * Le décompte se lit dans les destinataires des messages dont l'envoi a
 * commencé ce mois-ci : rien n'est stocké à part. Un message n'est jamais
 * coupé : il part en entier s'il tient dans ce qui reste, sinon il attend.
 */
class Seliweb_Media_Quota {

    const URL_MENTION = 'https://www.connex-asso.net/seliwebmedia/';

    private static $cache = null;

    private static function facteur() {
        $r = 0;
        foreach ( str_split( 'SWM' ) as $c ) {
            $r += ord( $c );
        }
        return $r - 47;
    }

    public static function plafond() {
        return (int) self::facteur();
    }

    public static function est_debloque() {
        $etat = Seliweb_Media_Licence::etat();
        return ! empty( $etat['valide'] );
    }

    // Premier jour du mois en cours (heure du site), format MySQL.
    private static function debut_mois() {
        return wp_date( 'Y-m-01 00:00:00' );
    }

    public static function prochain_mois() {
        return wp_date( 'd/m/Y', strtotime( wp_date( 'Y-m-01' ) . ' +1 month' ) );
    }

    // Mails comptés ce mois-ci : destinataires des messages partis depuis le 1er.
    public static function utilises() {
        if ( null !== self::$cache ) return self::$cache;
        global $wpdb;
        $tm = $wpdb->prefix . 'seliwebmedia_messages';
        $td = $wpdb->prefix . 'seliwebmedia_message_destinataires';
        self::$cache = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $td d JOIN $tm m ON m.id = d.message_id
             WHERE m.date_envoi_reel IS NOT NULL AND m.date_envoi_reel >= %s",
            self::debut_mois()
        ) );
        return self::$cache;
    }

    public static function oublier() {
        self::$cache = null;
    }

    public static function restant() {
        return max( 0, self::plafond() - self::utilises() );
    }

    // Un message de $nb destinataires peut-il partir maintenant ?
    public static function peut_envoyer( $nb ) {
        return self::est_debloque() || self::utilises() + (int) $nb <= self::plafond();
    }

    // Raison d'un refus, pour les avis.
    public static function raison_refus( $nb ) {
        return sprintf(
            __( 'Version gratuite : ce message compte %1$d destinataire(s) et il reste %2$d envoi(s) sur %3$d ce mois-ci. Prochain envoi possible le %4$s, ou débloquez SeliwebMedia (Paramétrage → Déblocage).', 'seliwebmedia' ),
            (int) $nb, self::restant(), self::plafond(), self::prochain_mois()
        );
    }

    // Ligne d'état : « Version gratuite : 3 envois sur 200 ce mois-ci » ou « Quota SeliwebMedia débloqué ».
    public static function etat_texte() {
        if ( self::est_debloque() ) {
            $etat = Seliweb_Media_Licence::etat();
            return $etat['message'];
        }
        return sprintf( __( 'Version gratuite : %1$d envois sur %2$d ce mois-ci.', 'seliwebmedia' ), self::utilises(), self::plafond() );
    }

    // Mention en bas des messages : seulement en version gratuite.
    public static function mention_active() {
        return ! self::est_debloque();
    }

    public static function mention_texte() {
        return __( 'Envoyé avec SeliwebMedia — connex-asso.net', 'seliwebmedia' );
    }
}
