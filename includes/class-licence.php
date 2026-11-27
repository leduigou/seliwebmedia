<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clé de déblocage SeliwebMedia, fournie par l'association Connex (Connex
 * Licences, installé sur www.connex-asso.net).
 *
 * Le site présente sa clé au service mise-a-jour de Connex Licences
 * ({ cle, site, produit: seliwebmedia }), qui contrôle la clé (active,
 * échéance, produit, site lié) et renvoie { valide, motif, echeance }.
 * Clé valide : quota débloqué (pas de limite mensuelle ni de mention en bas
 * des messages, voir Seliweb_Media_Quota). Pas de clé, ou clé expirée,
 * révoquée ou refusée : version gratuite, jamais de blocage. Échéance
 * affichée et bandeau 30 jours avant la fin pour les clés à échéance
 * (abonnements d'hébergement). Les mises à jour sont libres et ne dépendent
 * pas de la clé (voir Seliweb_Media_Updater).
 *
 * La clé est liée au premier site qui la présente : un autre site reçoit le
 * motif « autre_site ». Réponse gardée 6 h (1 h en cas d'erreur). Si Connex
 * ne répond pas, la dernière réponse valide reste acceptée pendant 7 jours.
 */
class Seliweb_Media_Licence {

    const OPTION_CLE      = 'seliwebmedia_cle_api';
    const OPTION_ETAT     = 'seliwebmedia_connex_etat'; // dernier état connu (alertes, tolérance)
    const TRANSIENT_INFO  = 'seliwebmedia_connex_info';
    const PRODUIT         = 'seliwebmedia';
    const JOURS_ALERTE    = 30; // bandeau quand l'abonnement se termine dans moins de N jours
    const JOURS_TOLERANCE = 7;  // Connex injoignable : dernière réponse valide acceptée N jours

    public static function init() {
        add_action( 'admin_post_seliwebmedia_verifier_licence', array( __CLASS__, 'handle_verification_manuelle' ) );
        add_action( 'admin_notices', array( __CLASS__, 'afficher_bandeau' ) );
    }

    // Adresse de Connex Licences (constante SELIWEBMEDIA_CONNEX_URL pour les essais en local).
    public static function url_connex() {
        return defined( 'SELIWEBMEDIA_CONNEX_URL' ) && SELIWEBMEDIA_CONNEX_URL ? (string) SELIWEBMEDIA_CONNEX_URL : 'https://www.connex-asso.net';
    }

    public static function endpoint( $route ) {
        return untrailingslashit( self::url_connex() ) . '/wp-json/connex-licences/v1/' . $route;
    }

    public static function cle_enregistree() {
        return trim( (string) get_option( self::OPTION_CLE, '' ) );
    }

    public static function enregistrer_cle( $cle ) {
        update_option( self::OPTION_CLE, sanitize_text_field( $cle ) );
        self::oublier();
    }

    // Oublie la dernière réponse : Connex est interrogé de nouveau à la prochaine occasion.
    public static function oublier() {
        delete_transient( self::TRANSIENT_INFO );
        delete_site_transient( 'update_plugins' );
    }

    public static function motifs() {
        return array(
            'inconnue'   => __( "Clé inconnue de Connex : vérifiez qu'elle a été copiée en entier.", 'seliwebmedia' ),
            'revoquee'   => __( 'Clé révoquée par Connex.', 'seliwebmedia' ),
            'expiree'    => __( 'Abonnement Connex expiré.', 'seliwebmedia' ),
            'produit'    => __( "Cette clé n'est pas une clé SeliwebMedia.", 'seliwebmedia' ),
            'site'       => __( 'Adresse du site non reconnue par Connex.', 'seliwebmedia' ),
            'autre_site' => __( 'Clé déjà utilisée par un autre site.', 'seliwebmedia' ),
        );
    }

    /**
     * Réponse de Connex : array( valide, motif, echeance, paquets ) ou WP_Error
     * (pas de clé, Connex injoignable, réponse inattendue).
     */
    public static function info( $rafraichir = false ) {
        $cle    = self::cle_enregistree();
        $cache  = $rafraichir ? false : get_transient( self::TRANSIENT_INFO );
        if ( false !== $cache && isset( $cache['hash'] ) && md5( $cle . '|' . self::url_connex() ) === $cache['hash'] ) {
            return $cache['data'];
        }
        if ( '' === $cle ) {
            return new WP_Error( 'pas_de_cle', __( 'Aucune clé renseignée.', 'seliwebmedia' ) );
        }

        $res  = wp_remote_post( self::endpoint( 'mise-a-jour' ), array(
            'timeout' => 15,
            'body'    => array(
                'cle'     => $cle,
                'site'    => home_url(),
                'produit' => self::PRODUIT,
            ),
        ) );
        $code = (int) wp_remote_retrieve_response_code( $res );
        $data = json_decode( (string) wp_remote_retrieve_body( $res ), true );

        if ( is_wp_error( $res ) ) {
            $out = new WP_Error( 'http', sprintf( __( 'Connex injoignable : %s', 'seliwebmedia' ), $res->get_error_message() ) );
        } elseif ( 200 !== $code || ! is_array( $data ) || ! isset( $data['valide'] ) ) {
            $out = new WP_Error( 'http', sprintf( __( 'Réponse inattendue de Connex (code %d).', 'seliwebmedia' ), $code ) );
        } else {
            $out = array(
                'valide'   => (bool) $data['valide'],
                'motif'    => isset( $data['motif'] ) ? sanitize_key( $data['motif'] ) : '',
                'echeance' => isset( $data['echeance'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $data['echeance'] ) ? (string) $data['echeance'] : '',
                'paquets'  => isset( $data['paquets'] ) && is_array( $data['paquets'] ) ? $data['paquets'] : array(),
            );
            $ancien = (array) get_option( self::OPTION_ETAT, array() );
            update_option( self::OPTION_ETAT, array(
                'verifie'  => time(),
                'ok'       => $out['valide'] ? time() : 0,
                'valide'   => $out['valide'],
                'motif'    => $out['motif'],
                'echeance' => $out['valide'] ? $out['echeance'] : ( $ancien['echeance'] ?? '' ),
            ), false );
        }
        set_transient( self::TRANSIENT_INFO, array( 'hash' => md5( $cle . '|' . self::url_connex() ), 'data' => $out ),
            is_wp_error( $out ) ? HOUR_IN_SECONDS : 6 * HOUR_IN_SECONDS );
        return $out;
    }

    /**
     * État de l'abonnement, pour l'envoi et l'affichage :
     * array( valide, message, echeance, tolerance ).
     */
    public static function etat() {
        $info = self::info();
        if ( is_wp_error( $info ) ) {
            if ( 'pas_de_cle' === $info->get_error_code() ) {
                return array( 'valide' => false, 'message' => $info->get_error_message(), 'echeance' => '', 'tolerance' => false );
            }
            // Connex injoignable : la dernière réponse valide reste acceptée quelques jours.
            $etat = (array) get_option( self::OPTION_ETAT, array() );
            $ok   = (int) ( $etat['ok'] ?? 0 );
            if ( $ok && $ok > time() - self::JOURS_TOLERANCE * DAY_IN_SECONDS
                 && ( empty( $etat['echeance'] ) || $etat['echeance'] >= current_time( 'Y-m-d' ) ) ) {
                return array(
                    'valide'    => true,
                    'message'   => sprintf( __( '%1$s. Dernière vérification réussie le %2$s.', 'seliwebmedia' ), rtrim( $info->get_error_message(), '. ' ), wp_date( 'd/m/Y', $ok ) ),
                    'echeance'  => (string) ( $etat['echeance'] ?? '' ),
                    'tolerance' => true,
                );
            }
            return array( 'valide' => false, 'message' => $info->get_error_message(), 'echeance' => '', 'tolerance' => false );
        }
        if ( ! $info['valide'] ) {
            $motifs = self::motifs();
            return array(
                'valide'    => false,
                'message'   => $motifs[ $info['motif'] ] ?? __( 'Clé refusée par Connex.', 'seliwebmedia' ),
                'echeance'  => '',
                'tolerance' => false,
            );
        }
        return array(
            'valide'    => true,
            'message'   => $info['echeance']
                ? sprintf( __( "Quota SeliwebMedia débloqué jusqu'au %s.", 'seliwebmedia' ), self::date_fr( $info['echeance'] ) )
                : __( 'Quota SeliwebMedia débloqué.', 'seliwebmedia' ),
            'echeance'  => $info['echeance'],
            'tolerance' => false,
        );
    }

    // Vrai si l'abonnement autorise l'envoi des messages.
    public static function est_active() {
        $etat = self::etat();
        return ! empty( $etat['valide'] );
    }

    public static function date_fr( $ymd ) {
        $ts = strtotime( $ymd . ' 12:00:00' );
        return $ts ? wp_date( 'd/m/Y', $ts ) : $ymd;
    }

    /**
     * Bandeau : déblocage qui se termine dans moins de 30 jours, ou clé
     * saisie mais refusée (le site repasse en version gratuite). Rien sans
     * clé : la version gratuite est un fonctionnement normal. '' sinon.
     */
    public static function alerte() {
        if ( '' === self::cle_enregistree() ) {
            return '';
        }
        $etat = self::etat();
        if ( ! $etat['valide'] ) {
            return sprintf( __( 'SeliwebMedia : %s SeliwebMedia fonctionne en version gratuite (%d envois par mois).', 'seliwebmedia' ), $etat['message'], Seliweb_Media_Quota::plafond() );
        }
        if ( $etat['echeance'] && $etat['echeance'] <= wp_date( 'Y-m-d', strtotime( '+' . self::JOURS_ALERTE . ' days' ) ) ) {
            return sprintf( __( "Le déblocage de SeliwebMedia se termine le %s : pensez à le renouveler auprès de l'association Connex.", 'seliwebmedia' ), self::date_fr( $etat['echeance'] ) );
        }
        return '';
    }

    // Affiché aux administrateurs SeliwebMedia, sur le tableau de bord WordPress et les écrans SeliwebMedia.
    public static function afficher_bandeau() {
        if ( ! class_exists( 'Seliweb_Media_Droits' ) || ! Seliweb_Media_Droits::est_admin() ) return;
        $ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $ecran || ( 'dashboard' !== $ecran->id && false === strpos( $ecran->id, 'seliwebmedia' ) ) ) return;
        $message = self::alerte();
        if ( '' === $message ) return;
        $etat = self::etat();
        $classe = $etat['valide'] ? 'notice-warning' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $classe ) . '"><p>' . esc_html( $message )
            . ' <a href="' . esc_url( admin_url( 'admin.php?page=seliwebmedia&tab=licence' ) ) . '">' . esc_html__( 'Voir le déblocage', 'seliwebmedia' ) . '</a></p></div>';
    }

    // Bouton « Vérifier maintenant » de l'écran Licence.
    public static function handle_verification_manuelle() {
        if ( ! Seliweb_Media_Droits::est_admin() ) {
            wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        }
        check_admin_referer( 'seliwebmedia_verifier_licence' );

        self::oublier();
        self::info( true );

        wp_safe_redirect( add_query_arg( 'verifie', '1', admin_url( 'admin.php?page=seliwebmedia&tab=licence' ) ) );
        exit;
    }
}
