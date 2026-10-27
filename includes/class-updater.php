<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Branche le mécanisme de mise à jour natif de WordPress sur le dépôt
 * GitHub leduigou/seliwebmedia — même principe que Seliweb_Updater côté
 * Seliweb, simplifié (un seul composant : pas de thème associé).
 */
class Seliweb_Media_Updater {

    const GH_USER  = 'leduigou';
    const GH_REPO  = 'seliwebmedia';
    const PLUGIN_SLUG = 'seliwebmedia';

    private static function plugin_file() {
        return defined( 'SELIWEBMEDIA_PLUGIN_FILE' ) ? SELIWEBMEDIA_PLUGIN_FILE : self::PLUGIN_SLUG . '/seliwebmedia.php';
    }

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_plugin_update' ) );
        add_filter( 'plugins_api',                           array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                 array( __CLASS__, 'post_install' ), 10, 3 );
        add_action( 'delete_site_transient_update_plugins',  array( __CLASS__, 'clear_release_cache' ) );
    }

    public static function clear_release_cache() {
        delete_transient( 'seliwebmedia_gh_release' );
    }

    private static function get_release() {
        $transient_key = 'seliwebmedia_gh_release';
        $cached = get_transient( $transient_key );
        if ( $cached !== false ) return $cached;

        $url      = 'https://api.github.com/repos/' . self::GH_USER . '/' . self::GH_REPO . '/releases/latest';
        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $body->tag_name ) ) return false;

        // Préférer le zip joint à la release (construit par build-zip.sh) à
        // l'archive source auto-générée par GitHub — voir Seliweb_Updater
        // pour le piège rencontré avec un second fichier joint (ex. un PDF)
        // placé avant le zip dans la réponse de l'API.
        $zip_url = '';
        if ( ! empty( $body->assets ) && is_array( $body->assets ) ) {
            foreach ( $body->assets as $asset ) {
                if ( ! empty( $asset->browser_download_url ) && preg_match( '/\.zip$/i', $asset->name ?? '' ) ) {
                    $zip_url = $asset->browser_download_url;
                    break;
                }
            }
        }
        if ( ! $zip_url ) {
            $zip_url = $body->zipball_url ?? '';
        }

        $release = array(
            'version'  => ltrim( $body->tag_name, 'v' ),
            'zip_url'  => $zip_url,
            'page_url' => $body->html_url ?? '',
            'notes'    => $body->body     ?? '',
        );

        set_transient( $transient_key, $release, 6 * HOUR_IN_SECONDS );
        return $release;
    }

    public static function check_plugin_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = self::get_release();
        if ( ! $release ) return $transient;

        $plugin_file = self::plugin_file();
        if ( version_compare( $release['version'], SELIWEBMEDIA_VERSION, '>' ) ) {
            $transient->response[ $plugin_file ] = (object) array(
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_file,
                'new_version' => $release['version'],
                'url'         => $release['page_url'],
                'package'     => $release['zip_url'],
            );
        } else {
            $transient->no_update[ $plugin_file ] = (object) array(
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_file,
                'new_version' => $release['version'],
                'url'         => $release['page_url'],
                'package'     => '',
            );
        }

        return $transient;
    }

    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ( $args->slug ?? '' ) !== self::PLUGIN_SLUG ) return $result;

        $release = self::get_release();
        if ( ! $release ) return $result;

        return (object) array(
            'name'          => 'SeliwebMedia',
            'slug'          => self::PLUGIN_SLUG,
            'version'       => $release['version'],
            'author'        => '<a href="https://github.com/' . self::GH_USER . '">Philippe Le Duigou</a>',
            'homepage'      => 'https://github.com/' . self::GH_USER . '/' . self::GH_REPO,
            'requires'      => '6.0',
            'tested'        => get_bloginfo('version'),
            'sections'      => array(
                'description' => "Listes de diffusion, newsletters et gestion des réseaux sociaux, en lien avec les groupes de l'extension Seliweb-WP.",
                'changelog'   => '<pre>' . esc_html( $release['notes'] ) . '</pre>',
            ),
            'download_link' => $release['zip_url'],
        );
    }

    // Garantit que le dossier porte le nom canonique "seliwebmedia/" après
    // une mise à jour — même piège/raisonnement que Seliweb_Updater::post_install().
    public static function post_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        $plugin_file_avant = self::plugin_file();
        if ( ( $hook_extra['plugin'] ?? '' ) !== $plugin_file_avant ) return $result;

        $dest = WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG;
        if ( basename( untrailingslashit( (string) $result['destination'] ) ) !== self::PLUGIN_SLUG ) {
            $etait_actif = is_plugin_active( $plugin_file_avant );
            $wp_filesystem->move( $result['destination'], $dest, true );
            $result['destination']       = $dest;
            $result['destination_name']  = self::PLUGIN_SLUG;
            $result['remote_destination'] = $dest;
            if ( $etait_actif ) {
                activate_plugin( self::PLUGIN_SLUG . '/seliwebmedia.php' );
            }
        }
        delete_transient( 'seliwebmedia_gh_release' );
        return $result;
    }
}
