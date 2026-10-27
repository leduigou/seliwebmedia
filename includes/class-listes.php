<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Écran "Listes mails" (listes + messages, en onglets) et tout ce qui
 * tourne autour des listes natives SeliwebMedia : création, inscription
 * publique (shortcode + consentement), désabonnement par lien.
 *
 * Les groupes Seliweb (si l'extension est active) apparaissent dans le
 * même écran mais ne sont pas stockés ici : voir
 * Seliweb_Media_Integration::recuperer_groupes().
 */
class Seliweb_Media_Listes {

    const SHORTCODE = 'seliwebmedia_inscription';

    public static function init() {
        add_action( 'admin_post_seliwebmedia_save_liste',    array( __CLASS__, 'handle_save_liste' ) );
        add_action( 'admin_post_seliwebmedia_supprimer_liste', array( __CLASS__, 'handle_supprimer_liste' ) );
        add_action( 'admin_post_seliwebmedia_ajouter_destinataire',  array( __CLASS__, 'handle_ajouter_destinataire' ) );
        add_action( 'admin_post_seliwebmedia_modifier_destinataire', array( __CLASS__, 'handle_modifier_destinataire' ) );
        add_action( 'admin_post_seliwebmedia_retirer_destinataire',  array( __CLASS__, 'handle_retirer_destinataire' ) );
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_formulaire_inscription' ) );
        add_action( 'init', array( __CLASS__, 'handle_inscription_post' ) );
        add_action( 'init', array( __CLASS__, 'handle_desabonnement' ) );
    }

    // ----------------------------------------------------------------
    // Écran principal : onglets Listes / Messages
    // ----------------------------------------------------------------
    public static function render() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'listes';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Listes de diffusion par mails', 'seliwebmedia' ) . '</h1>';

        echo '<nav class="nav-tab-wrapper">';
        $onglets = array(
            'listes'       => __( 'Listes', 'seliwebmedia' ),
            'messages'     => __( 'Messages', 'seliwebmedia' ),
            'inscriptions' => __( 'Inscriptions', 'seliwebmedia' ),
        );
        foreach ( $onglets as $key => $label ) {
            $url = admin_url( 'admin.php?page=seliwebmedia_listes&tab=' . $key );
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav><div style="margin-top:20px;">';

        if ( $tab === 'messages' ) {
            Seliweb_Media_Messages::tab_messages();
        } elseif ( $tab === 'inscriptions' ) {
            self::tab_inscriptions();
        } else {
            self::tab_listes();
        }

        echo '</div></div>';
    }

    private static function tab_listes() {
        global $wpdb;
        $tl = $wpdb->prefix . 'seliwebmedia_listes';
        $ta = $wpdb->prefix . 'seliwebmedia_abonnements';

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'voir' && ! empty( $_GET['id'] ) ) {
            self::voir_liste( intval( $_GET['id'] ) );
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'voir_groupe' && ! empty( $_GET['id'] ) ) {
            self::voir_groupe_seliweb( intval( $_GET['id'] ) );
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'new' ) {
            self::form_liste();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && ! empty( $_GET['id'] ) ) {
            self::form_liste( intval( $_GET['id'] ) );
            return;
        }

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Liste enregistrée.', 'seliwebmedia' ) . '</p></div>';
        }
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Liste supprimée.', 'seliwebmedia' ) . '</p></div>';
        }

        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=new' ) ) . '" class="button button-primary">'
            . esc_html__( '+ Nouvelle liste', 'seliwebmedia' ) . '</a></p>';

        $listes = $wpdb->get_results( "SELECT * FROM $tl ORDER BY nom ASC" );
        ?>
        <h2><?php esc_html_e( 'Listes SeliwebMedia', 'seliwebmedia' ); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th style="width:40px;"><?php esc_html_e( 'ID', 'seliwebmedia' ); ?></th>
                <th style="width:300px;"><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></th>
                <th style="width:300px;"><?php esc_html_e( 'Description', 'seliwebmedia' ); ?></th>
                <th style="width:80px;"><?php esc_html_e( 'Abonnés', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'seliwebmedia' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( ! $listes ) : ?>
                <tr><td colspan="5"><em><?php esc_html_e( 'Aucune liste pour le moment.', 'seliwebmedia' ); ?></em></td></tr>
            <?php else : foreach ( $listes as $l ) :
                $nb = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ta WHERE liste_id=%d AND statut='actif'", $l->id ) );
                ?>
                <tr>
                    <td><?php echo (int) $l->id; ?></td>
                    <td><strong><?php echo esc_html( $l->nom ); ?></strong></td>
                    <td><?php echo esc_html( $l->description ); ?></td>
                    <td><?php echo (int) $nb; ?></td>
                    <td style="white-space:nowrap;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=edit&id=' . $l->id ) ); ?>"><?php esc_html_e( 'Modifier', 'seliwebmedia' ); ?></a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seliwebmedia_supprimer_liste&id=' . $l->id ), 'seliwebmedia_supprimer_liste_' . $l->id ) ); ?>"
                           onclick="return confirm('<?php esc_attr_e( 'Supprimer cette liste et tous ses abonnements ?', 'seliwebmedia' ); ?>')"
                           style="color:#b32d2e;"><?php esc_html_e( 'Supprimer', 'seliwebmedia' ); ?></a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $l->id ) ); ?>"><?php esc_html_e( 'Destinataires', 'seliwebmedia' ); ?></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ( Seliweb_Media_Integration::est_disponible() ) :
            $groupes = Seliweb_Media_Integration::recuperer_groupes();
        ?>
        <h2 style="margin-top:28px;"><?php esc_html_e( 'Groupes Seliweb', 'seliwebmedia' ); ?></h2>
        <p class="description"><?php esc_html_e( "La liste des destinataires d'un groupe Seliweb est reconstituée au moment de l'envoi — rien n'est stocké ici à l'avance.", 'seliwebmedia' ); ?></p>
        <table class="wp-list-table widefat fixed striped" style="max-width:600px;">
            <thead><tr>
                <th><?php esc_html_e( 'Groupe', 'seliwebmedia' ); ?></th>
                <th style="width:90px;"><?php esc_html_e( 'Abonnés', 'seliwebmedia' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Actions', 'seliwebmedia' ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $groupes as $g ) :
                $nb_membres = count( Seliweb_Media_Integration::recuperer_membres_groupe( $g->id ) );
                ?>
                <tr>
                    <td><?php echo esc_html( $g->nom ); ?></td>
                    <td><?php echo (int) $nb_membres; ?></td>
                    <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir_groupe&id=' . $g->id ) ); ?>"><?php esc_html_e( 'Voir', 'seliwebmedia' ); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }

    // Onglet "Inscriptions" : shortcode complet (avec id) prêt à copier pour
    // chaque liste, à insérer dans un bloc Code court/Shortcode d'une page.
    private static function tab_inscriptions() {
        global $wpdb;
        $listes = $wpdb->get_results( "SELECT id, nom FROM {$wpdb->prefix}seliwebmedia_listes ORDER BY nom ASC" );
        ?>
        <p style="font-size:16px;max-width:800px;">
            <?php esc_html_e( "Pour proposer l'inscription à une liste spécifique sur une page du site, copiez le « Code court »/« Shortcode » et insérez-le dans un bloc « Code court »/« Shortcode » de la page.", 'seliwebmedia' ); ?>
            <br>
            <?php esc_html_e( "Le désabonnement n'a besoin d'aucune page particulière : chaque mail envoyé à une liste contient automatiquement son propre lien de désabonnement.", 'seliwebmedia' ); ?>
        </p>

        <table class="wp-list-table widefat fixed striped" style="max-width:1100px;">
            <thead><tr>
                <th style="width:50px;"><?php esc_html_e( 'ID', 'seliwebmedia' ); ?></th>
                <th style="width:250px;"><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'Shortcode', 'seliwebmedia' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'Actions', 'seliwebmedia' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( ! $listes ) : ?>
                <tr><td colspan="4"><em><?php esc_html_e( 'Aucune liste pour le moment.', 'seliwebmedia' ); ?></em></td></tr>
            <?php else : foreach ( $listes as $l ) :
                $shortcode = '[seliwebmedia_inscription liste="' . (int) $l->id . '"]';
                ?>
                <tr>
                    <td><?php echo (int) $l->id; ?></td>
                    <td><strong><?php echo esc_html( $l->nom ); ?></strong></td>
                    <td><code style="white-space:nowrap;"><?php echo esc_html( $shortcode ); ?></code></td>
                    <td>
                        <button type="button" class="button swm-copier-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>">
                            <?php esc_html_e( 'Copier le shortcode', 'seliwebmedia' ); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <p style="margin-top:24px;max-width:600px;">
            <strong><?php esc_html_e( 'Exemple de saisie de Shortcode – Code court dans une page WP', 'seliwebmedia' ); ?></strong><br>
            <img src="<?php echo esc_url( SELIWEBMEDIA_URL . 'assets/img/exemple-insertion-shortcode.png' ); ?>"
                 alt="<?php esc_attr_e( 'Exemple : bloc Code court contenant le shortcode d\'inscription', 'seliwebmedia' ); ?>"
                 style="max-width:600px;width:100%;height:auto;border:1px solid #ddd;margin-top:8px;">
        </p>

        <script>
        document.querySelectorAll('.swm-copier-shortcode').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                var texte = bouton.getAttribute('data-shortcode');
                var libelleInitial = bouton.textContent;
                function marquerCopie() {
                    bouton.textContent = '<?php echo esc_js( __( 'Copié !', 'seliwebmedia' ) ); ?>';
                    setTimeout(function () { bouton.textContent = libelleInitial; }, 1500);
                }
                if ( navigator.clipboard && navigator.clipboard.writeText ) {
                    navigator.clipboard.writeText(texte).then(marquerCopie);
                } else {
                    var temp = document.createElement('textarea');
                    temp.value = texte;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                    marquerCopie();
                }
            });
        });
        </script>
        <?php
    }

    // $liste_id > 0 : édition (pré-remplit nom/description) ; 0 : création.
    private static function form_liste( $liste_id = 0 ) {
        global $wpdb;
        $liste = $liste_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}seliwebmedia_listes WHERE id=%d", $liste_id ) ) : null;
        ?>
        <h2><?php echo $liste ? esc_html__( 'Modifier la liste', 'seliwebmedia' ) : esc_html__( 'Nouvelle liste', 'seliwebmedia' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:600px;">
            <?php wp_nonce_field( 'seliwebmedia_save_liste' ); ?>
            <input type="hidden" name="action" value="seliwebmedia_save_liste">
            <input type="hidden" name="liste_id" value="<?php echo (int) $liste_id; ?>">
            <table class="form-table">
                <tr>
                    <th><label for="swm_liste_nom"><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></label></th>
                    <td><input type="text" id="swm_liste_nom" name="nom" class="regular-text" required value="<?php echo esc_attr( $liste ? $liste->nom : '' ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="swm_liste_description"><?php esc_html_e( 'Description', 'seliwebmedia' ); ?></label></th>
                    <td><textarea id="swm_liste_description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $liste ? $liste->description : '' ); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button( $liste ? __( 'Enregistrer', 'seliwebmedia' ) : __( 'Créer la liste', 'seliwebmedia' ) ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes' ) ); ?>" class="button"><?php esc_html_e( 'Annuler', 'seliwebmedia' ); ?></a>
        </form>
        <?php
    }

    // Aperçu en lecture seule des membres ACTUELS d'un groupe Seliweb — pas
    // stocké, juste une photo instantanée pour inspection (la vraie liste
    // ne se construit qu'au moment de l'envoi, voir Seliweb_Media_Messages).
    private static function voir_groupe_seliweb( $groupe_id ) {
        $groupes = Seliweb_Media_Integration::recuperer_groupes();
        $nom = '';
        foreach ( $groupes as $g ) {
            if ( (int) $g->id === $groupe_id ) { $nom = $g->nom; break; }
        }
        $membres = Seliweb_Media_Integration::recuperer_membres_groupe( $groupe_id );
        ?>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes' ) ); ?>">&larr; <?php esc_html_e( 'Retour aux listes', 'seliwebmedia' ); ?></a></p>
        <h2><?php echo esc_html( $nom ?: __( 'Groupe Seliweb', 'seliwebmedia' ) ); ?></h2>
        <p class="description"><?php esc_html_e( "Aperçu des membres actuels de ce groupe — purement informatif : la liste réelle des destinataires sera reconstituée au moment de l'envoi d'un message.", 'seliwebmedia' ); ?></p>
        <table class="wp-list-table widefat fixed striped" style="max-width:600px;">
            <thead><tr><th><?php esc_html_e( 'Membre', 'seliwebmedia' ); ?></th><th><?php esc_html_e( 'Mail', 'seliwebmedia' ); ?></th></tr></thead>
            <tbody>
            <?php if ( ! $membres ) : ?>
                <tr><td colspan="2"><em><?php esc_html_e( 'Aucun membre dans ce groupe.', 'seliwebmedia' ); ?></em></td></tr>
            <?php else : foreach ( $membres as $m ) : ?>
                <tr><td><?php echo esc_html( $m->display_name ); ?></td><td><?php echo esc_html( $m->user_email ); ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    private static function voir_liste( $liste_id ) {
        global $wpdb;
        $tl = $wpdb->prefix . 'seliwebmedia_listes';
        $ta = $wpdb->prefix . 'seliwebmedia_abonnements';
        $tc = $wpdb->prefix . 'seliwebmedia_contacts';

        $liste = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tl WHERE id=%d", $liste_id ) );
        if ( ! $liste ) {
            echo '<p>' . esc_html__( 'Liste introuvable.', 'seliwebmedia' ) . '</p>';
            return;
        }

        $abonnes = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.id AS abonnement_id, c.id AS contact_id, c.nom, c.prenom, c.code_postal, c.ville, c.email
             FROM $ta a JOIN $tc c ON c.id = a.contact_id
             WHERE a.liste_id = %d AND a.statut = 'actif' ORDER BY c.nom ASC",
            $liste_id
        ) );
        ?>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes' ) ); ?>">&larr; <?php esc_html_e( 'Retour aux listes', 'seliwebmedia' ); ?></a></p>
        <h2><?php echo esc_html__( 'Destinataires de la liste : ', 'seliwebmedia' ) . esc_html( $liste->nom ); ?></h2>

        <?php if ( isset( $_GET['ajoute'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Destinataire ajouté.', 'seliwebmedia' ); ?></p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET['modifie'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Destinataire modifié.', 'seliwebmedia' ); ?></p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET['retire'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Destinataire retiré de la liste.', 'seliwebmedia' ); ?></p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET['erreur'] ) && $_GET['erreur'] === 'email_existant' ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Cette adresse mail est déjà utilisée par un autre contact.', 'seliwebmedia' ); ?></p></div>
        <?php endif; ?>

        <p>
            <button type="button" class="button button-primary" onclick="document.getElementById('swm_dialog_ajout').showModal();">
                <?php esc_html_e( '+ Ajouter un destinataire', 'seliwebmedia' ); ?>
            </button>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th style="width:50px;"><?php esc_html_e( 'Id', 'seliwebmedia' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'Prénom', 'seliwebmedia' ); ?></th>
                <th style="width:80px;"><?php esc_html_e( 'CP', 'seliwebmedia' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'Ville', 'seliwebmedia' ); ?></th>
                <th style="width:220px;"><?php esc_html_e( 'Mail', 'seliwebmedia' ); ?></th>
                <th style="width:160px;"><?php esc_html_e( 'Actions', 'seliwebmedia' ); ?></th>
            </tr></thead>
            <tbody>
            <?php
            $dialogues_modif = '';
            if ( ! $abonnes ) : ?>
                <tr><td colspan="7"><em><?php esc_html_e( 'Aucun abonné.', 'seliwebmedia' ); ?></em></td></tr>
            <?php else : foreach ( $abonnes as $a ) : ?>
                <tr>
                    <td><?php echo (int) $a->contact_id; ?></td>
                    <td><?php echo esc_html( $a->nom ); ?></td>
                    <td><?php echo esc_html( $a->prenom ); ?></td>
                    <td><?php echo esc_html( $a->code_postal ); ?></td>
                    <td><?php echo esc_html( $a->ville ); ?></td>
                    <td><?php echo esc_html( $a->email ); ?></td>
                    <td style="white-space:nowrap;">
                        <a href="#" onclick="document.getElementById('swm_dialog_modif_<?php echo (int) $a->contact_id; ?>').showModal();return false;">
                            <?php esc_html_e( 'Modifier', 'seliwebmedia' ); ?>
                        </a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'admin-post.php?action=seliwebmedia_retirer_destinataire&liste_id=' . $liste_id . '&contact_id=' . $a->contact_id ),
                            'seliwebmedia_retirer_destinataire_' . $liste_id . '_' . $a->contact_id
                        ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Retirer ce destinataire de la liste ?', 'seliwebmedia' ); ?>')" style="color:#b32d2e;">
                            <?php esc_html_e( 'Retirer', 'seliwebmedia' ); ?>
                        </a>
                    </td>
                </tr>
                <?php
                // La fenêtre de modification ne peut pas être imbriquée dans
                // <tbody> (HTML invalide, casse la mise en page) : on la
                // met de côté pour l'afficher après la fermeture du tableau.
                ob_start();
                ?>
                <dialog id="swm_dialog_modif_<?php echo (int) $a->contact_id; ?>">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'seliwebmedia_modifier_destinataire_' . $a->contact_id ); ?>
                        <input type="hidden" name="action" value="seliwebmedia_modifier_destinataire">
                        <input type="hidden" name="liste_id" value="<?php echo (int) $liste_id; ?>">
                        <input type="hidden" name="contact_id" value="<?php echo (int) $a->contact_id; ?>">
                        <table class="form-table">
                            <tr><th><label><?php esc_html_e( 'Prénom', 'seliwebmedia' ); ?></label></th>
                                <td><input type="text" name="prenom" class="regular-text" value="<?php echo esc_attr( $a->prenom ); ?>"></td></tr>
                            <tr><th><label><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></label></th>
                                <td><input type="text" name="nom" class="regular-text" value="<?php echo esc_attr( $a->nom ); ?>"></td></tr>
                            <tr><th><label><?php esc_html_e( 'Code postal', 'seliwebmedia' ); ?></label></th>
                                <td><input type="text" name="code_postal" class="regular-text" value="<?php echo esc_attr( $a->code_postal ); ?>"></td></tr>
                            <tr><th><label><?php esc_html_e( 'Ville', 'seliwebmedia' ); ?></label></th>
                                <td><input type="text" name="ville" class="regular-text" value="<?php echo esc_attr( $a->ville ); ?>"></td></tr>
                            <tr><th><label><?php esc_html_e( 'Email', 'seliwebmedia' ); ?></label></th>
                                <td><input type="email" name="email" class="regular-text" required value="<?php echo esc_attr( $a->email ); ?>"></td></tr>
                        </table>
                        <?php submit_button( __( 'Enregistrer', 'seliwebmedia' ), 'primary', 'submit', false ); ?>
                        <button type="button" class="button" onclick="document.getElementById('swm_dialog_modif_<?php echo (int) $a->contact_id; ?>').close();">
                            <?php esc_html_e( 'Annuler', 'seliwebmedia' ); ?>
                        </button>
                    </form>
                </dialog>
                <?php
                $dialogues_modif .= ob_get_clean();
            endforeach; endif; ?>
            </tbody>
        </table>
        <?php echo $dialogues_modif; ?>

        <dialog id="swm_dialog_ajout">
            <h3 style="margin-top:0;"><?php esc_html_e( 'Ajouter un destinataire', 'seliwebmedia' ); ?></h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'seliwebmedia_ajouter_destinataire_' . $liste_id ); ?>
                <input type="hidden" name="action" value="seliwebmedia_ajouter_destinataire">
                <input type="hidden" name="liste_id" value="<?php echo (int) $liste_id; ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="swm_dest_prenom"><?php esc_html_e( 'Prénom', 'seliwebmedia' ); ?></label></th>
                        <td><input type="text" id="swm_dest_prenom" name="prenom" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="swm_dest_nom"><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></label></th>
                        <td><input type="text" id="swm_dest_nom" name="nom" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="swm_dest_cp"><?php esc_html_e( 'Code postal', 'seliwebmedia' ); ?></label></th>
                        <td><input type="text" id="swm_dest_cp" name="code_postal" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="swm_dest_ville"><?php esc_html_e( 'Ville', 'seliwebmedia' ); ?></label></th>
                        <td><input type="text" id="swm_dest_ville" name="ville" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="swm_dest_email"><?php esc_html_e( 'Email', 'seliwebmedia' ); ?></label></th>
                        <td><input type="email" id="swm_dest_email" name="email" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Ajouter', 'seliwebmedia' ), 'primary', 'submit', false ); ?>
                <button type="button" class="button" onclick="document.getElementById('swm_dialog_ajout').close();">
                    <?php esc_html_e( 'Annuler', 'seliwebmedia' ); ?>
                </button>
            </form>
        </dialog>
        <?php
    }

    public static function handle_save_liste() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        check_admin_referer( 'seliwebmedia_save_liste' );

        global $wpdb;
        $liste_id = intval( $_POST['liste_id'] ?? 0 );
        $nom = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
        if ( $nom === '' ) {
            $retour = $liste_id ? '&action=edit&id=' . $liste_id : '&action=new';
            wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes' . $retour ) );
            exit;
        }

        $donnees = array(
            'nom'         => $nom,
            'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
        );

        if ( $liste_id ) {
            $wpdb->update( $wpdb->prefix . 'seliwebmedia_listes', $donnees, array( 'id' => $liste_id ) );
        } else {
            $donnees['date_creation'] = current_time( 'mysql' );
            $wpdb->insert( $wpdb->prefix . 'seliwebmedia_listes', $donnees );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&updated=1' ) );
        exit;
    }

    // Ajout manuel d'un destinataire à une liste, depuis l'admin (en plus
    // du formulaire public d'inscription). Pas de case de consentement :
    // action administrative explicite, pas une inscription du contact
    // lui-même — on l'enregistre quand même avec un repère distinct
    // (consentement_ip = 'admin') pour le distinguer dans les données.
    public static function handle_ajouter_destinataire() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        $liste_id = intval( $_POST['liste_id'] ?? 0 );
        check_admin_referer( 'seliwebmedia_ajouter_destinataire_' . $liste_id );

        $prenom = sanitize_text_field( wp_unslash( $_POST['prenom'] ?? '' ) );
        $nom    = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
        $cp     = sanitize_text_field( wp_unslash( $_POST['code_postal'] ?? '' ) );
        $ville  = sanitize_text_field( wp_unslash( $_POST['ville'] ?? '' ) );
        $email  = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( ! $liste_id || ! is_email( $email ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $liste_id ) );
            exit;
        }

        global $wpdb;
        $tc = $wpdb->prefix . 'seliwebmedia_contacts';
        $ta = $wpdb->prefix . 'seliwebmedia_abonnements';
        $maintenant = current_time( 'mysql' );

        $contact_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tc WHERE email=%s", $email ) );
        if ( $contact_id ) {
            $wpdb->update( $tc, array( 'nom' => $nom, 'prenom' => $prenom, 'code_postal' => $cp, 'ville' => $ville ), array( 'id' => $contact_id ) );
        } else {
            $wpdb->insert( $tc, array(
                'nom' => $nom, 'prenom' => $prenom, 'code_postal' => $cp, 'ville' => $ville,
                'email' => $email,
                'token_desabonnement' => bin2hex( random_bytes( 16 ) ),
                'consentement_date'   => $maintenant,
                'consentement_ip'     => 'admin',
                'date_creation'       => $maintenant,
            ) );
            $contact_id = (int) $wpdb->insert_id;
        }

        $existant = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $ta WHERE liste_id=%d AND contact_id=%d", $liste_id, $contact_id ) );
        if ( $existant ) {
            $wpdb->update( $ta, array( 'statut' => 'actif', 'date_inscription' => $maintenant, 'date_desabonnement' => null ), array( 'id' => $existant->id ) );
        } else {
            $wpdb->insert( $ta, array( 'liste_id' => $liste_id, 'contact_id' => $contact_id, 'statut' => 'actif', 'date_inscription' => $maintenant ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $liste_id . '&ajoute=1' ) );
        exit;
    }

    // Modifie les coordonnées d'un contact existant. L'email reste la clé
    // unique des contacts : si la nouvelle adresse appartient déjà à un
    // autre contact, on refuse plutôt que de fusionner silencieusement.
    public static function handle_modifier_destinataire() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        $contact_id = intval( $_POST['contact_id'] ?? 0 );
        check_admin_referer( 'seliwebmedia_modifier_destinataire_' . $contact_id );

        $liste_id = intval( $_POST['liste_id'] ?? 0 );
        $prenom   = sanitize_text_field( wp_unslash( $_POST['prenom'] ?? '' ) );
        $nom      = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
        $cp       = sanitize_text_field( wp_unslash( $_POST['code_postal'] ?? '' ) );
        $ville    = sanitize_text_field( wp_unslash( $_POST['ville'] ?? '' ) );
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( ! $contact_id || ! is_email( $email ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $liste_id ) );
            exit;
        }

        global $wpdb;
        $tc = $wpdb->prefix . 'seliwebmedia_contacts';
        $autre = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tc WHERE email=%s AND id!=%d", $email, $contact_id ) );
        if ( $autre ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $liste_id . '&erreur=email_existant' ) );
            exit;
        }

        $wpdb->update( $tc, array(
            'nom' => $nom, 'prenom' => $prenom, 'code_postal' => $cp, 'ville' => $ville, 'email' => $email,
        ), array( 'id' => $contact_id ) );

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $liste_id . '&modifie=1' ) );
        exit;
    }

    // Retire un contact d'une liste (désabonnement administratif) — ne
    // supprime pas le contact lui-même, seulement son abonnement à CETTE liste.
    public static function handle_retirer_destinataire() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        $liste_id   = intval( $_GET['liste_id'] ?? 0 );
        $contact_id = intval( $_GET['contact_id'] ?? 0 );
        check_admin_referer( 'seliwebmedia_retirer_destinataire_' . $liste_id . '_' . $contact_id );

        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'seliwebmedia_abonnements',
            array( 'statut' => 'desabonne', 'date_desabonnement' => current_time( 'mysql' ) ),
            array( 'liste_id' => $liste_id, 'contact_id' => $contact_id )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&action=voir&id=' . $liste_id . '&retire=1' ) );
        exit;
    }

    public static function handle_supprimer_liste() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        $id = intval( $_GET['id'] ?? 0 );
        check_admin_referer( 'seliwebmedia_supprimer_liste_' . $id );

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'seliwebmedia_abonnements', array( 'liste_id' => $id ) );
        $wpdb->delete( $wpdb->prefix . 'seliwebmedia_listes', array( 'id' => $id ) );

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=listes&deleted=1' ) );
        exit;
    }

    // ----------------------------------------------------------------
    // Formulaire public d'inscription — [seliwebmedia_inscription liste="ID"]
    // ----------------------------------------------------------------
    public static function render_formulaire_inscription( $atts ) {
        $atts = shortcode_atts( array( 'liste' => 0 ), $atts );
        $liste_id = intval( $atts['liste'] );
        if ( ! $liste_id ) {
            return '<p><em>' . esc_html__( 'Formulaire SeliwebMedia : identifiant de liste manquant.', 'seliwebmedia' ) . '</em></p>';
        }

        $etat = isset( $_GET['swm_inscription'] ) ? sanitize_key( $_GET['swm_inscription'] ) : '';

        ob_start();
        if ( $etat === 'ok' ) {
            echo '<p>' . esc_html__( 'Merci, votre inscription est confirmée.', 'seliwebmedia' ) . '</p>';
            return ob_get_clean();
        }
        ?>
        <form method="post">
            <?php wp_nonce_field( 'seliwebmedia_inscription' ); ?>
            <input type="hidden" name="seliwebmedia_action" value="inscription">
            <input type="hidden" name="liste_id" value="<?php echo (int) $liste_id; ?>">
            <p>
                <label><?php esc_html_e( 'Prénom', 'seliwebmedia' ); ?><br>
                    <input type="text" name="prenom" required></label>
            </p>
            <p>
                <label><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?><br>
                    <input type="text" name="nom" required></label>
            </p>
            <p>
                <span style="display:inline-block;margin-right:16px;">
                    <label><?php esc_html_e( 'Code postal', 'seliwebmedia' ); ?><br>
                        <input type="text" name="code_postal" style="box-sizing:border-box;width:100px;"></label>
                </span>
                <span style="display:inline-block;">
                    <label><?php esc_html_e( 'Ville', 'seliwebmedia' ); ?><br>
                        <input type="text" name="ville" style="box-sizing:border-box;width:220px;"></label>
                </span>
            </p>
            <p>
                <label><?php esc_html_e( 'Email', 'seliwebmedia' ); ?><br>
                    <input type="email" name="email" required style="box-sizing:border-box;width:336px;"></label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="consentement" value="1" required>
                    <?php echo esc_html( Seliweb_Media_Parametres::texte_consentement() ); ?>
                </label>
            </p>
            <?php if ( $etat === 'erreur' ) : ?>
                <p style="color:#b32d2e;"><?php esc_html_e( "Merci de vérifier les champs obligatoires et le consentement.", 'seliwebmedia' ); ?></p>
            <?php endif; ?>
            <p><button type="submit"><?php esc_html_e( "S'inscrire", 'seliwebmedia' ); ?></button></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handle_inscription_post() {
        if ( is_admin() ) return;
        if ( ! isset( $_POST['seliwebmedia_action'] ) || $_POST['seliwebmedia_action'] !== 'inscription' ) return;
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'seliwebmedia_inscription' ) ) return;

        $retour = wp_get_raw_referer() ?: home_url( '/' );

        $liste_id = intval( $_POST['liste_id'] ?? 0 );
        $prenom   = sanitize_text_field( wp_unslash( $_POST['prenom'] ?? '' ) );
        $nom      = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
        $cp       = sanitize_text_field( wp_unslash( $_POST['code_postal'] ?? '' ) );
        $ville    = sanitize_text_field( wp_unslash( $_POST['ville'] ?? '' ) );
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $consentement = ! empty( $_POST['consentement'] );

        if ( ! $liste_id || ! $prenom || ! $nom || ! is_email( $email ) || ! $consentement ) {
            wp_safe_redirect( add_query_arg( 'swm_inscription', 'erreur', $retour ) );
            exit;
        }

        global $wpdb;
        $tc = $wpdb->prefix . 'seliwebmedia_contacts';
        $ta = $wpdb->prefix . 'seliwebmedia_abonnements';

        $contact_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tc WHERE email=%s", $email ) );
        $maintenant = current_time( 'mysql' );

        if ( $contact_id ) {
            $wpdb->update( $tc, array(
                'nom' => $nom, 'prenom' => $prenom, 'code_postal' => $cp, 'ville' => $ville,
            ), array( 'id' => $contact_id ) );
        } else {
            $wpdb->insert( $tc, array(
                'nom' => $nom, 'prenom' => $prenom, 'code_postal' => $cp, 'ville' => $ville,
                'email' => $email,
                'token_desabonnement' => bin2hex( random_bytes( 16 ) ),
                'consentement_date'   => $maintenant,
                'consentement_ip'     => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
                'date_creation'       => $maintenant,
            ) );
            $contact_id = (int) $wpdb->insert_id;
        }

        $existant = $wpdb->get_row( $wpdb->prepare( "SELECT id, statut FROM $ta WHERE liste_id=%d AND contact_id=%d", $liste_id, $contact_id ) );
        if ( $existant ) {
            $wpdb->update( $ta, array( 'statut' => 'actif', 'date_inscription' => $maintenant, 'date_desabonnement' => null ), array( 'id' => $existant->id ) );
        } else {
            $wpdb->insert( $ta, array(
                'liste_id' => $liste_id, 'contact_id' => $contact_id,
                'statut' => 'actif', 'date_inscription' => $maintenant,
            ) );
        }

        wp_safe_redirect( add_query_arg( 'swm_inscription', 'ok', $retour ) );
        exit;
    }

    // ----------------------------------------------------------------
    // Désabonnement en un clic — lien envoyé dans chaque message, jamais
    // d'authentification requise (token aléatoire propre au contact).
    // ----------------------------------------------------------------
    public static function handle_desabonnement() {
        if ( is_admin() ) return;
        if ( empty( $_GET['swm_desabonner'] ) ) return;

        $token = sanitize_text_field( wp_unslash( $_GET['swm_desabonner'] ) );
        global $wpdb;
        $tc = $wpdb->prefix . 'seliwebmedia_contacts';
        $ta = $wpdb->prefix . 'seliwebmedia_abonnements';

        $contact_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tc WHERE token_desabonnement=%s", $token ) );
        if ( ! $contact_id ) {
            wp_die( esc_html__( 'Lien de désabonnement invalide.', 'seliwebmedia' ), '', array( 'response' => 404 ) );
        }

        $liste_id = intval( $_GET['liste'] ?? 0 );
        $maintenant = current_time( 'mysql' );
        if ( $liste_id ) {
            $wpdb->update( $ta, array( 'statut' => 'desabonne', 'date_desabonnement' => $maintenant ), array( 'liste_id' => $liste_id, 'contact_id' => $contact_id ) );
        } else {
            $wpdb->update( $ta, array( 'statut' => 'desabonne', 'date_desabonnement' => $maintenant ), array( 'contact_id' => $contact_id ) );
        }

        wp_die(
            esc_html__( 'Vous avez bien été désabonné.', 'seliwebmedia' ),
            esc_html__( 'Désabonnement', 'seliwebmedia' ),
            array( 'response' => 200 )
        );
    }
}
