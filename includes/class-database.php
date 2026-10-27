<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Création/migration des tables SeliwebMedia — même mécanisme que
 * Seliweb_Database côté Seliweb (version comparée à chaque admin_init,
 * migrations ajoutées au tableau $sql au fil des versions).
 *
 * Schéma (préfixe wp_seliwebmedia_*, indépendant de Seliweb) :
 * - listes              : listes de diffusion natives SeliwebMedia.
 * - contacts            : une ligne par adresse mail, réutilisable entre listes.
 * - abonnements         : table pivot contact <-> liste (un contact peut
 *                         être inscrit à plusieurs listes, se désabonner
 *                         d'une liste sans affecter les autres).
 * - messages            : ce que l'utilisateur appelle "message" (pas
 *                         "campagne"), avec statut et date d'envoi prévue.
 * - message_cibles      : listes/groupes Seliweb visés par un message
 *                         (plusieurs cibles possibles par message).
 * - message_destinataires : photo des destinataires réels d'un message,
 *                         construite au moment de l'envoi (jamais avant,
 *                         pour les groupes Seliweb dont la composition
 *                         peut changer entre programmation et envoi). Sert
 *                         aussi de file d'attente : le cron à la minute
 *                         puise directement dans cette table (statut_envoi
 *                         = 'attente'), pas de table de file séparée.
 */
class Seliweb_Media_Database {

    const DB_VERSION     = '0.2';
    const DB_VERSION_KEY = 'seliwebmedia_db_version';

    public static function install() {
        $installed = get_option( self::DB_VERSION_KEY, '0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::create_tables();
            update_option( self::DB_VERSION_KEY, self::DB_VERSION );
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = array();

        $sql[] = "CREATE TABLE {$wpdb->prefix}seliwebmedia_listes (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom            VARCHAR(190) NOT NULL,
            description    TEXT,
            date_creation  DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}seliwebmedia_contacts (
            id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom                 VARCHAR(190) DEFAULT NULL,
            prenom              VARCHAR(190) DEFAULT NULL,
            code_postal         VARCHAR(20)  DEFAULT NULL,
            ville               VARCHAR(190) DEFAULT NULL,
            email               VARCHAR(190) NOT NULL,
            token_desabonnement VARCHAR(64)  NOT NULL,
            consentement_date   DATETIME     DEFAULT NULL,
            consentement_ip     VARCHAR(45)  DEFAULT NULL,
            date_creation       DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY token_desabonnement (token_desabonnement)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}seliwebmedia_abonnements (
            id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            liste_id            INT UNSIGNED NOT NULL,
            contact_id          INT UNSIGNED NOT NULL,
            statut              ENUM('actif','desabonne') NOT NULL DEFAULT 'actif',
            date_inscription    DATETIME NOT NULL,
            date_desabonnement  DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY liste_contact (liste_id, contact_id),
            KEY contact_id (contact_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}seliwebmedia_messages (
            id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sujet            VARCHAR(255) NOT NULL,
            corps            LONGTEXT,
            format           ENUM('texte','html') NOT NULL DEFAULT 'texte',
            statut           ENUM('brouillon','programme','en_cours','envoye') NOT NULL DEFAULT 'brouillon',
            date_creation    DATETIME NOT NULL,
            date_envoi_prevue DATETIME DEFAULT NULL,
            date_envoi_reel  DATETIME DEFAULT NULL,
            archive          TINYINT(1) NOT NULL DEFAULT 0,
            date_archivage   DATETIME DEFAULT NULL,
            duplique_de      INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY statut (statut),
            KEY archive (archive)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}seliwebmedia_message_cibles (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id  INT UNSIGNED NOT NULL,
            type        ENUM('liste','groupe_seliweb') NOT NULL,
            cible_id    INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY message_id (message_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}seliwebmedia_message_destinataires (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id      INT UNSIGNED NOT NULL,
            nom             VARCHAR(190) DEFAULT NULL,
            prenom          VARCHAR(190) DEFAULT NULL,
            code_postal     VARCHAR(20)  DEFAULT NULL,
            ville           VARCHAR(190) DEFAULT NULL,
            email           VARCHAR(190) NOT NULL,
            numero_membre   INT UNSIGNED DEFAULT NULL,
            token_desabonnement VARCHAR(64) DEFAULT NULL,
            statut_envoi    ENUM('attente','envoye','echec') NOT NULL DEFAULT 'attente',
            date_envoi      DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY statut_envoi (statut_envoi)
        ) $charset;";

        foreach ( $sql as $requete ) {
            dbDelta( $requete );
        }
    }
}
