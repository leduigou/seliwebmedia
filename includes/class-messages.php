<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Onglet Messages (dans l'écran "Listes mails") : création, visualisation,
 * duplication, et construction des destinataires au moment de l'envoi.
 *
 * "Message" plutôt que "campagne" — terme réservé aux envois commerciaux,
 * pas à l'usage associatif de SeliwebMedia (choix de Philippe).
 */
class Seliweb_Media_Messages {

    public static function init() {
        add_action( 'admin_post_seliwebmedia_save_message',      array( __CLASS__, 'handle_save_message' ) );
        add_action( 'admin_post_seliwebmedia_supprimer_message',  array( __CLASS__, 'handle_supprimer_message' ) );
    }

    // ----------------------------------------------------------------
    // Écran
    // ----------------------------------------------------------------
    public static function tab_messages() {
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $id     = isset( $_GET['id'] )     ? intval( $_GET['id'] )           : 0;

        switch ( $action ) {
            case 'new':
                self::form_message();
                break;
            case 'edit':
                self::form_message( $id, false );
                break;
            case 'dupliquer':
                self::form_message( $id, true );
                break;
            case 'voir':
                self::voir_message( $id );
                break;
            default:
                self::liste_messages();
                break;
        }
    }

    private static function liste_messages() {
        global $wpdb;
        $tm = $wpdb->prefix . 'seliwebmedia_messages';

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Message enregistré.', 'seliwebmedia' ) . '</p></div>';
        }
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Message supprimé.', 'seliwebmedia' ) . '</p></div>';
        }

        $filtre = isset( $_GET['filtre'] ) && $_GET['filtre'] === 'archives' ? 'archives' : 'actifs';

        echo '<p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&action=new' ) ) . '" class="button button-primary">'
            . esc_html__( '+ Nouveau message', 'seliwebmedia' ) . '</a>';
        echo '</p>';

        echo '<ul class="subsubsub">';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&filtre=actifs' ) ) . '"' . ( $filtre === 'actifs' ? ' class="current"' : '' ) . '>' . esc_html__( 'Actifs', 'seliwebmedia' ) . '</a> |</li> ';
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&filtre=archives' ) ) . '"' . ( $filtre === 'archives' ? ' class="current"' : '' ) . '>' . esc_html__( 'Archivés', 'seliwebmedia' ) . '</a></li>';
        echo '</ul><br class="clear">';

        $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $tm WHERE archive=%d ORDER BY date_creation DESC",
            $filtre === 'archives' ? 1 : 0
        ) );

        $libelles_statut = array(
            'brouillon' => __( 'Brouillon', 'seliwebmedia' ),
            'programme' => __( 'Programmé', 'seliwebmedia' ),
            'en_cours'  => __( 'En cours d\'envoi', 'seliwebmedia' ),
            'envoye'    => __( 'Envoyé', 'seliwebmedia' ),
        );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th style="width:50px;"><?php esc_html_e( 'ID', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'Sujet', 'seliwebmedia' ); ?></th>
                <th style="width:140px;"><?php esc_html_e( 'Statut', 'seliwebmedia' ); ?></th>
                <th style="width:160px;"><?php esc_html_e( 'Créé le', 'seliwebmedia' ); ?></th>
                <th style="width:160px;"><?php esc_html_e( 'Envoi prévu', 'seliwebmedia' ); ?></th>
                <th style="width:220px;"><?php esc_html_e( 'Actions', 'seliwebmedia' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( ! $messages ) : ?>
                <tr><td colspan="6"><em><?php esc_html_e( 'Aucun message.', 'seliwebmedia' ); ?></em></td></tr>
            <?php else : foreach ( $messages as $m ) : ?>
                <tr>
                    <td><?php echo (int) $m->id; ?></td>
                    <td><strong><?php echo esc_html( $m->sujet ); ?></strong></td>
                    <td><?php echo esc_html( $libelles_statut[ $m->statut ] ?? $m->statut ); ?></td>
                    <td><?php echo esc_html( $m->date_creation ); ?></td>
                    <td><?php echo $m->date_envoi_prevue ? esc_html( $m->date_envoi_prevue ) : '—'; ?></td>
                    <td>
                        <?php $modifiable = in_array( $m->statut, array( 'brouillon', 'programme' ), true ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&action=' . ( $modifiable ? 'edit' : 'voir' ) . '&id=' . $m->id ) ); ?>"><?php echo $modifiable ? esc_html__( 'Modifier', 'seliwebmedia' ) : esc_html__( 'Voir', 'seliwebmedia' ); ?></a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&action=dupliquer&id=' . $m->id ) ); ?>"><?php esc_html_e( 'Dupliquer', 'seliwebmedia' ); ?></a>
                        <?php if ( $m->statut === 'brouillon' ) : ?>
                            &nbsp;|&nbsp;
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seliwebmedia_supprimer_message&id=' . $m->id ), 'seliwebmedia_supprimer_message_' . $m->id ) ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Supprimer ce message ?', 'seliwebmedia' ); ?>')"
                               style="color:#b32d2e;"><?php esc_html_e( 'Supprimer', 'seliwebmedia' ); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    private static function form_message( $message_id = 0, $dupliquer = false ) {
        if ( ! Seliweb_Media_Parametres::from_email() ) {
            ?>
            <h2><?php esc_html_e( 'Nouveau message', 'seliwebmedia' ); ?></h2>
            <div class="notice notice-warning" style="margin:16px 0;max-width:700px;">
                <p>
                    <?php esc_html_e( "Vous devez d'abord renseigner les paramètres.", 'seliwebmedia' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia&tab=mail' ) ); ?>">
                        <?php esc_html_e( "Aller à l'onglet Mails", 'seliwebmedia' ); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }

        global $wpdb;
        $tm  = $wpdb->prefix . 'seliwebmedia_messages';
        $tmc = $wpdb->prefix . 'seliwebmedia_message_cibles';

        $message        = null;
        $cibles_cochees = array();
        $sujet          = '';
        $format         = 'texte';
        $corps          = '';
        $date_envoi     = '';

        if ( $message_id ) {
            $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tm WHERE id=%d", $message_id ) );
            if ( ! $message || ( ! $dupliquer && ! in_array( $message->statut, array( 'brouillon', 'programme' ), true ) ) ) {
                echo '<p>' . esc_html__( 'Message introuvable ou non modifiable.', 'seliwebmedia' ) . '</p>';
                return;
            }

            $sujet  = $dupliquer ? $message->sujet . ' (' . __( 'copie', 'seliwebmedia' ) . ')' : $message->sujet;
            $format = $message->format;
            $corps  = $message->corps;
            // La date d'envoi n'est jamais reprise lors d'une duplication :
            // l'administrateur choisit une nouvelle date (ou l'envoi immédiat).
            if ( ! $dupliquer && $message->date_envoi_prevue ) {
                $date_envoi = str_replace( ' ', 'T', substr( $message->date_envoi_prevue, 0, 16 ) );
            }

            $cibles_existantes = $wpdb->get_results( $wpdb->prepare( "SELECT type, cible_id FROM $tmc WHERE message_id=%d", $message_id ) );
            foreach ( $cibles_existantes as $c ) {
                $cibles_cochees[] = ( $c->type === 'liste' ? 'liste_' : 'groupe_' ) . $c->cible_id;
            }
        }

        $titre = $dupliquer ? __( 'Dupliquer le message', 'seliwebmedia' ) : ( $message_id ? __( 'Modifier le message', 'seliwebmedia' ) : __( 'Nouveau message', 'seliwebmedia' ) );
        $confirmation_brouillon = ( $message && ! $dupliquer && $message->statut === 'programme' )
            ? esc_js( __( "Ce message est programmé : l'enregistrer en brouillon annule sa programmation. Continuer ?", 'seliwebmedia' ) )
            : '';
        ?>
        <h2><?php echo esc_html( $titre ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:800px;">
            <?php wp_nonce_field( 'seliwebmedia_save_message' ); ?>
            <input type="hidden" name="action" value="seliwebmedia_save_message">
            <?php if ( $message_id && ! $dupliquer ) : ?>
                <input type="hidden" name="message_id" value="<?php echo (int) $message_id; ?>">
            <?php elseif ( $message_id && $dupliquer ) : ?>
                <input type="hidden" name="duplique_de" value="<?php echo (int) $message_id; ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="swm_sujet"><?php esc_html_e( 'Sujet', 'seliwebmedia' ); ?></label></th>
                    <td><input type="text" id="swm_sujet" name="sujet" class="large-text" value="<?php echo esc_attr( $sujet ); ?>" required></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Destinataires', 'seliwebmedia' ); ?></th>
                    <td><?php self::afficher_cases_cibles( $cibles_cochees ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Format', 'seliwebmedia' ); ?></th>
                    <td>
                        <label><input type="radio" name="format" value="texte" <?php checked( $format, 'texte' ); ?> onclick="document.getElementById('swm_corps_texte').style.display='';document.getElementById('swm_corps_html').style.display='none';"> <?php esc_html_e( 'Texte brut', 'seliwebmedia' ); ?></label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="format" value="html" <?php checked( $format, 'html' ); ?> onclick="document.getElementById('swm_corps_texte').style.display='none';document.getElementById('swm_corps_html').style.display='';"> <?php esc_html_e( 'HTML (mise en forme)', 'seliwebmedia' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Variables disponibles : {prenom}, {nom}.', 'seliwebmedia' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="swm_corps_texte"><?php esc_html_e( 'Message', 'seliwebmedia' ); ?></label></th>
                    <td>
                        <div id="swm_corps_texte" <?php echo $format === 'html' ? 'style="display:none;"' : ''; ?>>
                            <textarea id="swm_corps_texte_champ" name="corps_texte" rows="10" class="large-text"><?php echo esc_textarea( $format === 'texte' ? $corps : '' ); ?></textarea>
                        </div>
                        <div id="swm_corps_html" <?php echo $format === 'html' ? '' : 'style="display:none;"'; ?>>
                            <?php
                            wp_editor( $format === 'html' ? $corps : '', 'swm_corps_html_champ', array(
                                'textarea_name' => 'corps_html',
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                            ) );
                            ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="swm_date_envoi"><?php esc_html_e( "Date d'envoi", 'seliwebmedia' ); ?></label></th>
                    <td>
                        <input type="datetime-local" id="swm_date_envoi" name="date_envoi_prevue" value="<?php echo esc_attr( $date_envoi ); ?>">
                        <p class="description"><?php esc_html_e( "Laisser vide pour un envoi immédiat dès l'enregistrement.", 'seliwebmedia' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Enregistrer brouillon', 'seliwebmedia' ), 'secondary', 'submit_action_brouillon', false, $confirmation_brouillon ? array( 'onclick' => "return confirm('{$confirmation_brouillon}')" ) : array() ); ?>
            &nbsp;
            <?php submit_button( __( 'Envoyer', 'seliwebmedia' ), 'primary', 'submit_action_envoyer', false ); ?>
            &nbsp;
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages' ) ); ?>" class="button"><?php esc_html_e( 'Annuler', 'seliwebmedia' ); ?></a>
        </form>
        <?php
    }

    // Cases à cocher listes SeliwebMedia + groupes Seliweb (si dispo),
    // avec le nombre de destinataires actuel de chacun·e.
    private static function afficher_cases_cibles( $cochees ) {
        global $wpdb;
        $ta     = $wpdb->prefix . 'seliwebmedia_abonnements';
        $listes = $wpdb->get_results( "SELECT id, nom FROM {$wpdb->prefix}seliwebmedia_listes ORDER BY nom ASC" );

        foreach ( $listes as $l ) {
            $checked = in_array( 'liste_' . $l->id, $cochees, true );
            $nb      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ta WHERE liste_id=%d AND statut='actif'", $l->id ) );
            echo '<label style="display:block;"><input type="checkbox" name="cibles[]" value="liste_' . (int) $l->id . '"' . ( $checked ? ' checked' : '' ) . '> ' . esc_html( $l->nom ) . ' (' . esc_html__( 'liste SeliwebMedia', 'seliwebmedia' ) . ', ' . sprintf( esc_html( _n( '%d destinataire', '%d destinataires', $nb, 'seliwebmedia' ) ), $nb ) . ')</label>';
        }

        if ( Seliweb_Media_Integration::est_disponible() ) {
            foreach ( Seliweb_Media_Integration::recuperer_groupes() as $g ) {
                $checked = in_array( 'groupe_' . $g->id, $cochees, true );
                $nb      = count( Seliweb_Media_Integration::recuperer_membres_groupe( $g->id ) );
                echo '<label style="display:block;"><input type="checkbox" name="cibles[]" value="groupe_' . (int) $g->id . '"' . ( $checked ? ' checked' : '' ) . '> ' . esc_html( $g->nom ) . ' (' . esc_html__( 'groupe Seliweb', 'seliwebmedia' ) . ', ' . sprintf( esc_html( _n( '%d destinataire', '%d destinataires', $nb, 'seliwebmedia' ) ), $nb ) . ')</label>';
            }
        }

        if ( ! $listes && ! Seliweb_Media_Integration::est_disponible() ) {
            echo '<p><em>' . esc_html__( 'Aucune liste créée pour le moment.', 'seliwebmedia' ) . '</em></p>';
        }
    }

    // Ajoute des marges en ligne de secours aux paragraphes/listes d'un
    // message HTML, sans jamais écraser un style déjà présent sur la
    // balise. Nécessaire car certains contextes d'affichage (clients mail
    // comme Gmail, certaines pages d'admin) n'appliquent pas les marges
    // par défaut du navigateur sur <p>/<ul>/<ol>/<li> — sans ce filet, la
    // mise en page composée dans l'éditeur (paragraphes séparés, listes
    // aérées) s'aplatit visuellement à l'affichage ou à la réception,
    // alors que le HTML stocké en base reste, lui, intact (ce qui explique
    // qu'un « Dupliquer », qui recharge ce même HTML dans l'éditeur, ne
    // montre pas le problème — l'éditeur applique ses propres styles).
    // Utilisée à la fois par voir_message() et par l'envoi réel
    // (Seliweb_Media_Mail_Queue::envoyer_destinataire()).
    public static function normaliser_corps_html( $html ) {
        if ( trim( (string) $html ) === '' ) {
            return $html;
        }

        $styles_par_balise = array(
            'p'  => 'margin:0 0 1em 0;',
            'ul' => 'margin:0 0 1em 0;padding-left:20px;',
            'ol' => 'margin:0 0 1em 0;padding-left:20px;',
            'li' => 'margin:0 0 0.5em 0;',
        );

        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div id="swm-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath  = new DOMXPath( $doc );
        $racine = $xpath->query( '//div[@id="swm-root"]' )->item( 0 );
        if ( ! $racine ) {
            return $html; // repli : contenu inchangé si le parsing a échoué
        }

        foreach ( $styles_par_balise as $balise => $style ) {
            foreach ( $doc->getElementsByTagName( $balise ) as $element ) {
                if ( ! $element->getAttribute( 'style' ) ) {
                    $element->setAttribute( 'style', $style );
                }
            }
        }

        $resultat = '';
        foreach ( $racine->childNodes as $enfant ) {
            $resultat .= $doc->saveHTML( $enfant );
        }
        return $resultat;
    }

    private static function voir_message( $id ) {
        global $wpdb;
        $tm = $wpdb->prefix . 'seliwebmedia_messages';
        $td = $wpdb->prefix . 'seliwebmedia_message_destinataires';

        $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tm WHERE id=%d", $id ) );
        if ( ! $message ) {
            echo '<p>' . esc_html__( 'Message introuvable.', 'seliwebmedia' ) . '</p>';
            return;
        }
        $destinataires = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $td WHERE message_id=%d ORDER BY id ASC", $id ) );
        ?>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages' ) ); ?>">&larr; <?php esc_html_e( 'Retour aux messages', 'seliwebmedia' ); ?></a></p>
        <h2><?php echo esc_html( $message->sujet ); ?></h2>
        <p>
            <strong><?php esc_html_e( 'Statut :', 'seliwebmedia' ); ?></strong> <?php echo esc_html( $message->statut ); ?>
            <?php if ( $message->date_envoi_prevue ) : ?> — <strong><?php esc_html_e( 'Envoi prévu :', 'seliwebmedia' ); ?></strong> <?php echo esc_html( $message->date_envoi_prevue ); ?><?php endif; ?>
        </p>
        <div style="background:#fff;border:1px solid #ddd;padding:16px;max-width:700px;margin:12px 0;">
            <?php echo $message->format === 'html' ? self::normaliser_corps_html( $message->corps ) : '<pre style="white-space:pre-wrap;font-family:inherit;">' . esc_html( $message->corps ) . '</pre>'; ?>
        </div>

        <h3><?php printf( esc_html__( 'Destinataires (%d)', 'seliwebmedia' ), count( $destinataires ) ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th><?php esc_html_e( 'Nom', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'Prénom', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'Mail', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'N° membre', 'seliwebmedia' ); ?></th>
                <th><?php esc_html_e( 'Statut envoi', 'seliwebmedia' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( ! $destinataires ) : ?>
                <tr><td colspan="5"><em><?php esc_html_e( 'Pas encore constitués (message pas encore envoyé).', 'seliwebmedia' ); ?></em></td></tr>
            <?php else : foreach ( $destinataires as $d ) : ?>
                <tr>
                    <td><?php echo esc_html( $d->nom ); ?></td>
                    <td><?php echo esc_html( $d->prenom ); ?></td>
                    <td><?php echo esc_html( $d->email ); ?></td>
                    <td><?php echo $d->numero_membre ? (int) $d->numero_membre : '—'; ?></td>
                    <td><?php echo esc_html( $d->statut_envoi ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    // ----------------------------------------------------------------
    // Enregistrement
    // ----------------------------------------------------------------
    public static function handle_save_message() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        check_admin_referer( 'seliwebmedia_save_message' );

        // Filet de sécurité serveur : le formulaire masque déjà la saisie
        // tant que l'adresse d'expéditeur n'est pas réglée (voir form_message()).
        if ( ! Seliweb_Media_Parametres::from_email() ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&action=new' ) );
            exit;
        }

        global $wpdb;
        $sujet  = sanitize_text_field( wp_unslash( $_POST['sujet'] ?? '' ) );
        $format = ( $_POST['format'] ?? 'texte' ) === 'html' ? 'html' : 'texte';
        $corps  = $format === 'html'
            ? wp_kses_post( wp_unslash( $_POST['corps_html'] ?? '' ) )
            : sanitize_textarea_field( wp_unslash( $_POST['corps_texte'] ?? '' ) );

        $date_envoi_prevue = sanitize_text_field( wp_unslash( $_POST['date_envoi_prevue'] ?? '' ) );
        $date_envoi_prevue = $date_envoi_prevue ? str_replace( 'T', ' ', $date_envoi_prevue ) . ':00' : null;

        if ( $sujet === '' ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&action=new' ) );
            exit;
        }

        // Bouton "Enregistrer brouillon" : repasse toujours en brouillon,
        // même si le message était déjà programmé (confirmation demandée
        // côté formulaire dans ce cas). Bouton "Envoyer" : programme ou
        // envoie immédiatement selon la date choisie.
        $en_brouillon = isset( $_POST['submit_action_brouillon'] );
        $statut       = $en_brouillon ? 'brouillon' : ( $date_envoi_prevue ? 'programme' : 'brouillon' );

        $tm         = $wpdb->prefix . 'seliwebmedia_messages';
        $message_id = intval( $_POST['message_id'] ?? 0 );
        $duplique_de = intval( $_POST['duplique_de'] ?? 0 );

        if ( $message_id ) {
            $wpdb->update( $tm, array(
                'sujet'             => $sujet,
                'corps'             => $corps,
                'format'            => $format,
                'statut'            => $statut,
                'date_envoi_prevue' => $en_brouillon ? null : $date_envoi_prevue,
            ), array( 'id' => $message_id ) );

            $tmc = $wpdb->prefix . 'seliwebmedia_message_cibles';
            $wpdb->delete( $tmc, array( 'message_id' => $message_id ) );
        } else {
            $wpdb->insert( $tm, array(
                'sujet'             => $sujet,
                'corps'             => $corps,
                'format'            => $format,
                'statut'            => $statut,
                'date_creation'     => current_time( 'mysql' ),
                'date_envoi_prevue' => $en_brouillon ? null : $date_envoi_prevue,
                'duplique_de'       => $duplique_de ?: null,
            ) );
            $message_id = (int) $wpdb->insert_id;
        }

        $cibles = array_map( 'sanitize_text_field', (array) ( $_POST['cibles'] ?? array() ) );
        self::enregistrer_cibles( $message_id, $cibles );

        // Envoi immédiat si le bouton "Envoyer" a été utilisé sans date choisie.
        if ( ! $en_brouillon && ! $date_envoi_prevue ) {
            Seliweb_Media_Mail_Queue::expedier_message( $message_id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&updated=1' ) );
        exit;
    }

    private static function enregistrer_cibles( $message_id, $cibles ) {
        global $wpdb;
        $tmc = $wpdb->prefix . 'seliwebmedia_message_cibles';
        foreach ( $cibles as $cible ) {
            if ( strpos( $cible, 'liste_' ) === 0 ) {
                $wpdb->insert( $tmc, array( 'message_id' => $message_id, 'type' => 'liste', 'cible_id' => intval( substr( $cible, 6 ) ) ) );
            } elseif ( strpos( $cible, 'groupe_' ) === 0 ) {
                $wpdb->insert( $tmc, array( 'message_id' => $message_id, 'type' => 'groupe_seliweb', 'cible_id' => intval( substr( $cible, 7 ) ) ) );
            }
        }
    }

    public static function handle_supprimer_message() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        $id = intval( $_GET['id'] ?? 0 );
        check_admin_referer( 'seliwebmedia_supprimer_message_' . $id );

        global $wpdb;
        $tm = $wpdb->prefix . 'seliwebmedia_messages';
        // Par sécurité, suppression manuelle réservée aux brouillons (un
        // message déjà programmé/envoyé se gère via l'archivage/la purge).
        $statut = $wpdb->get_var( $wpdb->prepare( "SELECT statut FROM $tm WHERE id=%d", $id ) );
        if ( $statut === 'brouillon' ) {
            $wpdb->delete( $wpdb->prefix . 'seliwebmedia_message_cibles', array( 'message_id' => $id ) );
            $wpdb->delete( $tm, array( 'id' => $id ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia_listes&tab=messages&deleted=1' ) );
        exit;
    }

    // ----------------------------------------------------------------
    // Construction des destinataires d'un message — appelée par
    // Seliweb_Media_Mail_Queue::expedier_message() au moment de l'envoi
    // (immédiat ou à l'heure programmée), jamais avant. Retourne le
    // nombre de destinataires retenus (après déduplication).
    // ----------------------------------------------------------------
    public static function construire_destinataires( $message_id ) {
        global $wpdb;
        $tmc = $wpdb->prefix . 'seliwebmedia_message_cibles';
        $ta  = $wpdb->prefix . 'seliwebmedia_abonnements';
        $tc  = $wpdb->prefix . 'seliwebmedia_contacts';
        $td  = $wpdb->prefix . 'seliwebmedia_message_destinataires';

        $cibles = $wpdb->get_results( $wpdb->prepare( "SELECT type, cible_id FROM $tmc WHERE message_id=%d", $message_id ) );

        $par_email = array(); // dédoublonnage — dernière occurrence gagne (peu importe laquelle, champs identiques)
        foreach ( $cibles as $cible ) {
            if ( $cible->type === 'liste' ) {
                $abonnes = $wpdb->get_results( $wpdb->prepare(
                    "SELECT c.nom, c.prenom, c.code_postal, c.ville, c.email, c.token_desabonnement
                     FROM $ta a JOIN $tc c ON c.id = a.contact_id
                     WHERE a.liste_id=%d AND a.statut='actif'",
                    $cible->cible_id
                ) );
                foreach ( $abonnes as $a ) {
                    $par_email[ strtolower( $a->email ) ] = array(
                        'nom' => $a->nom, 'prenom' => $a->prenom, 'code_postal' => $a->code_postal,
                        'ville' => $a->ville, 'email' => $a->email, 'numero_membre' => null,
                        'token_desabonnement' => $a->token_desabonnement,
                    );
                }
            } elseif ( $cible->type === 'groupe_seliweb' ) {
                $membres = Seliweb_Media_Integration::recuperer_membres_groupe( $cible->cible_id );
                foreach ( $membres as $m ) {
                    if ( empty( $m->user_email ) ) continue;
                    $nom_complet = (string) $m->display_name;
                    $par_email[ strtolower( $m->user_email ) ] = array(
                        'nom' => $nom_complet, 'prenom' => '', 'code_postal' => '', 'ville' => '',
                        'email' => $m->user_email, 'numero_membre' => isset( $m->id ) ? intval( $m->id ) : null,
                        'token_desabonnement' => null, // pas de désabonnement mail : géré via les préférences Seliweb
                    );
                }
            }
        }

        $maintenant = current_time( 'mysql' );
        foreach ( $par_email as $destinataire ) {
            $wpdb->insert( $td, array(
                'message_id'          => $message_id,
                'nom'                 => $destinataire['nom'],
                'prenom'              => $destinataire['prenom'],
                'code_postal'         => $destinataire['code_postal'],
                'ville'               => $destinataire['ville'],
                'email'               => $destinataire['email'],
                'numero_membre'       => $destinataire['numero_membre'],
                'token_desabonnement' => $destinataire['token_desabonnement'],
                'statut_envoi'        => 'attente',
            ) );
        }

        return count( $par_email );
    }
}
