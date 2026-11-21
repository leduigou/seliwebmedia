<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Droits d'accès à SeliwebMedia — même mécanique que les Gestionnaires de
 * Seliweb, mais indépendante : les gestionnaires sont des comptes
 * WordPress, reliés ou non à Seliweb (un site peut utiliser SeliwebMedia
 * sans Seliweb).
 *
 * - Administrateurs (administrateur WordPress, et Administrateur Seliweb
 *   quand Seliweb est installé) : tous les droits, Paramétrage compris, et
 *   ce sont eux qui donnent les droits (Paramétrage → Gestionnaires).
 * - Gestionnaires : un niveau par module, hiérarchique — lecture <
 *   modification < création (la création emporte la suppression et, pour
 *   Mails, l'envoi des messages).
 *
 * Stockage : option seliwebmedia_droits = array( user_id => array( module => niveau ) ).
 */
class Seliweb_Media_Droits {

    const OPTION = 'seliwebmedia_droits';

    // Capacités techniques, données dynamiquement (jamais stockées).
    const CAP_ACCES  = 'seliwebmedia_acces';      // menu SeliwebMedia (au moins un droit)
    const CAP_ADMIN  = 'seliwebmedia_administrer'; // Paramétrage, Gestionnaires, tous les droits

    const NIVEAUX = array( 'lecture' => 1, 'modification' => 2, 'creation' => 3 );

    public static function init() {
        add_filter( 'user_has_cap', array( __CLASS__, 'filtrer_cap' ), 10, 4 );
        add_action( 'admin_notices', array( __CLASS__, 'afficher_bandeau_niveau' ) );
        add_action( 'admin_post_seliwebmedia_save_gestionnaires', array( __CLASS__, 'handle_save' ) );
        add_action( 'deleted_user', array( __CLASS__, 'oublier_compte' ) );
    }

    // Compte supprimé : ses droits disparaissent avec lui.
    public static function oublier_compte( $user_id ) {
        $droits = self::tous_les_droits();
        if ( isset( $droits[ $user_id ] ) ) {
            unset( $droits[ $user_id ] );
            update_option( self::OPTION, $droits, false );
        }
    }

    public static function modules() {
        return array(
            'mails'      => __( 'Mails', 'seliwebmedia' ),
            'sms'        => __( 'SMS', 'seliwebmedia' ),        // module à venir
            'newsletter' => __( 'Newsletter', 'seliwebmedia' ), // module à venir
        );
    }

    // Écran (page admin) -> module.
    private static function module_de_la_page() {
        $map = array( 'seliwebmedia_listes' => 'mails' );
        return $map[ sanitize_key( $_GET['page'] ?? '' ) ] ?? '';
    }

    public static function filtrer_cap( $allcaps, $caps, $args, $user ) {
        $caps = (array) $caps;
        if ( ! array_intersect( $caps, array( self::CAP_ACCES, self::CAP_ADMIN ) ) ) return $allcaps;

        // Administrateur WordPress, ou Administrateur Seliweb (capacité
        // seliweb_administrer de l'extension Seliweb, stockée dans son rôle).
        if ( ! empty( $allcaps['manage_options'] ) || ! empty( $allcaps['seliweb_administrer'] ) ) {
            $allcaps[ self::CAP_ADMIN ] = true;
            $allcaps[ self::CAP_ACCES ] = true;
            return $allcaps;
        }
        if ( self::droits_de( (int) $user->ID ) ) {
            $allcaps[ self::CAP_ACCES ] = true;
        }
        return $allcaps;
    }

    public static function est_admin( $user_id = 0 ) {
        return $user_id ? user_can( $user_id, self::CAP_ADMIN ) : current_user_can( self::CAP_ADMIN );
    }

    public static function tous_les_droits() {
        $d = get_option( self::OPTION, array() );
        return is_array( $d ) ? $d : array();
    }

    public static function droits_de( $user_id ) {
        $tous = self::tous_les_droits();
        return isset( $tous[ $user_id ] ) && is_array( $tous[ $user_id ] ) ? $tous[ $user_id ] : array();
    }

    // Niveau (0 à 3) d'un compte sur un module. 0 = aucun droit.
    public static function niveau( $user_id, $module ) {
        if ( ! $user_id ) return 0;
        $d = self::droits_de( $user_id );
        return isset( $d[ $module ] ) ? (int) ( self::NIVEAUX[ $d[ $module ] ] ?? 0 ) : 0;
    }

    // $action : lire | modifier | creer | supprimer | envoyer.
    public static function peut( $module, $action = 'lire' ) {
        if ( self::est_admin() ) return true;
        $seuils = array( 'lire' => 1, 'modifier' => 2, 'creer' => 3, 'supprimer' => 3, 'envoyer' => 3 );
        return self::niveau( get_current_user_id(), $module ) >= ( $seuils[ $action ] ?? 99 );
    }

    // Pour les traitements (admin-post) : arrête net si le droit manque.
    public static function exiger( $module, $action ) {
        if ( ! self::peut( $module, $action ) ) {
            wp_die( esc_html__( "Accès refusé : vous n'avez pas les droits pour cette action.", 'seliwebmedia' ), '', array( 'response' => 403 ) );
        }
    }

    // Pour les écrans : affiche un refus poli et renvoie false.
    public static function verifier_acces( $module, $action = 'lire' ) {
        if ( self::peut( $module, $action ) ) return true;
        echo '<div class="wrap"><h1>' . esc_html__( 'Accès refusé', 'seliwebmedia' ) . '</h1><p>'
            . esc_html__( "Vous n'avez pas les droits pour accéder à cette page. Contactez l'administrateur du site si vous pensez qu'il s'agit d'une erreur.", 'seliwebmedia' )
            . '</p></div>';
        return false;
    }

    // Rappel du niveau en haut de l'écran pour un droit partiel (comme Seliweb).
    public static function afficher_bandeau_niveau() {
        if ( self::est_admin() ) return;
        $module = self::module_de_la_page();
        if ( ! $module ) return;
        $niveau = self::niveau( get_current_user_id(), $module );
        if ( $niveau === self::NIVEAUX['lecture'] ) {
            $texte = __( 'Vous êtes connecté en mode Lecture sur ce module : vous pouvez consulter, mais pas créer, modifier, envoyer ou supprimer.', 'seliwebmedia' );
        } elseif ( $niveau === self::NIVEAUX['modification'] ) {
            $texte = __( 'Vous êtes connecté en mode Modification sur ce module : vous pouvez modifier les listes, leurs destinataires et les brouillons, mais pas créer, envoyer ni supprimer.', 'seliwebmedia' );
        } else {
            return;
        }
        echo '<div class="notice notice-info"><p>' . esc_html( $texte ) . '</p></div>';
    }

    // ================================================================
    // ONGLET « GESTIONNAIRES » (Paramétrage)
    // ================================================================
    public static function tab_gestionnaires() {
        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Droits enregistrés.', 'seliwebmedia' ) . '</p></div>';
        }

        $droits  = self::tous_les_droits();
        $modules = self::modules();

        // Gestionnaires actuels (comptes encore existants).
        $gestionnaires = array();
        foreach ( array_keys( $droits ) as $uid ) {
            $u = get_userdata( (int) $uid );
            if ( $u ) $gestionnaires[] = $u;
        }
        usort( $gestionnaires, function ( $a, $b ) { return strcasecmp( $a->display_name, $b->display_name ); } );

        // Comptes proposés à l'ajout : tous, sauf les administrateurs (qui
        // ont déjà tous les droits) et les gestionnaires déjà listés.
        $proposes = array();
        $noms     = array();
        foreach ( get_users( array( 'orderby' => 'display_name', 'fields' => 'all' ) ) as $u ) {
            if ( isset( $droits[ $u->ID ] ) || self::est_admin( $u->ID ) ) continue;
            $nom = $u->display_name ?: $u->user_login;
            $proposes[] = array( 'id' => (int) $u->ID, 'label' => $u->ID . ' — ' . $nom . ' (' . $u->user_email . ')' );
            $noms[ $u->ID ] = $nom;
        }
        ?>
        <h2><?php esc_html_e( 'Gestionnaires', 'seliwebmedia' ); ?></h2>
        <p class="description" style="max-width:760px;">
            <?php esc_html_e( "Donnez à des comptes du site un accès à SeliwebMedia sans en faire des administrateurs. Trois niveaux par module, hiérarchiques : Création/suppression inclut Modification, qui inclut Lecture. Pour Mails, l'envoi d'un message demande le niveau Création/suppression. Les modules SMS et Newsletter ne sont pas encore développés : leurs droits serviront dès qu'ils le seront. Les administrateurs WordPress et les Administrateurs Seliweb ont tous les droits sans réglage, et ce Paramétrage leur reste réservé.", 'seliwebmedia' ); ?>
        </p>

        <p>
            <button type="button" class="button button-primary" id="swm-gest-toggle"><?php esc_html_e( '+ Ajouter un gestionnaire', 'seliwebmedia' ); ?></button>
        </p>
        <p id="swm-gest-picker" style="display:none;">
            <span id="swm_wrap_gest" style="position:relative;display:inline-block;min-width:320px;"></span>
            <button type="button" class="button" id="swm-gest-confirmer"><?php esc_html_e( 'Ajouter à la liste', 'seliwebmedia' ); ?></button>
        </p>
        <p id="swm-gest-vide" <?php echo $gestionnaires ? 'style="display:none;"' : ''; ?>>
            <em><?php esc_html_e( 'Aucun gestionnaire pour le moment. Utilisez « Ajouter un gestionnaire » ci-dessus.', 'seliwebmedia' ); ?></em>
        </p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'seliwebmedia_save_gestionnaires' ); ?>
            <input type="hidden" name="action" value="seliwebmedia_save_gestionnaires">
            <table class="wp-list-table widefat fixed striped" id="swm-gest-table" style="max-width:900px;<?php echo $gestionnaires ? '' : 'display:none;'; ?>">
                <thead><tr>
                    <th style="width:50px;"><?php esc_html_e( 'Id', 'seliwebmedia' ); ?></th>
                    <th><?php esc_html_e( 'Membre', 'seliwebmedia' ); ?></th>
                    <?php foreach ( $modules as $label ) : ?><th style="width:170px;"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody id="swm-gest-tbody">
                <?php foreach ( $gestionnaires as $u ) : ?>
                    <tr data-id="<?php echo (int) $u->ID; ?>">
                        <td><?php echo (int) $u->ID; ?></td>
                        <td><?php echo esc_html( $u->display_name ?: $u->user_login ); ?></td>
                        <?php foreach ( $modules as $mod => $label ) :
                            $courant = $droits[ $u->ID ][ $mod ] ?? ''; ?>
                            <td><?php self::select_niveau( (int) $u->ID, $mod, $courant ); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description" style="margin-top:10px;"><?php esc_html_e( 'Pour retirer un gestionnaire, remettez tous ses modules sur « Aucun » puis enregistrez : il disparaîtra de cette liste.', 'seliwebmedia' ); ?></p>
            <?php submit_button( __( 'Enregistrer les droits', 'seliwebmedia' ) ); ?>
        </form>
        <script>
        (function () {
            var modules  = <?php echo wp_json_encode( $modules ); ?>;
            var noms     = <?php echo wp_json_encode( $noms ); ?>;
            var proposes = <?php echo wp_json_encode( $proposes ); ?>;
            var niveaux  = [
                ['', <?php echo wp_json_encode( __( 'Aucun', 'seliwebmedia' ) ); ?>],
                ['lecture', <?php echo wp_json_encode( __( 'Lecture', 'seliwebmedia' ) ); ?>],
                ['modification', <?php echo wp_json_encode( __( 'Modification', 'seliwebmedia' ) ); ?>],
                ['creation', <?php echo wp_json_encode( __( 'Création/suppression', 'seliwebmedia' ) ); ?>]
            ];
            function ajouterLigne(id, nom) {
                var tbody = document.getElementById('swm-gest-tbody');
                if (tbody.querySelector('tr[data-id="' + id + '"]')) return;
                var tr = document.createElement('tr'); tr.dataset.id = id;
                [id, nom].forEach(function (t) { var td = document.createElement('td'); td.textContent = t; tr.appendChild(td); });
                Object.keys(modules).forEach(function (mod) {
                    var td = document.createElement('td'), sel = document.createElement('select');
                    sel.name = 'droits[' + id + '][' + mod + ']';
                    niveaux.forEach(function (n) { var o = document.createElement('option'); o.value = n[0]; o.textContent = n[1]; sel.appendChild(o); });
                    td.appendChild(sel); tr.appendChild(td);
                });
                tbody.appendChild(tr);
                document.getElementById('swm-gest-vide').style.display = 'none';
                document.getElementById('swm-gest-table').style.display = '';
            }
            // Recherche intelligente (même principe que Seliweb).
            var wrap = document.getElementById('swm_wrap_gest');
            var txt = document.createElement('input'); txt.type = 'text'; txt.autocomplete = 'off';
            txt.placeholder = <?php echo wp_json_encode( __( 'Taper un nom, un e-mail ou un Id…', 'seliwebmedia' ) ); ?>;
            txt.style.cssText = 'width:320px;padding:6px 8px;font-size:14px;border:1px solid #8c8f94;border-radius:3px;';
            var choisi = '';
            var list = document.createElement('ul'); list.hidden = true;
            list.style.cssText = 'position:absolute;top:100%;left:0;right:0;z-index:9999;background:#fff;border:1px solid #8c8f94;border-top:0;list-style:none;margin:0;padding:0;max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.18);';
            wrap.appendChild(txt); wrap.appendChild(list);
            function rendre() {
                var q = txt.value.toLowerCase().trim(); list.innerHTML = '';
                if (!q) { list.hidden = true; return; }
                var res = proposes.filter(function (i) { return i.label.toLowerCase().indexOf(q) !== -1; }).slice(0, 15);
                res.forEach(function (i) {
                    var li = document.createElement('li'); li.textContent = i.label;
                    li.style.cssText = 'padding:7px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f6f7f7;';
                    li.addEventListener('mousedown', function (e) { e.preventDefault(); txt.value = i.label; choisi = String(i.id); list.hidden = true; });
                    list.appendChild(li);
                });
                list.hidden = !res.length;
            }
            txt.addEventListener('input', function () { choisi = ''; rendre(); });
            txt.addEventListener('focus', rendre);
            txt.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; }, 200); });
            var picker = document.getElementById('swm-gest-picker');
            document.getElementById('swm-gest-toggle').addEventListener('click', function () {
                picker.style.display = picker.style.display === 'none' ? '' : 'none';
                if (picker.style.display === '') txt.focus();
            });
            document.getElementById('swm-gest-confirmer').addEventListener('click', function () {
                if (!choisi) { alert(<?php echo wp_json_encode( __( 'Choisissez un compte dans la liste proposée.', 'seliwebmedia' ) ); ?>); return; }
                ajouterLigne(choisi, noms[choisi] || ('#' + choisi));
                txt.value = ''; choisi = ''; picker.style.display = 'none';
            });
        })();
        </script>
        <?php
    }

    private static function select_niveau( $user_id, $module, $courant ) {
        echo '<select name="droits[' . (int) $user_id . '][' . esc_attr( $module ) . ']">';
        echo '<option value="">' . esc_html__( 'Aucun', 'seliwebmedia' ) . '</option>';
        foreach ( array(
            'lecture'      => __( 'Lecture', 'seliwebmedia' ),
            'modification' => __( 'Modification', 'seliwebmedia' ),
            'creation'     => __( 'Création/suppression', 'seliwebmedia' ),
        ) as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $courant, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    public static function handle_save() {
        if ( ! self::est_admin() ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ), '', array( 'response' => 403 ) );
        check_admin_referer( 'seliwebmedia_save_gestionnaires' );

        $modules = array_keys( self::modules() );
        $droits  = self::tous_les_droits();
        foreach ( (array) ( $_POST['droits'] ?? array() ) as $uid => $par_module ) {
            $uid = (int) $uid;
            if ( ! $uid || ! get_userdata( $uid ) ) continue;
            $ligne = array();
            foreach ( $modules as $mod ) {
                $niv = sanitize_key( $par_module[ $mod ] ?? '' );
                if ( isset( self::NIVEAUX[ $niv ] ) ) $ligne[ $mod ] = $niv;
            }
            if ( $ligne ) {
                $droits[ $uid ] = $ligne;
            } else {
                unset( $droits[ $uid ] ); // tout sur « Aucun » : retiré de la liste
            }
        }
        update_option( self::OPTION, $droits, false );
        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia&tab=gestionnaires&updated=1' ) );
        exit;
    }
}
