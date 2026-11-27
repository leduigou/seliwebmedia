<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mises à jour libres de SeliwebMedia, sans clé : la dernière release
 * publique du dépôt GitHub leduigou/seliwebmedia, fichier seliwebmedia.zip.
 *
 * - L'en-tête « Update URI: https://github.com/leduigou/seliwebmedia » de
 *   l'extension indique à WordPress de ne pas la chercher sur wordpress.org
 *   et de demander sa version au filtre update_plugins_github.com. Dans les
 *   deux cas (à jour ou non), le lien des mises à jour automatiques
 *   s'affiche dans l'écran Extensions.
 * - Réponse de GitHub gardée 6 h (1 h en cas d'erreur).
 * - Jamais de mise à jour sur un site local ni sur une copie de
 *   développement (dossier suivi par git) : elle remplacerait le code en
 *   cours d'écriture.
 *
 * Les versions 0.6.12 interrogeaient Connex Licences avec la clé : c'est
 * par ce chemin qu'elles reçoivent la version suivante (le zip doit donc
 * toujours s'appeler seliwebmedia.zip).
 */
class Seliweb_Media_Updater {

    const DEPOT     = 'leduigou/seliwebmedia';
    const FICHIER   = 'seliwebmedia.zip';
    const SLUG      = 'seliwebmedia';
    const TRANSIENT = 'seliwebmedia_release';

    public static function init() {
        add_filter( 'update_plugins_github.com', array( __CLASS__, 'version_disponible' ), 10, 3 );
        add_filter( 'plugins_api', array( __CLASS__, 'fiche' ), 10, 3 );
        // « Rechercher les mises à jour » de WordPress : on relit GitHub aussi.
        add_action( 'delete_site_transient_update_plugins', array( __CLASS__, 'oublier' ) );
    }

    public static function oublier() {
        delete_transient( self::TRANSIENT );
    }

    private static function plugin_file() {
        return defined( 'SELIWEBMEDIA_PLUGIN_FILE' ) ? SELIWEBMEDIA_PLUGIN_FILE : 'seliwebmedia/seliwebmedia.php';
    }

    // Site local (WP_ENVIRONMENT_TYPE = local) ou copie suivie par git : pas de mise à jour.
    public static function est_developpement() {
        return 'local' === wp_get_environment_type() || is_dir( SELIWEBMEDIA_DIR . '.git' );
    }

    /** Dernière version publiée : array( version, notes, date, telechargement, page ) ou WP_Error. */
    public static function derniere_version() {
        $cache = get_transient( self::TRANSIENT );
        if ( false !== $cache ) {
            return $cache;
        }
        $res  = wp_remote_get( 'https://api.github.com/repos/' . self::DEPOT . '/releases/latest', array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'SeliwebMedia/' . SELIWEBMEDIA_VERSION,
            ),
        ) );
        $code = (int) wp_remote_retrieve_response_code( $res );
        $data = json_decode( (string) wp_remote_retrieve_body( $res ), true );

        if ( is_wp_error( $res ) ) {
            $out = new WP_Error( 'http', sprintf( __( 'GitHub injoignable : %s', 'seliwebmedia' ), $res->get_error_message() ) );
        } elseif ( 200 !== $code || empty( $data['tag_name'] ) ) {
            $out = new WP_Error( 'http', sprintf( __( 'Aucune version publique trouvée (code %d).', 'seliwebmedia' ), $code ) );
        } else {
            $zip = '';
            foreach ( (array) ( $data['assets'] ?? array() ) as $a ) {
                if ( isset( $a['name'], $a['browser_download_url'] ) && self::FICHIER === $a['name'] ) {
                    $zip = (string) $a['browser_download_url'];
                    break;
                }
            }
            $version = ltrim( (string) $data['tag_name'], 'vV' );
            $out = $zip ? array(
                'version'        => sanitize_text_field( $version ),
                'notes'          => (string) ( $data['body'] ?? '' ),
                'date'           => substr( (string) ( $data['published_at'] ?? '' ), 0, 10 ),
                'telechargement' => esc_url_raw( $zip ),
                'page'           => esc_url_raw( (string) ( $data['html_url'] ?? '' ) ),
            ) : new WP_Error( 'paquet', sprintf( __( 'La version %s n\'a pas de fichier seliwebmedia.zip.', 'seliwebmedia' ), $version ) );
        }
        set_transient( self::TRANSIENT, $out, is_wp_error( $out ) ? HOUR_IN_SECONDS : 6 * HOUR_IN_SECONDS );
        return $out;
    }

    public static function version_disponible( $update, $plugin_data, $plugin_file ) {
        if ( self::plugin_file() !== $plugin_file || self::est_developpement() ) {
            return $update;
        }
        $v = self::derniere_version();
        if ( is_wp_error( $v ) ) {
            return $update;
        }
        return array(
            'id'           => 'https://github.com/' . self::DEPOT,
            'slug'         => self::SLUG,
            'version'      => $v['version'],
            'url'          => 'https://www.connex-asso.net/',
            'package'      => $v['telechargement'],
            'requires'     => '6.0',
            'requires_php' => '7.4',
        );
    }

    // Fenêtre « Afficher les détails » de l'écran Extensions.
    public static function fiche( $res, $action, $args ) {
        if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
            return $res;
        }
        $v = self::derniere_version();
        if ( is_wp_error( $v ) ) {
            return $v;
        }
        $notes = '' !== trim( $v['notes'] ) ? wpautop( esc_html( $v['notes'] ) ) : '<p>' . esc_html__( 'Pas de note pour cette version.', 'seliwebmedia' ) . '</p>';
        return (object) array(
            'name'          => 'SeliwebMedia',
            'slug'          => self::SLUG,
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
