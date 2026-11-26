<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mises à jour de SeliwebMedia par Connex Licences (connex-asso.net), comme
 * AbeloMag. Le dépôt GitHub est privé : seul Connex Licences y a accès.
 *
 * - L'en-tête « Update URI: https://www.connex-asso.net/seliwebmedia » de
 *   l'extension indique à WordPress de ne pas la chercher sur wordpress.org
 *   et de demander sa version au filtre update_plugins_www.connex-asso.net.
 * - La version vient de la même réponse que l'abonnement
 *   (Seliweb_Media_Licence::info(), service mise-a-jour).
 * - Le téléchargement passe par le service telecharger de Connex Licences,
 *   qui contrôle la clé et le site (en-têtes X-Connex-Cle / X-Connex-Site,
 *   jamais dans l'adresse) puis renvoie vers une adresse temporaire du zip.
 * - Jamais de mise à jour sur un site local ni sur une copie de
 *   développement (dossier suivi par git) : elle remplacerait le code en
 *   cours d'écriture.
 */
class Seliweb_Media_Updater {

    const PAQUET = 'seliwebmedia';

    public static function init() {
        add_filter( 'update_plugins_www.connex-asso.net', array( __CLASS__, 'version_disponible' ), 10, 3 );
        add_filter( 'upgrader_pre_download', array( __CLASS__, 'telecharger' ), 10, 2 );
        add_filter( 'plugins_api', array( __CLASS__, 'fiche' ), 10, 3 );
    }

    private static function plugin_file() {
        return defined( 'SELIWEBMEDIA_PLUGIN_FILE' ) ? SELIWEBMEDIA_PLUGIN_FILE : 'seliwebmedia/seliwebmedia.php';
    }

    // Site local (WP_ENVIRONMENT_TYPE = local) ou copie suivie par git : pas de mise à jour.
    public static function est_developpement() {
        return 'local' === wp_get_environment_type() || is_dir( SELIWEBMEDIA_DIR . '.git' );
    }

    /** Dernière version selon Connex : array( version, notes, date, telechargement ) ou WP_Error. */
    public static function derniere_version() {
        $info = Seliweb_Media_Licence::info();
        if ( is_wp_error( $info ) ) {
            return $info;
        }
        if ( ! $info['valide'] ) {
            $motifs = Seliweb_Media_Licence::motifs();
            return new WP_Error( 'refus', $motifs[ $info['motif'] ] ?? __( 'Clé refusée par Connex.', 'seliwebmedia' ) );
        }
        $p = isset( $info['paquets'][ self::PAQUET ] ) ? (array) $info['paquets'][ self::PAQUET ] : array();
        if ( ! empty( $p['erreur'] ) ) {
            return new WP_Error( 'paquet', sprintf( __( 'Connex : %s', 'seliwebmedia' ), sanitize_text_field( $p['erreur'] ) ) );
        }
        if ( empty( $p['version'] ) || empty( $p['telechargement'] ) ) {
            return new WP_Error( 'paquet', __( 'Connex ne propose pas encore de version de SeliwebMedia.', 'seliwebmedia' ) );
        }
        return array(
            'version'        => sanitize_text_field( $p['version'] ),
            'notes'          => isset( $p['notes'] ) ? (string) $p['notes'] : '',
            'date'           => isset( $p['date'] ) ? sanitize_text_field( $p['date'] ) : '',
            'telechargement' => esc_url_raw( $p['telechargement'] ),
        );
    }

    // WordPress range la réponse en « mise à jour disponible » ou « à jour » ;
    // dans les deux cas, le lien des mises à jour automatiques s'affiche.
    public static function version_disponible( $update, $plugin_data, $plugin_file ) {
        if ( self::plugin_file() !== $plugin_file || self::est_developpement() ) {
            return $update;
        }
        $v = self::derniere_version();
        if ( is_wp_error( $v ) ) {
            return $update;
        }
        return array(
            'id'           => 'https://www.connex-asso.net/seliwebmedia',
            'slug'         => self::PAQUET,
            'version'      => $v['version'],
            'url'          => 'https://www.connex-asso.net/',
            'package'      => $v['telechargement'],
            'requires'     => '6.0',
            'requires_php' => '7.4',
        );
    }

    public static function telecharger( $reply, $package ) {
        if ( false !== $reply || ! is_string( $package ) || false === strpos( $package, 'connex-licences/v1/telecharger' ) ) {
            return $reply;
        }
        $hote_paquet = preg_replace( '/^www\./', '', (string) wp_parse_url( $package, PHP_URL_HOST ) );
        $hote_connex = preg_replace( '/^www\./', '', (string) wp_parse_url( Seliweb_Media_Licence::url_connex(), PHP_URL_HOST ) );
        if ( $hote_paquet !== $hote_connex || false === strpos( $package, 'paquet=' . self::PAQUET ) ) {
            return $reply; // paquet d'une autre extension (AbeloMag…) : elle s'en charge
        }
        $res = wp_remote_get( $package, array(
            'timeout'     => 30,
            'redirection' => 0,
            'headers'     => array(
                'X-Connex-Cle'  => Seliweb_Media_Licence::cle_enregistree(),
                'X-Connex-Site' => home_url(),
            ),
        ) );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        $code     = (int) wp_remote_retrieve_response_code( $res );
        $location = wp_remote_retrieve_header( $res, 'location' );
        if ( is_array( $location ) ) {
            $location = reset( $location );
        }
        if ( $code >= 300 && $code < 400 && $location ) {
            return download_url( (string) $location, 300 );
        }
        return new WP_Error( 'seliwebmedia_telechargement', sprintf(
            __( 'Téléchargement refusé par Connex (code %d) : vérifiez la clé dans SeliwebMedia → Paramétrage → Licence.', 'seliwebmedia' ), $code
        ) );
    }

    // Fenêtre « Afficher les détails » de l'écran Extensions.
    public static function fiche( $res, $action, $args ) {
        if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::PAQUET !== $args->slug ) {
            return $res;
        }
        $v = self::derniere_version();
        if ( is_wp_error( $v ) ) {
            return $v;
        }
        $notes = '' !== trim( $v['notes'] ) ? wpautop( esc_html( $v['notes'] ) ) : '<p>' . esc_html__( 'Pas de note pour cette version.', 'seliwebmedia' ) . '</p>';
        return (object) array(
            'name'          => 'SeliwebMedia',
            'slug'          => self::PAQUET,
            'version'       => $v['version'],
            'author'        => 'Philippe Le Duigou',
            'homepage'      => 'https://www.connex-asso.net/',
            'requires'      => '6.0',
            'requires_php'  => '7.4',
            'last_updated'  => $v['date'],
            'download_link' => $v['telechargement'],
            'sections'      => array(
                'description' => '<p>' . esc_html__( "Listes de diffusion, newsletters et gestion des réseaux sociaux — en lien avec les groupes de l'extension Seliweb-WP si elle est active.", 'seliwebmedia' ) . '</p>',
                'changelog'   => '<h4>' . esc_html( sprintf( __( 'Version %s', 'seliwebmedia' ), $v['version'] ) ) . '</h4>' . $notes,
            ),
        );
    }

    /** Ligne d'état pour l'écran Licence et le tableau de bord SeliwebMedia. */
    public static function statut() {
        $v = self::derniere_version();
        if ( is_wp_error( $v ) ) {
            return $v->get_error_message();
        }
        if ( self::est_developpement() ) {
            return sprintf( __( 'Site local ou copie de développement : pas de mise à jour automatique. Dernière version publiée : %s.', 'seliwebmedia' ), $v['version'] );
        }
        return version_compare( $v['version'], SELIWEBMEDIA_VERSION, '>' )
            ? sprintf( __( 'Version %s disponible (Tableau de bord → Mises à jour, ou écran Extensions).', 'seliwebmedia' ), $v['version'] )
            : __( 'À jour.', 'seliwebmedia' );
    }
}
