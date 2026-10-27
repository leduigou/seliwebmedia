# SeliwebMedia

Extension WordPress libre et gratuite : listes de diffusion, newsletters et gestion des réseaux sociaux — en lien avec les groupes de l'extension [**Seliweb-WP**](https://github.com/leduigou/seliweb) si elle est active sur le même site.

SeliwebMedia fonctionne de façon autonome (listes de diffusion et newsletters classiques) même sans Seliweb-WP. L'envoi de messages directement aux groupes d'un SEL nécessite que Seliweb-WP soit installée et active sur le même site.

## Prérequis

- WordPress 6.0 ou supérieur
- PHP 7.4 ou supérieur
- L'extension [Seliweb-WP](https://github.com/leduigou/seliweb) installée et active, pour la liaison avec les groupes (facultatif pour le reste)

## Installation

1. Téléchargez le zip de la [dernière release](https://github.com/leduigou/seliwebmedia/releases/latest) (`seliwebmedia-X.Y.Z.zip`).
2. Dans l'administration WordPress : **Extensions → Ajouter une extension → Téléverser une extension**, sélectionnez le zip téléchargé, puis **Installer**.
3. Activez l'extension : le menu **SeliwebMedia** apparaît dans la colonne de gauche, sous Seliweb.

## Licence (clé API Connex)

L'extension est librement téléchargeable et installable. L'utilisation des fonctions d'envoi (listes de diffusion, newsletters, réseaux sociaux) nécessite une clé API fournie par l'association Connex, à saisir dans **SeliwebMedia → Licence**.

## Mises à jour

Les nouvelles versions sont publiées sur ce dépôt GitHub et détectées automatiquement par WordPress (Extensions / Mises à jour), comme pour une extension classique du répertoire officiel.

## Changelog

### 0.6.7
- Correctif : le lien « Aller à l'onglet Mails » (message « Vous devez d'abord renseigner les paramètres » à la création d'un message) pointait vers un slug de page inexistant (`seliwebmedia_reglages` au lieu de `seliwebmedia`), ce qui affichait « Vous n'avez pas l'autorisation d'accéder à cette page ».

### 0.6.6
- Menu d'administration : SeliwebMedia se place désormais juste après Seliweb dans la colonne de gauche (la position 2 demandée par Seliweb est en réalité décalée par WordPress, qui l'utilise déjà pour son propre Tableau de bord).

### 0.6.5
- Formulaire public d'inscription : champ Email élargi pour s'aligner avec la fin du champ Ville (largeurs explicites Code postal/Ville/Email).

