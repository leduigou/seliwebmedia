<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pont avec l'extension Seliweb-WP — dépendance douce, jamais obligatoire.
 *
 * SeliwebMedia ne lit jamais directement les tables de Seliweb : il passe
 * par les filtres que Seliweb expose lui-même (seliweb_get_groupes,
 * seliweb_get_membres_groupe — voir includes/class-groupes.php côté
 * Seliweb). Si la structure des tables Seliweb change un jour, seul le
 * code de Seliweb a besoin d'être ajusté, pas celui-ci.
 */
class Seliweb_Media_Integration {

    public static function est_disponible() {
        return defined( 'SELIWEB_VERSION' );
    }

    // Tableau d'objets {id, nom} — vide si Seliweb n'est pas actif.
    public static function recuperer_groupes() {
        if ( ! self::est_disponible() ) return array();
        return apply_filters( 'seliweb_get_groupes', array() );
    }

    // Tableau d'objets {id, display_name, user_email} — vide si Seliweb
    // n'est pas actif ou si le groupe n'existe pas/est vide.
    public static function recuperer_membres_groupe( $groupe_id ) {
        if ( ! self::est_disponible() ) return array();
        return apply_filters( 'seliweb_get_membres_groupe', array(), intval( $groupe_id ) );
    }
}
