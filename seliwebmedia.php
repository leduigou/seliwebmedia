<?php
/*
 * Plugin Name: SeliwebMedia
 * Description: Listes de diffusion, newsletters et gestion des réseaux sociaux — en lien avec les groupes de l'extension Seliweb-WP si elle est active.
 * Version: 0.6.9
 * Author: Philippe Le Duigou
 * Text Domain: seliwebmedia
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SELIWEBMEDIA_VERSION', '0.6.9' );
define( 'SELIWEBMEDIA_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SELIWEBMEDIA_URL',     plugin_dir_url( __FILE__ ) );
// Chemin réel tel que WordPress l'a chargé (dossier/fichier.php) — même
// raison que SELIWEB_PLUGIN_FILE côté Seliweb : ne pas supposer que le
// dossier s'appelle "seliwebmedia" (cf. Seliweb_Media_Updater).
define( 'SELIWEBMEDIA_PLUGIN_FILE', plugin_basename( __FILE__ ) );

require_once SELIWEBMEDIA_DIR . 'includes/class-database.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-licence.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-integration-seliweb.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-parametres.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-listes.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-messages.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-mail-queue.php';
require_once SELIWEBMEDIA_DIR . 'includes/class-updater.php';

Seliweb_Media_Licence::init();
Seliweb_Media_Parametres::init();
Seliweb_Media_Listes::init();
Seliweb_Media_Messages::init();
Seliweb_Media_Mail_Queue::init();
Seliweb_Media_Updater::init();

class SeliwebMedia {

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'admin_init',     array( 'Seliweb_Media_Database', 'install' ) );
        add_action( 'admin_menu',     array( $this, 'create_menu' ) );
        add_action( 'admin_notices',  array( $this, 'notice_seliweb_absent' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'seliwebmedia', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    // Menu admin : "Listes mails" (listes + messages) en écran principal,
    // "Paramétrage" (Tableau de bord/Licence/Mails) en premier — c'est
    // l'écran par défaut quand on clique sur SeliwebMedia — puis "Listes
    // mails". Les écrans Newsletters et Réseaux sociaux viendront
    // s'ajouter ici au fil du développement.
    public function create_menu() {
        add_menu_page(
            __( 'SeliwebMedia', 'seliwebmedia' ),
            __( 'SeliwebMedia', 'seliwebmedia' ),
            'manage_options',
            'seliwebmedia',
            array( 'Seliweb_Media_Parametres', 'render_dashboard' ),
            'dashicons-megaphone',
            // Seliweb demande la position 2, mais WordPress l'occupe déjà
            // pour son propre menu Tableau de bord (wp-admin/menu.php) :
            // Seliweb entre donc en collision et se retrouve décalé par
            // WordPress à une position pseudo-aléatoire proche de 2 (entre
            // 2 et 2.65, calculée via un hash — jamais exactement 2). La
            // prochaine position fixe du cœur WordPress est le séparateur
            // 4 ; 3 se place donc toujours après Seliweb, quel que soit le
            // décalage exact qu'il reçoit.
            3
        );
        add_submenu_page(
            'seliwebmedia',
            __( 'Paramétrage SeliwebMedia', 'seliwebmedia' ),
            __( 'Paramétrage', 'seliwebmedia' ),
            'manage_options',
            'seliwebmedia',
            array( 'Seliweb_Media_Parametres', 'render_dashboard' )
        );
        add_submenu_page(
            'seliwebmedia',
            __( 'Listes mails', 'seliwebmedia' ),
            __( 'Listes mails', 'seliwebmedia' ),
            'manage_options',
            'seliwebmedia_listes',
            array( 'Seliweb_Media_Listes', 'render' )
        );
    }

    // Rappel discret (pas bloquant) si Seliweb n'est pas détecté : les
    // fonctions liste de diffusion/newsletter restent utilisables seules,
    // seul le lien avec les groupes Seliweb ne s'activera pas. Voir
    // Seliweb_Media_Integration::est_disponible().
    public function notice_seliweb_absent() {
        if ( ! function_exists( 'get_current_screen' ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'seliwebmedia' ) === false ) return;
        if ( Seliweb_Media_Integration::est_disponible() ) return;

        echo '<div class="notice notice-info"><p>' . esc_html__(
            "L'extension Seliweb-WP n'est pas détectée sur ce site : les fonctions liste de diffusion et newsletter restent utilisables, mais l'envoi aux groupes Seliweb n'est pas disponible.",
            'seliwebmedia'
        ) . '</p></div>';
    }
}

new SeliwebMedia();