### 0.6.4
- Formulaire public d'inscription : correctif d'affichage des champs Code postal/Ville (le libellé « Ville » apparaissait à côté du champ Code postal au lieu d'être au-dessus de son propre champ) — chaque champ est maintenant regroupé dans un bloc `inline-block` qui ne se scinde plus.

### 0.6.3
- Onglet Inscriptions : tableau élargi (shortcodes affichés sur une seule ligne) ; image d'exemple réduite à 600px avec le titre « Exemple de saisie de Shortcode – Code court dans une page WP » au-dessus.

### 0.6.2
- Écran Listes mails : colonne ID ajoutée avant Nom dans le tableau « Listes SeliwebMedia » (l'id entre parenthèses après le nom est retiré) ; le rappel du shortcode au-dessus du tableau est retiré.
- Nouvel onglet **Inscriptions** (après Messages) : tableau ID/Nom/Shortcode avec un bouton « Copier le shortcode » par liste (shortcode complet, avec l'id, copié dans le presse-papiers), message d'explication en plus gros, et une image d'exemple montrant l'insertion du shortcode dans un bloc Code court.

### 0.6.1
- Formulaire de message (nouveau/modifier/dupliquer) : le nombre de destinataires actuel est affiché après chaque liste SeliwebMedia et chaque groupe Seliweb.
- Écran Listes mails : rappel du shortcode `[seliwebmedia_inscription liste="X"]` à insérer dans une page pour proposer l'inscription (avec l'id de chaque liste affiché), et précision que le désabonnement ne nécessite aucune page particulière (lien inclus automatiquement dans chaque mail).

### 0.6.0
- Liste des messages : l'action « Voir » devient « Modifier » pour les messages Brouillon/Programmé ; les deux liens « Dupliquer le texte » et « Dupliquer (+ destinataires) » sont remplacés par un seul lien « Dupliquer » qui ouvre le formulaire pré-rempli (rien n'est enregistré avant validation ; la date d'envoi n'est jamais reprise).
- Formulaire de message : devient aussi l'écran de modification (Brouillon/Programmé) et de duplication ; deux boutons « Enregistrer brouillon » et « Envoyer ». « Enregistrer brouillon » repasse toujours le message en brouillon, avec une demande de confirmation si le message était déjà programmé.

### 0.5.0
- Liste des messages : colonne ID ajoutée avant Sujet.
- Nouveau message : si l'adresse email de l'expéditeur n'est pas réglée (onglet Mails), le formulaire de création est remplacé par un message d'avertissement avec lien direct vers ce réglage.

### 0.4.1
- Correctif : les fenêtres modales « Modifier un destinataire » étaient imbriquées dans le tableau (HTML invalide), ce qui déstabilisait l'affichage de la page — déplacées après le tableau.

### 0.4.0
- Écran Destinataires d'une liste : titre « Destinataires de la liste : [nom] » ; saisie d'un nouveau destinataire déplacée dans une fenêtre modale ouverte par un bouton en haut de page ; action « Modifier » ajoutée (fenêtre modale pré-remplie) ; colonne Statut retirée (seuls les destinataires actifs sont affichés) ; largeurs de colonnes explicites.

### 0.3.3
- Groupes Seliweb : texte de présentation simplifié ; colonne « Abonnés » (nombre de membres) ajoutée avant la colonne Actions.

### 0.3.2
- Tableau « Listes SeliwebMedia » : largeur des colonnes Nom (300 px) et Description (500 px) ajustée.

### 0.3.1
- Tableau « Listes SeliwebMedia » : colonnes Nom/Description resserrées pour que les actions tiennent sur une ligne, ordre des actions Modifier | Supprimer | Destinataires (renommé depuis « Voir »), colonne « Abonnés actifs » renommée « Abonnés ».

### 0.3.0
- Listes SeliwebMedia : possibilité de modifier le nom/la description d'une liste existante, d'ajouter un destinataire manuellement depuis l'admin (sans passer par le formulaire public), et de le retirer d'une liste donnée.
- Groupes Seliweb : lien « Voir » donnant un aperçu en lecture seule des membres actuels du groupe (purement informatif, la vraie liste se reconstitue à l'envoi).

### 0.2.2
- Titre de la page « Listes mails » renommé en « Listes de diffusion par mails ».

### 0.2.1
- Menu renommé « Réglages » → « Paramétrage », placé en premier sous SeliwebMedia (c'est désormais l'écran affiché par défaut en cliquant sur SeliwebMedia) ; « Listes mails » passe en second.

### 0.2.0
- Écran « Listes mails » : listes de diffusion natives (avec formulaire public d'inscription et consentement) et groupes Seliweb (destinataires reconstitués au moment de l'envoi, dans le respect des préférences de chaque membre).
- Écran Messages : création (texte brut ou HTML avec mise en forme), visualisation, duplication (texte seul ou texte + destinataires), envoi immédiat ou programmé à une date donnée.
- File d'envoi à la minute, cadence réglable (onglet Réglages → Mails), avec variables de personnalisation `{prenom}`/`{nom}` et lien de désabonnement en un clic pour les listes natives.
- Archivage automatique des messages envoyés après 1 mois, purge après un délai réglable (12 mois par défaut).
- Réglages → Mails : adresse d'expéditeur (avec avertissement de délivrabilité si le domaine diffère de celui du site), cadence d'envoi, texte de consentement, délai de purge.

### 0.1.0
- Première version : squelette de l'extension (menu d'administration, détection de Seliweb-WP, gestion de la clé API Connex, mise à jour automatique via GitHub). Pas encore de fonction de liste de diffusion, newsletter ou réseaux sociaux.
