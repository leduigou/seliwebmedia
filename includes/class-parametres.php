<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Écrans d'administration SeliwebMedia. Un seul menu pour l'instant
 * (Tableau de bord + onglet Licence) ; les écrans Listes de diffusion,
 * Newsletters et Réseaux sociaux viendront s'ajouter comme sous-onglets
 * au même endroit, sur le même modèle que Seliweb_Parametres::display().
 */
class Seliweb_Media_Parametres {

    const OPTION_CADENCE        = 'seliwebmedia_cadence_minute';
    const OPTION_FROM_EMAIL     = 'seliwebmedia_from_email';
    const OPTION_FROM_NAME      = 'seliwebmedia_from_name';
    const OPTION_CONSENTEMENT   = 'seliwebmedia_texte_consentement';
    const OPTION_DELAI_PURGE    = 'seliwebmedia_delai_purge_mois';

    const CADENCE_DEFAUT      = 20;  // mails/minute, même logique que Seliweb_Mail_Queue
    const DELAI_PURGE_DEFAUT  = 12;  // mois après archivage

    public static function init() {
        add_action( 'admin_post_seliwebmedia_save_licence', array( __CLASS__, 'handle_save_licence' ) );
        add_action( 'admin_post_seliwebmedia_save_mail',    array( __CLASS__, 'handle_save_mail' ) );
    }

    public static function cadence_minute() {
        $v = (int) get_option( self::OPTION_CADENCE, self::CADENCE_DEFAUT );
        return $v > 0 ? $v : self::CADENCE_DEFAUT;
    }

    public static function from_email() {
        return trim( (string) get_option( self::OPTION_FROM_EMAIL, '' ) );
    }

    public static function from_name() {
        return trim( (string) get_option( self::OPTION_FROM_NAME, '' ) );
    }

    public static function texte_consentement() {
        $defaut = __( "J'accepte de recevoir les communications de ce site par email. Vous pouvez vous désabonner à tout moment via le lien présent dans chaque message.", 'seliwebmedia' );
        return (string) get_option( self::OPTION_CONSENTEMENT, $defaut );
    }

    public static function delai_purge_mois() {
        $v = (int) get_option( self::OPTION_DELAI_PURGE, self::DELAI_PURGE_DEFAUT );
        return $v > 0 ? $v : self::DELAI_PURGE_DEFAUT;
    }

