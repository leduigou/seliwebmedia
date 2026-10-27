<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activation du plugin par clé API — fournie par l'association Connex.
 *
 * SeliwebMedia reste librement téléchargeable et installable ; c'est
 * l'utilisation des fonctions d'envoi (listes de diffusion, newsletters,
 * réseaux sociaux) qui nécessite une clé valide. Le téléchargement du
 * plugin lui-même n'est jamais bloqué par cette classe.
 *
 * Le point d'API est fourni par le plugin « Connex Licences », installé
 * sur www.connex-asso.net (dépôt privé leduigou/connex-licences). Il reçoit
 * { cle, site_url } et renvoie { valide: bool } — voir verifier_cle().
 */
class Seliweb_Media_Licence {

    const OPTION_CLE   = 'seliwebmedia_cle_api';
    const TRANSIENT_ETAT = 'seliwebmedia_licence_etat';
    const URL_VERIFICATION = 'https://www.connex-asso.net/wp-json/connex-licences/v1/verifier';

    // Durée de mise en cache d'une vérification réussie — évite d'appeler
    // le serveur Connex à chaque chargement de page, et laisse une licence
    // valide continuer à fonctionner si le serveur est temporairement
    // indisponible au moment du renouvellement du cache.
    const CACHE_DUREE = DAY_IN_SECONDS;

    public static function init() {
        add_action( 'admin_post_seliwebmedia_verifier_licence', array( __CLASS__, 'handle_verification_manuelle' ) );
    }

    public static function cle_enregistree() {
        return trim( (string) get_option( self::OPTION_CLE, '' ) );
    }

    public static function enregistrer_cle( $cle ) {
        update_option( self::OPTION_CLE, sanitize_text_field( $cle ) );
        delete_transient( self::TRANSIENT_ETAT );
    }

    // Vrai si une clé a été saisie ET validée par le serveur Connex
    // (avec mise en cache — voir CACHE_DUREE). C'est cette méthode que le
    // reste du plugin doit appeler avant d'autoriser un envoi réel.
    public static function est_active() {
        $cle = self::cle_enregistree();
        if ( ! $cle ) return false;

        $etat = get_transient( self::TRANSIENT_ETAT );
        if ( $etat === false ) {
            $etat = self::verifier_cle( $cle );
            set_transient( self::TRANSIENT_ETAT, $etat, self::CACHE_DUREE );
        }
        return ! empty( $etat['valide'] );
    }

    // Appel réseau vers le service Connex. Retourne toujours un tableau
    // ['valide' => bool, 'message' => string] — jamais d'exception, pour
    // que l'absence/l'indisponibilité du service Connex ne casse jamais
    // le reste du plugin.
    public static function verifier_cle( $cle ) {
        $reponse = wp_remote_post( self::URL_VERIFICATION, array(
            'timeout' => 10,
            'body'    => array(
                'cle'      => $cle,
                'site_url' => home_url( '/' ),
            ),
        ) );

        if ( is_wp_error( $reponse ) || wp_remote_retrieve_response_code( $reponse ) !== 200 ) {
            return array( 'valide' => false, 'message' => __( 'Service de licence Connex indisponible.', 'seliwebmedia' ) );
        }

        $corps = json_decode( wp_remote_retrieve_body( $reponse ), true );
        if ( empty( $corps['valide'] ) ) {
            return array( 'valide' => false, 'message' => __( 'Clé API invalide ou expirée.', 'seliwebmedia' ) );
        }

        return array( 'valide' => true, 'message' => __( 'Licence active.', 'seliwebmedia' ) );
    }

    // Bouton "Vérifier maintenant" de l'écran Licence — force une nouvelle
    // vérification au lieu d'attendre l'expiration du cache.
    public static function handle_verification_manuelle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        }
        check_admin_referer( 'seliwebmedia_verifier_licence' );

        delete_transient( self::TRANSIENT_ETAT );
        self::est_active(); // relance la vérification, remplit le cache

        wp_safe_redirect( add_query_arg( 'verifie', '1', admin_url( 'admin.php?page=seliwebmedia&tab=licence' ) ) );
        exit;
    }
}
