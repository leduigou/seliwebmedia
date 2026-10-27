<?php
/**
 * Gabarit HTML générique pour les messages SeliwebMedia au format HTML.
 *
 * Conçu une fois pour s'afficher correctement dans tous les clients mail
 * (structure en tableaux, styles en ligne — le même principe que produirait
 * un outil comme MJML, mais sans dépendance à l'exécution : ce fichier est
 * du HTML statique, livré tel quel, pas généré dynamiquement à l'envoi).
 *
 * Variables attendues : $corps_html (le contenu du message), $lien_desabonnement
 * (URL ou chaîne vide si non applicable — ex. message envoyé à un groupe Seliweb).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f1f1f1;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f1f1;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;">
<tr><td style="padding:28px 32px;color:#222222;font-size:15px;line-height:1.6;">
<?php echo $corps_html; // déjà filtré (wp_kses_post) avant stockage ?>
</td></tr>
<?php if ( ! empty( $lien_desabonnement ) ) : ?>
<tr><td style="padding:16px 32px;background:#f6f6f6;font-size:12px;color:#888888;text-align:center;">
<a href="<?php echo esc_url( $lien_desabonnement ); ?>" style="color:#888888;">Se désabonner</a>
</td></tr>
<?php endif; ?>
</table>
</td></tr>
</table>
</body>
</html>