    public static function render_dashboard() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'SeliwebMedia', 'seliwebmedia' ) . '</h1>';

        echo '<nav class="nav-tab-wrapper">';
        $onglets = array(
            'general' => __( 'Tableau de bord', 'seliwebmedia' ),
            'licence' => __( 'Déblocage', 'seliwebmedia' ),
            'mail'    => __( 'Mails', 'seliwebmedia' ),
            'gestionnaires' => __( 'Gestionnaires', 'seliwebmedia' ),
        );
        foreach ( $onglets as $key => $label ) {
            $url = admin_url( 'admin.php?page=seliwebmedia&tab=' . $key );
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav><div style="margin-top:20px;">';

        if ( $tab === 'licence' ) {
            self::tab_licence();
        } elseif ( $tab === 'mail' ) {
            self::tab_mail();
        } elseif ( $tab === 'gestionnaires' ) {
            Seliweb_Media_Droits::tab_gestionnaires();
        } else {
            self::tab_general();
        }

        echo '</div></div>';
    }

    private static function tab_general() {
        $seliweb_ok = Seliweb_Media_Integration::est_disponible();
        $licence_ok = Seliweb_Media_Quota::est_debloque();
        ?>
        <table class="form-table" style="max-width:700px;">
            <tr>
                <th><?php esc_html_e( 'Version installée', 'seliwebmedia' ); ?></th>
                <td><?php echo esc_html( SELIWEBMEDIA_VERSION ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Extension Seliweb', 'seliwebmedia' ); ?></th>
                <td>
                    <?php if ( $seliweb_ok ) : ?>
                        <span style="color:#1d6a4a;"><?php esc_html_e( 'Détectée — envoi aux groupes Seliweb disponible.', 'seliwebmedia' ); ?></span>
                    <?php else : ?>
                        <span style="color:#888;"><?php esc_html_e( 'Non détectée — fonctions listes/newsletter uniquement.', 'seliwebmedia' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Mises à jour', 'seliwebmedia' ); ?></th>
                <td><?php echo esc_html( Seliweb_Media_Updater::statut() ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Déblocage', 'seliwebmedia' ); ?></th>
                <td>
                    <span style="color:<?php echo $licence_ok ? '#1d6a4a' : '#555'; ?>;"><?php echo esc_html( Seliweb_Media_Quota::etat_texte() ); ?></span>
                    &nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=seliwebmedia&tab=licence' ) ); ?>"><?php esc_html_e( 'Gérer', 'seliwebmedia' ); ?></a>
                </td>
            </tr>
        </table>
        <?php
        // Manuel utilisateur (PDF livré avec l'extension, dans docs/).
        $doc = 'docs/Manuel-Utilisateur-SeliwebMedia.pdf';
        if ( file_exists( SELIWEBMEDIA_DIR . $doc ) ) : ?>
            <p style="margin-top:20px;">
                <a href="<?php echo esc_url( SELIWEBMEDIA_URL . $doc ); ?>" class="button" download>
                    <span class="dashicons dashicons-media-document" style="vertical-align:text-bottom;"></span>
                    <?php esc_html_e( 'Télécharger la doc', 'seliwebmedia' ); ?>
                </a>
            </p>
        <?php endif;
    }

    private static function tab_licence() {
        $sous = ( ( $_GET['sous'] ?? '' ) === 'sms' ) ? 'sms' : 'mails';
        $base = admin_url( 'admin.php?page=seliwebmedia&tab=licence' );
        echo '<ul class="subsubsub" style="float:none;margin:0 0 12px;">';
        echo '<li><a href="' . esc_url( $base ) . '" class="' . ( 'mails' === $sous ? 'current' : '' ) . '">' . esc_html__( 'Mails/Newsletter', 'seliwebmedia' ) . '</a> |</li>';
        echo '<li><a href="' . esc_url( add_query_arg( 'sous', 'sms', $base ) ) . '" class="' . ( 'sms' === $sous ? 'current' : '' ) . '">' . esc_html__( 'SMS', 'seliwebmedia' ) . '</a></li>';
        echo '</ul>';

        if ( 'sms' === $sous ) {
            echo '<p style="max-width:700px;"><em>' . esc_html__( 'En cours de développement.', 'seliwebmedia' ) . '</em></p>';
            return;
        }

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Clé enregistrée.', 'seliwebmedia' ) . '</p></div>';
        }
        if ( isset( $_GET['verifie'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Vérification effectuée.', 'seliwebmedia' ) . '</p></div>';
        }

        $cle      = Seliweb_Media_Licence::cle_enregistree();
        $etat     = Seliweb_Media_Licence::etat();
        $debloque = Seliweb_Media_Quota::est_debloque();
        ?>
        <p style="max-width:720px;font-size:14px;font-weight:600;color:<?php echo $debloque ? '#1d6a4a' : '#333'; ?>;">
            <?php echo esc_html( Seliweb_Media_Quota::etat_texte() ); ?>
        </p>
        <p class="description" style="max-width:720px;">
            <?php printf(
                esc_html__( "Sans clé de déblocage, SeliwebMedia fonctionne en version gratuite : %d envois par mois (mails et newsletters confondus, remis à zéro le 1er du mois) et la mention « Envoyé avec SeliwebMedia — connex-asso.net » en bas des messages. La clé de déblocage, fournie par l'association Connex, lève la limite et retire la mention. Elle est liée à ce site.", 'seliwebmedia' ),
                (int) Seliweb_Media_Quota::plafond()
            ); ?>
            <a href="<?php echo esc_url( Seliweb_Media_Quota::URL_MENTION ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'En savoir plus', 'seliwebmedia' ); ?></a>
        </p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;margin-top:16px;">
            <?php wp_nonce_field( 'seliwebmedia_save_licence' ); ?>
            <input type="hidden" name="action" value="seliwebmedia_save_licence">
            <table class="form-table">
                <tr>
                    <th><label for="seliwebmedia_cle"><?php esc_html_e( 'Clé de déblocage', 'seliwebmedia' ); ?></label></th>
                    <td>
                        <input type="text" id="seliwebmedia_cle" name="seliwebmedia_cle" class="regular-text"
                               value="<?php echo esc_attr( $cle ); ?>">
                        <?php if ( '' !== $cle ) : ?>
                        <p class="description">
                            <span style="color:<?php echo $debloque ? '#1d6a4a' : '#b32d2e'; ?>;font-weight:600;"><?php echo esc_html( $etat['message'] ); ?></span>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Mises à jour', 'seliwebmedia' ); ?></th>
                    <td><?php echo esc_html( Seliweb_Media_Updater::statut() ); ?></td>
                </tr>
            </table>
            <?php submit_button( __( 'Enregistrer', 'seliwebmedia' ) ); ?>
        </form>

        <?php if ( '' !== $cle ) : ?>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url(
                admin_url( 'admin-post.php?action=seliwebmedia_verifier_licence' ),
                'seliwebmedia_verifier_licence'
            ) ); ?>" class="button">
                <?php esc_html_e( 'Vérifier maintenant', 'seliwebmedia' ); ?>
            </a>
        </p>
        <?php endif;
    }

    private static function tab_mail() {
        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Réglages enregistrés.', 'seliwebmedia' ) . '</p></div>';
        }

        $from_email = self::from_email();
        $site_domain = preg_replace( '/^www\./', '', strtolower( $_SERVER['HTTP_HOST'] ?? '' ) );
        $from_domain = $from_email !== '' ? strtolower( substr( strrchr( $from_email, '@' ), 1 ) ) : '';
        $domaine_differe = $from_email !== '' && $from_domain && $site_domain && $from_domain !== $site_domain;
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px;">
            <?php wp_nonce_field( 'seliwebmedia_save_mail' ); ?>
            <input type="hidden" name="action" value="seliwebmedia_save_mail">
            <table class="form-table">
                <tr>
                    <th><label for="swm_from_name"><?php esc_html_e( "Nom de l'expéditeur", 'seliwebmedia' ); ?></label></th>
                    <td><input type="text" id="swm_from_name" name="from_name" class="regular-text" value="<?php echo esc_attr( self::from_name() ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="swm_from_email"><?php esc_html_e( "Adresse email de l'expéditeur", 'seliwebmedia' ); ?></label></th>
                    <td>
                        <input type="email" id="swm_from_email" name="from_email" class="regular-text" value="<?php echo esc_attr( $from_email ); ?>">
                        <?php if ( $domaine_differe ) : ?>
                            <p class="description" style="margin-top:4px;color:#b32d2e;font-weight:600;">
                                <?php printf(
                                    esc_html__( 'Cette adresse est sur le domaine « %1$s », différent de celui du site (« %2$s ») : risque réel d\'être rejetée ou classée en spam (SPF). Préférez une adresse en @%2$s.', 'seliwebmedia' ),
                                    esc_html( $from_domain ), esc_html( $site_domain )
                                ); ?>
                            </p>
                        <?php else : ?>
                            <p class="description" style="margin-top:4px;color:#888;">
                                <?php printf( esc_html__( 'Pour éviter des problèmes de délivrabilité, utilisez une adresse rattachée au domaine du site (%s).', 'seliwebmedia' ), esc_html( $site_domain ) ); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="swm_cadence"><?php esc_html_e( 'Cadence d\'envoi (mails par minute)', 'seliwebmedia' ); ?></label></th>
                    <td>
                        <input type="number" id="swm_cadence" name="cadence" class="small-text" min="1" max="200" value="<?php echo esc_attr( self::cadence_minute() ); ?>">
                        <p class="description"><?php esc_html_e( "À régler selon la limite d'envoi autorisée par votre hébergeur.", 'seliwebmedia' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="swm_delai_purge"><?php esc_html_e( 'Purge des messages (mois après archivage)', 'seliwebmedia' ); ?></label></th>
                    <td>
                        <input type="number" id="swm_delai_purge" name="delai_purge" class="small-text" min="1" max="120" value="<?php echo esc_attr( self::delai_purge_mois() ); ?>">
                        <p class="description"><?php esc_html_e( 'Un message est archivé 1 mois après son envoi, puis supprimé définitivement (avec ses destinataires) après ce délai supplémentaire.', 'seliwebmedia' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="swm_consentement"><?php esc_html_e( 'Texte de consentement (formulaire public d\'inscription)', 'seliwebmedia' ); ?></label></th>
                    <td>
                        <textarea id="swm_consentement" name="consentement" rows="3" class="large-text"><?php echo esc_textarea( self::texte_consentement() ); ?></textarea>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Enregistrer', 'seliwebmedia' ) ); ?>
        </form>
        <?php
    }

    public static function handle_save_mail() {
        if ( ! Seliweb_Media_Droits::est_admin() ) {
            wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        }
        check_admin_referer( 'seliwebmedia_save_mail' );

        $from_email = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
        if ( $from_email !== '' && ! is_email( $from_email ) ) {
            $from_email = '';
        }
        update_option( self::OPTION_FROM_EMAIL,   $from_email );
        update_option( self::OPTION_FROM_NAME,    sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ) );
        update_option( self::OPTION_CADENCE,      max( 1, intval( $_POST['cadence'] ?? self::CADENCE_DEFAUT ) ) );
        update_option( self::OPTION_DELAI_PURGE,  max( 1, intval( $_POST['delai_purge'] ?? self::DELAI_PURGE_DEFAUT ) ) );
        update_option( self::OPTION_CONSENTEMENT, sanitize_textarea_field( wp_unslash( $_POST['consentement'] ?? '' ) ) );

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia&tab=mail&updated=1' ) );
        exit;
    }

    public static function handle_save_licence() {
        if ( ! Seliweb_Media_Droits::est_admin() ) {
            wp_die( esc_html__( 'Accès refusé.', 'seliwebmedia' ) );
        }
        check_admin_referer( 'seliwebmedia_save_licence' );

        Seliweb_Media_Licence::enregistrer_cle( wp_unslash( $_POST['seliwebmedia_cle'] ?? '' ) );
        Seliweb_Media_Licence::info( true ); // interroge Connex tout de suite

        wp_safe_redirect( admin_url( 'admin.php?page=seliwebmedia&tab=licence&updated=1' ) );
        exit;
    }
}
