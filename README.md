# Nzhinu Création — boutique africaine

Référencement : HTML public lisible sans JavaScript, métadonnées locales, catégories indexables, données structurées et sitemap automatique. L’indexation est désactivée en développement ; [activation sur le domaine définitif et recette SEO](docs/seo.md).

La vitrine est contrôlée à 320, 390, 768, 1024 et 1440 px. Le contact WhatsApp animé est intégré au menu sous 1440 px et reste flottant dans la marge sur les grands écrans ; il ne recouvre plus les articles, formulaires et montants. Les champs sont agrandis sur téléphone et les pages d’information reprennent les en-têtes noir et or. [Dernière recette visuelle](docs/visual-review.md).

**Lancement MVP : Cameroun uniquement (XAF).** Boutique à Douala, Bonaberi Rail, Marché ChaCha 223. Les fonctionnalités techniques CA/CAD sont conservées pour une ouverture ultérieure. Configuration et retrait : [documentation](docs/cameroon-launch.md).

La vitrine présente simplement la boutique et ses prix (exemple : `25 000`), sans mention du pays ou de la devise implicite. Les devises restent enregistrées et contrôlées en interne.

Après « À découvrir », l’accueil présente les catégories avec icône, nom et description facultative, reliées au catalogue filtré. [Gestion des catégories et migration](docs/home-categories.md).

La page `/boutique` reprend la palette noir, or et ivoire de l’accueil : en-tête éditorial, raccourcis de catégories, cartes produits communes et panneau de filtres repliable sur mobile. Le tri est accessible au-dessus des résultats. Les filtres appliqués peuvent être retirés individuellement ; le formulaire suit les raccourcis, la réinitialisation et le retour arrière du navigateur. Les raccourcis conservent les autres filtres et repartent de la première page ; le tri par défaut n’ajoute pas de paramètre superflu à l’URL.

[Présentation et vérifications de la boutique](docs/boutique-presentation.md).

Pied de page en colonnes et logo noir et or sans fond : [présentation et contrôle du PNG transparent](docs/footer-logo.md).

Galerie produit : carrousel tactile et accessible, chevrons SVG centrés et curseur pointer sur les commandes, deux visuels par article de démonstration. Installation, gestion des photos et contrôles : [documentation](docs/product-gallery.md).

Les photos peuvent être agrandies et zoomées ; les miniatures suivent la sélection. La fiche propose jusqu’à quatre articles similaires de la même catégorie, visibles dans le catalogue courant.

La fiche article reprend la présentation noir et or de la boutique : titre et prix visibles avant la galerie sur mobile, disponibilité de la variante choisie, bloc de sélection et boutons de quantité. Le changement de variante ou de stock réinitialise la quantité et les choix incompatibles. Le conseil WhatsApp préremplit le nom et le lien public de l’article. [Présentation et vérifications de la fiche](docs/product-presentation.md).

Le panier reprend la même présentation : fiches d’articles, boutons de quantité, récapitulatif noir et or et état vide illustré. Les quantités modifiées doivent être enregistrées avec « Actualiser » avant de poursuivre la demande ; le sous-total reste celui enregistré sur le serveur. Les articles indisponibles bloquent la demande et peuvent être retirés. [Présentation du panier](docs/cart-presentation.md).

La page `/commander` présente les coordonnées et le mode de réception dans des blocs distincts, avec choix livraison/retrait illustrés et récapitulatif noir et or. Les champs de livraison restent en mémoire pendant un changement de mode, l’adresse du point de retrait apparaît après sélection et les erreurs sont regroupées puis associées à leurs champs. [Présentation et recette de la commande](docs/checkout-presentation.md).

La confirmation et le suivi reprennent la même présentation : statuts séparés, progression fondée sur les états enregistrés, sélection commandée, réception et montants vérifiés. Une actualisation manuelle conserve le lien signé. Les demandes expirées, annulées ou remboursées disposent d’un affichage adapté, sans faire passer un remboursement pour une nouvelle somme à régler. [Présentation et vérifications du suivi](docs/order-tracking-presentation.md).

Animations légères de la vitrine, sans dépendance et avec prise en charge du mouvement réduit : [documentation](docs/storefront-motion.md).

Monolithe Laravel 12 / MySQL 8, vitrine Inertia 2 + React + TypeScript + Tailwind 4 et administration Filament 5. Les commandes sont des demandes sans compte client : confirmation téléphonique et vérification manuelle du paiement avant remise. Aucun paiement en ligne.

## Phase 1

- Page d'attente française sur `/`, via Inertia ; catalogue et panier prévus en phase 4.
- Connexion/déconnexion du personnel sur `/admin`, limitation des tentatives par Filament, aucune inscription publique.
- Spatie Permission 6 : rôles `administrator`, `manager`, `staff`. Tous accèdent au panneau ; seuls les administrateurs peuvent créer du personnel via le service applicatif. Responsables et administrateurs disposent des permissions de gestion des marchés et dépôts. Les écrans CRUD viendront aux phases concernées.
- Marchés Canada (`CA`, `CAD`, 2 décimales) et Cameroun (`CM`, `XAF`, 0 décimale). Les futurs montants seront des entiers séparés par devise.
- Plusieurs lieux de stockage possibles par marché, code unique, suppression logique et relation protégée. Un dépôt exemple désactivé, à adresse fictive, par pays.
- Seeders répétables sans compte ni mot de passe par défaut, préservant les dépôts modifiés ou archivés. Le seeder des rôles réapplique la matrice de permissions déclarée dans le code.
- Media Library 11 installé avec sa migration ; catalogue et médias produits en phase 2.

## Phase 2 — catalogue

Dans `/admin`, le groupe **Catalogue** permet de gérer catégories, collections culturelles et produits. Administrateurs et responsables peuvent créer, modifier et archiver ; le rôle `staff` dispose d'un accès en lecture seule. Les permissions existantes sont mises à jour par `php artisan db:seed`.

Un produit possède une catégorie principale, plusieurs collections facultatives et au moins une variante active. Dans le formulaire, renseigner un SKU unique et le libellé `Standard` pour un produit sans taille/couleur. Les nouveaux produits sont en brouillon ; le service crée automatiquement une variante Standard avec un SKU généré si aucune liste de variantes n'est fournie. Les SKU et identifiants URL restent réservés après archivage.

Les prix sont saisis dans la devise du marché : `49,95` ou `49.95` CAD donne 4995 cents ; `25000` XAF donne 25000 francs. La devise est déduite du marché, jamais choisie par le navigateur. Montants non négatifs, aucun nombre flottant, plafond de 999999999999 unités mineures. Les marchés disponibles sont définis séparément ; chaque variante active doit avoir un prix pour chaque marché activé. Les brouillons, catégories archivées/inactives et marchés inactifs ne sont pas éligibles à la publication. Cette disponibilité est celle du catalogue ; le stock sera ajouté en phase 3.

Les variantes retirées du formulaire sont archivées ; leurs références et prix restent conservés. L'archivage d'un produit conserve ses variantes et images. Un compteur de version rejette une sauvegarde obsolète : recharger la page et reprendre les modifications pour éviter d'écraser le travail d'un collègue. Une sauvegarde par service porte sur l'ensemble du formulaire, pas un patch partiel.

Après création d'un produit, les actions **Ajouter une image** et **Supprimer une image** sont disponibles sur la page de modification. JPEG/PNG/WebP uniquement, 5 Mio maximum, dimensions au plus 6000 × 6000, description alternative obligatoire et nom de fichier généré. Les images sont stockées avec Spatie Media Library sur le disque `public`, donc accessibles à toute personne qui connaît leur URL, y compris pour un brouillon. Pour les afficher :

```powershell
php artisan storage:link
```

Trois catégories initiales sont ajoutées sans écraser les modifications : Vêtements, Accessoires, Bijoux. Aucun produit ni collection culturelle fictifs n'est ajouté. Les archivages restent conservés à la réexécution des seeders.

## Phase 3 — stocks et réservations

Le groupe **Stocks** de Filament contient les lieux de stockage, stocks physiques, réservations et le journal des mouvements. Administrateurs et responsables peuvent modifier les stocks et gérer les réservations ; le personnel peut les consulter. Les droits restent globaux aux deux marchés.

1. Dans **Lieux de stockage**, remplacer l'adresse fictive par l'adresse réelle, décocher « Adresse fictive / à valider », puis activer le lieu. Le marché d'un lieu existant est immuable. Un lieu contenant du stock ne peut pas être archivé ; on peut le désactiver pour empêcher de nouvelles réservations.
2. Dans **Stocks physiques**, initialiser la quantité d'une variante à un lieu précis, son seuil d'alerte et un motif. Une seule ligne de stock par lieu/variante. Les réceptions et corrections exigent aussi un motif ; une correction ne peut pas ramener le stock physique sous le stock réservé. Le journal conserve les variations et les quantités résultantes.
3. Dans **Réservations**, choisir le marché et chaque ligne de stock explicitement, avec sa quantité. Le service refuse les lieux d'un autre pays, inactifs ou fictifs, les variantes non publiées et les quantités indisponibles. Une réservation de plusieurs lignes réussit entièrement ou ne change rien.

Exemple : 10 unités physiques et 3 réservées donnent 7 disponibles. Réserver/libérer ne modifie jamais les 10 unités physiques. L'expiration et la libération sont répétables sans double mouvement. Une réactivation revérifie le stock, ouvre un nouveau cycle et réinitialise l'échéance ; une ancienne requête de libération ne peut pas libérer le nouveau cycle.

Les initialisations, corrections, créations et réactivations utilisent des clés UUID de reprise, générées par les formulaires. Répéter une même clé et les mêmes données ne répète pas l'opération. Réutiliser une clé avec d'autres données ou un autre acteur est refusé. Le délai est de 24 heures par défaut ; `INVENTORY_RESERVATION_HOURS` peut être réglé entre 1 et 720 heures dans `.env`. Aucune modification de `.env` existant n'est nécessaire pour utiliser la valeur par défaut.

Les opérations verrouillent les stocks dans l'ordre croissant de leurs identifiants, dans des transactions avec reprise des interblocages. MySQL impose des quantités non négatives et `réservé <= physique`. Les tests SQLite utilisent aussi des contraintes par triggers ; les garanties de concurrence sont vérifiées sur MySQL avec des processus distincts.

Les alertes de stock faible sont envoyées dans les notifications Filament aux administrateurs/responsables quand le disponible atteint ou passe sous le seuil. Une seule alerte par passage sous le seuil ; une remontée au-dessus du seuil réarme l'alerte. Les notifications sont mises en file après validation de la transaction. Le journal et l'historique des réservations sont consultables, sans suppression depuis l'administration.

Pour exécuter automatiquement les expirations et les notifications, laisser fonctionner les deux processus en développement :

```powershell
php artisan schedule:work
php artisan queue:work --tries=3 --timeout=60
```

En production, exécuter `php artisan schedule:run` chaque minute via le planificateur du serveur, et superviser le worker de file d'attente. L'ordonnanceur met le job d'expiration en file chaque minute. Plusieurs exécutions restent sûres grâce aux verrous et aux statuts. Pour un rattrapage immédiat sans worker :

```powershell
php artisan inventory:release-expired --sync
```

Cette phase propose des réservations internes au personnel. Leur rattachement transactionnel aux commandes invitées et le délai de paiement après confirmation viendront en phase 5. La remise après paiement intégral vérifié, avec décrément physique exactement une fois, sera implémentée en phase 6. Aucun écran de livraison, de remise ou de transfert international n'est ajouté ici ; les corrections sont réservées aux réceptions et écarts de comptage.

## Phase 4 — boutique publique et panier

La vitrine utilise le logo original `public/images/logo.png` et la palette noir, or et ivoire. Routes : accueil `/`, catalogue `/boutique`, fiches `/produits/{slug}` et panier `/panier`. L'interface est française, avec une navigation adaptée au mobile et des filtres repliables sur petit écran.

Le sélecteur de marché applique Canada/CAD ou Cameroun/XAF à toute la visite. Sans préférence de session, le Canada est choisi s'il est actif, sinon le premier marché actif. Aucun pays n'est déduit de l'adresse IP. Un changement de marché avec un panier non vide exige une confirmation de vidage ; aucune conversion ni addition entre devises n'est effectuée. Si le marché d'un ancien panier est désactivé, le panier reste conservé mais sans total jusqu'à son vidage explicite. Sans marché actif, la boutique répond 503.

Le catalogue présente uniquement les produits publiés, activés dans le marché choisi, liés à une catégorie active et possédant une variante active avec un prix dans la bonne devise. Recherche nom/description/SKU, filtres par catégorie, collection culturelle, fourchette de prix et stock, tri par nouveauté/nom/prix, pagination de 12 produits. Une fourchette de prix retient les produits ayant au moins une variante dans cette fourchette ; le prix « À partir de » reste le plus petit prix du produit dans ce marché. Les produits sans stock restent consultables, avec une indication d'indisponibilité.

Les fiches affichent les images et leur texte alternatif, les variantes, leurs prix et les stocks disponibles par lieu. Seuls les lieux actifs, validés et situés dans le pays choisi sont utilisables. Les adresses, quantités physiques/réservées et informations du personnel ne sont pas exposées. Donner aux lieux des noms adaptés à un affichage public : ces libellés apparaissent dans le choix du stock de départ.

Le panier appartient à la session invitée, sans compte. Chaque ligne correspond à un stock précis (variante + lieu), avec 1 à 99 unités et au maximum 50 lignes. « Mettre au panier » fixe la quantité de cette ligne ; répéter la requête ne l'ajoute pas une deuxième fois. Plusieurs lieux restent plusieurs lignes, sans fusion de quantités. Les prix et sous-totaux sont recalculés depuis la base en unités mineures entières ; les prix ou devises envoyés par le navigateur sont ignorés. Le panier n'effectue aucune réservation ni mouvement physique.

Si le prix change, le panier utilise le prix courant. Si un produit, un lieu ou une quantité n'est plus disponible, la ligne est signalée sans réduction silencieuse ; aucun sous-total n'est présenté tant que le panier contient une erreur. L'utilisateur peut actualiser, retirer une ligne ou vider le panier. Les mutations sont protégées par CSRF ; les routes publiques dynamiques partagent un verrou de session pour éviter les écrasements entre requêtes concurrentes.

Conserver un stockage de session côté serveur (par défaut `database`) et un cache prenant en charge les verrous atomiques (par défaut `database`). Le driver de session `cookie` ne convient pas au verrouillage utilisé ici. Les métadonnées titre/description/canonical et les balises Open Graph des fiches sont présentes dès le HTML initial, puis gérées par Inertia ; recherches/filtres et panier sont marqués `noindex`. Le marché reste une préférence de session sur des URL communes : un destinataire d'un lien doit sélectionner son propre marché. L'interface interactive exige JavaScript ; aucun serveur SSR Node supplémentaire n'est ajouté.

Pour alimenter la boutique : créer les vrais produits dans Filament, ajouter leurs images, catégories et variantes, définir les prix par marché, activer leur disponibilité et passer le statut à **Publié**. Initialiser ensuite le stock dans un lieu réel validé. Aucun produit ni stock fictif n'a été ajouté à la base de travail pour cette phase. Le dépôt sans produits publiés affiche un état vide explicite.

La demande de commande, les coordonnées invitées et la réservation transactionnelle seront ajoutées en phase 5. Aucun bouton de paiement ou de validation de commande non fonctionnel n'est affiché.

## Phase 5 — commandes invitées

Depuis `/panier`, **Demander ma commande** ouvre `/commander` : nom et téléphone obligatoires, e-mail facultatif, livraison ou retrait. Le pays est celui du marché choisi. La livraison collecte adresse et ville ; province/région, complément et code postal sont facultatifs. Aucun compte ni paiement en ligne n'est créé.

Dans Filament, le groupe **Commandes** permet aux responsables/administrateurs de configurer des zones de livraison et des points de retrait, puis de consulter les demandes et confirmer les appels. Aucun faux point ou tarif n'est semé. Sans zone configurée, la livraison peut être demandée avec ses frais à confirmer. Un retrait exige un point actif avec une adresse publique réelle, associé au dépôt de **toutes** les lignes du panier ; aucun transfert entre dépôts n'est implicite. Les prix de zone sont des estimations dans la devise du marché, jamais un paiement ni des frais définitifs.

La commande et ses réservations sont créées dans la même transaction MySQL. Les lignes conservent SKU, nom, variante, taille/couleur, prix, devise et lieu sélectionné. Le stock physique reste inchangé. La reprise d'une soumission avec la même clé et le même contexte de session retourne la même commande ; un autre contexte ou un autre contenu est refusé. Un panier obsolète, un changement de prix non revu ou une rupture ne laisse aucune commande partielle. Le panier est vidé après succès, sans effacer une nouvelle sélection lors d'une ancienne reprise.

Les coordonnées et l'adresse de livraison sont chiffrées en base avec la clé de l'application. Les erreurs de formulaire ne recopient pas ces coordonnées dans les anciens champs de session. Ne pas perdre `APP_KEY` : elle sert aussi au déchiffrement des commandes et aux signatures. Les liens privés de confirmation utilisent une référence aléatoire et une signature temporaire ; un numéro de commande seul ne permet pas l'accès. Ils affichent le récapitulatif et un téléphone masqué, sans l'adresse privée ni l'e-mail. Ces pages sont `no-store`, `noindex` et ne transmettent pas de référent.

L'état de commande, l'état de paiement et l'état de remise sont séparés. Les responsables/administrateurs peuvent confirmer l'appel, annuler ou réactiver une demande impayée/non remise. La confirmation exige la saisie explicite des frais et du montant des taxes après revue locale ; aucun taux légal ou fiscal n'est inventé. Le total définitif reste absent avant confirmation. Le paiement reste **Non payé** : la saisie/vérification des paiements et la remise physique appartiennent à la phase 6.

La réservation initiale expire après `INVENTORY_RESERVATION_HOURS` (24 h). Après appel confirmé, le délai de paiement devient `ORDER_PAYMENT_HOURS` (24 h par défaut), avec échéance commune à la commande et à sa réservation. Le job existant libère les stocks et marque la commande expirée de façon idempotente. Réactiver revérifie le stock, ouvre un nouveau cycle et nécessite un nouvel appel ; les frais définitifs sont à reconfirmer. Une réservation liée à une commande doit être gérée depuis le workflow de cette commande.

`ORDER_LINK_DAYS` règle la validité des liens signés (30 jours par défaut, bornée entre 1 et 90). Conserver `APP_URL` et la configuration HTTPS/proxy corrects en déploiement, et traiter les liens signés comme privés. Les rôles `staff` ne reçoivent pas par défaut la permission de consulter les coordonnées des commandes. Les liens de confirmation restent consultables même si leur marché est ensuite désactivé.

## Phase 6 — paiements manuels et remise

Dans **Commandes → Commandes invitées**, déclarer un paiement avec montant dans la devise de la commande, moyen, référence du mouvement et note interne. Les responsables et administrateurs peuvent déclarer ; seuls les administrateurs vérifient ou rejettent dans **Paiements et remboursements**. Les références sont normalisées et uniques par marché/type/moyen. Les déclarations n'ajoutent aucun fonds au solde vérifié.

La vérification exige un relevé bancaire, un relevé du fournisseur ou un comptage de caisse pour les espèces, avec compte rendu du contrôle et identité du vérificateur. Une capture fournie par le client ne suffit pas. Les paiements partiels s'additionnent en unités mineures entières. Aucune modification/suppression des mouvements traités n'est proposée ; une correction financière passe par un remboursement explicite. Les moyens proposés sont des libellés de suivi manuel, sans intégration bancaire ou WhatsApp.

Le parcours est : **Appel confirmé → Déclarer le paiement → Vérifier → Préparer → Expédier ou remettre**. Préparer et remettre exigent un total définitif, une réservation active et un net vérifié couvrant ce total. La préparation ne change pas le stock physique. L'expédition exige un transporteur et un numéro de suivi ; le retrait exige un reçu de remise. Le mode doit correspondre à la demande initiale. La remise diminue atomiquement stock physique et réservé, consomme la réservation et clôture la commande. Les reprises avec une même clé ne dupliquent ni fonds ni mouvements de stock.

Les commandes intégralement payées après vérification conservent leur réservation au-delà de l'échéance de paiement jusqu'à remise ou annulation explicite. Les commandes partiellement payées peuvent expirer : les fonds restent comptabilisés pour revue. Un paiement vérifié après échéance ou annulation est conservé sans réactiver le stock ; une réactivation impose un nouveau contrôle de disponibilité et un nouvel appel pour les frais/taxes.

Les administrateurs peuvent déclarer puis vérifier un remboursement **déjà effectué hors application**. Le logiciel ne transfère aucun argent. Les remboursements vérifiés ne peuvent dépasser le net reçu ; les déclarations en attente réservent aussi leur budget de remboursement. Si un remboursement vérifié rend une préparation insuffisamment payée, elle est suspendue et la remise est bloquée. Une commande déjà remise conserve son historique et son stock consommé après remboursement ; aucun retour physique n'est automatique. Une annulation avec fonds reçus exige la permission de remboursement, libère seulement le stock réservé et laisse la décision financière explicite.

Le détail staff affiche les montants reçus/remboursés/nets, l'audit et les fonds à examiner après clôture ou en trop-perçu. Le lien privé client affiche les soldes vérifiés et le suivi de remise, sans références bancaires, notes internes ni identité du personnel. Logo et couleurs noir/or conservés.

La migration de phase 6 refuse un rollback lorsqu'un historique de paiement/remise existe. Sauvegarder les données et préparer une migration corrective en cas d'incident ; le rollback sur une base fraîche reste testé. Les permissions sont mises à jour par `php artisan migrate --seed` : ce seeder réapplique la matrice de rôles déclarée dans le code.

## Phase 7 — notifications, contenu et suivi

Le groupe **Contenu** de Filament propose des pages publiques et des bannières d'accueil. Créer un brouillon, sélectionner éventuellement un marché, puis publier. Les pages sont accessibles sur `/pages/identifiant` et dans le pied de page (8 premiers titres). Les bannières respectent le marché, leurs dates UTC et leur ordre (3 premières actives). Les textes restent du texte simple, sans exécution de HTML ; leurs liens sont internes. Aucun contenu fiscal/juridique ou texte de démonstration n'est publié automatiquement. L'archivage conserve les enregistrements et les identifiants URL ; les versions empêchent d'écraser une modification concurrente.

Le tableau de bord des administrateurs/responsables présente les commandes à appeler, confirmées et remises, les fonds vérifiés et remboursements, les situations à examiner, les stocks faibles et les notifications en attente/échec. Les montants restent séparés par marché **et devise figée**. Les fonds de commandes annulées/expirées sont inclus dans les soldes ; ils ne sont pas un chiffre d'affaires comptable. Le personnel ordinaire ne voit pas ces informations financières.

Chaque événement de commande est enregistré dans une file persistante avec son historique. Le worker envoie ensuite les notifications Filament aux personnes ayant les permissions de consultation, revérifiées avant envoi. Les reprises n'ajoutent pas une seconde notification en base. Les e-mails clients facultatifs contiennent un message de suivi et un lien signé, sans coordonnées complètes ni références bancaires. Ils ne sont activés qu'après configuration de la messagerie : `ORDER_EMAIL_ENABLED=true` et un vrai transport SMTP. Le défaut est `false`, notamment pour éviter l'écriture de liens privés dans le mailer de développement `log`. Les déclarations/rejets financiers internes ne déclenchent pas d'e-mail au client.

Configurer `WHATSAPP_CA` et `WHATSAPP_CM` avec les numéros publics de la boutique au format international sans `+` ni espaces. Sans numéro valide, aucun bouton n'apparaît. Les liens ouvrent WhatsApp manuellement ; aucun message n'est envoyé par l'application. Le lien client de suivi préremplit seulement le numéro de commande, jamais l'URL privée. Le détail staff propose un contact vers le téléphone du client uniquement s'il a fourni un numéro international commençant par `+`.

Les réponses appliquent une politique CSP, la protection contre l'intégration dans une frame, `nosniff`, une politique de référent et une restriction des fonctions navigateur inutilisées. La vitrine utilise des nonces pour ses scripts ; Filament conserve les exceptions nécessaires à Alpine. Les écrans privés et staff sont sans cache et non indexables. Les mutations du panier et les recherches sont limitées en fréquence. Les sessions sont chiffrées par défaut et les cookies deviennent sécurisés par défaut en production ; des variables explicites de votre `.env` existant peuvent remplacer ces valeurs. Les hôtes de production sont limités au domaine d'`APP_URL`.

Exploitation, sauvegardes, file d'attente, configuration HTTPS/messagerie et limites : [guide de déploiement](docs/operations.md). Les détails des livraisons précédentes restent historiques ; cette section remplace leurs limites relatives aux notifications et aux contenus.

## Installation locale

PHP 8.2+ avec extensions Laravel, `intl`, `pdo_mysql`, `fileinfo`, `gd`, `zip`, `exif` ; Composer 2, Node compatible Vite 7 et MySQL 8.

Vérifier `php -v` : sur cette machine, le PATH initial pointe vers PHP 7.4. Utiliser PHP 8.3 pour la session PowerShell :

```powershell
$env:Path = 'C:\laragon\bin\php\php-8.3.2-nts-Win32-vs16-x64;' + $env:Path
composer install
npm ci
# Copier .env.example vers .env uniquement si .env n'existe pas.
# Configurer une base MySQL dédiée et ses identifiants dans .env.
php artisan key:generate # uniquement pour une nouvelle installation sans APP_KEY
php artisan migrate --seed
npm run build
php artisan staff:create
php artisan serve
```

`staff:create` demande nom, e-mail, mot de passe masqué et rôle. Choisir `administrator` pour le premier compte. Mot de passe : au moins 12 caractères, majuscule, minuscule, chiffre et symbole. Commande réservée aux opérateurs autorisés ayant accès au serveur ; ne jamais l'exposer via HTTP. Les créations applicatives passent par `StaffService::create($actor, $data)`, avec contrôle d'autorisation. `make:filament-user` ne donne aucune permission et ne remplace pas cette commande.

Le français est la langue du MVP ; textes publics dans `lang/fr`. Dates stockées en UTC. Aucun identifiant de connexion versionné. Remplacer les adresses fictives et valider les dépôts avant activation. Les permissions du personnel sont globales dans cette phase ; un éventuel périmètre par marché reste à décider avant les workflows opérationnels.

L'autoload Composer n'est pas optimisé en développement pour éviter les scans très longs observés sous Windows. En déploiement : `composer install --no-dev --optimize-autoloader`. Le script Composer republie les assets Filament ; ne pas versionner les assets générés ni `public/build`.

## Vérifications

Les couleurs sont définies dans `resources/css/app.css`, les textes dans `lang/fr/storefront.php` et `lang/fr/shop.php`. Le catalogue et le panier utilisent les données réelles de l'administration.

```powershell
php artisan test
php vendor/bin/pint --test
npm run build
php tools/verify-mysql.php
npm run test:e2e
```

Les tests habituels utilisent SQLite en mémoire sans modifier la base de `.env`. Le script MySQL crée une base temporaire au nom aléatoire avec les paramètres MySQL locaux, vérifie migrations/rollback/seeders et exécute la suite contre cette base, puis la supprime. L'utilisateur MySQL doit avoir les droits CREATE/DROP DATABASE. Ne jamais configurer une base métier pour des tests utilisant `RefreshDatabase`.

Couverture : authentification/permissions, catalogue Filament, stocks et concurrence MySQL, recherche/filtres publics, pagination, prix CAD/XAF, validation, images, archivage, panier invité isolé, changements de marché, CSRF, métadonnées initiales et échappement HTML. Les tests spécifiques MySQL sont ignorés sous SQLite puis exécutés par le script MySQL isolé.

Playwright utilise Microsoft Edge installé, sans téléchargement de navigateur. Avec PHP 8.2+ dans le PATH et un build présent, `npm run test:e2e` démarre un serveur réservé aux tests sur `127.0.0.1:8765` et recrée uniquement `storage/framework/testing/e2e.sqlite`. Il vérifie les pages CMS, le parcours invité jusqu'à l'expédition ou au retrait, le blocage avant paiement, les soldes et le stock final, sur ordinateur et format mobile. Ne pas utiliser ce serveur de test en production. Les données et traces synthétiques sont ignorées par Git. Le script refuse une configuration pointant vers MySQL ou une autre base ; aucun identifiant de test n'est créé dans la base métier.

## Catalogue de démonstration et réseaux sociaux

Un seeder explicite ajoute **12 produits de démonstration**, **22 variantes** et **44 prix CAD/XAF**, répartis entre vêtements, accessoires et bijoux :

```text
php artisan db:seed --class=DemoCatalogSeeder
```

Il est réservé aux environnements `local` et `testing`, exige un compte existant ayant la permission de gérer le catalogue et n'est pas appelé par `DatabaseSeeder`. Les produits sont publiés dans les deux marchés pour prévisualisation, avec des noms/identifiants clairement marqués comme démo et des prix indicatifs indépendants par pays. Aucune adresse fictive n'est activée, aucun compte, stock physique, commande ou paiement n'est créé. Les articles restent indisponibles à la commande tant qu'un stock réel n'est pas saisi. Les photos ne sont pas inventées : le catalogue affiche son état « Photo à venir ».

Le seeder est répétable : les produits existants, y compris leurs modifications et archives, ne sont pas remplacés. Les SKU `DEMO-NZ-*` évitent aussi de recréer un produit dont le slug a été renommé. Les données de démonstration doivent être remplacées/validées ou archivées avant ouverture au public ; changer `APP_ENV` ne les supprime pas.

Les factories permettent également de composer des jeux de test :

```php
use App\Models\Product;
use App\Models\ProductVariant;

Product::factory()->published()->withMarketPrices()->create();
Product::factory()->published()
    ->has(ProductVariant::factory()->count(3), 'variants')
    ->withMarketPrices(['CA' => 8990, 'CM' => 35000])
    ->create();
```

Les montants de ces factories sont en **unités mineures entières** : 8990 CAD mineurs = 89,90 CAD ; 35000 XAF = 35000 FCFA. `MarketPrice::factory()` déduit la devise de son marché. Les factories restent des outils de développement, pas une procédure de création de stock commercial.

Les liens sociaux sont configurés dans `.env`, puis pris en compte après `php artisan config:clear` en développement ou reconstruction du cache de configuration au déploiement :

```dotenv
SOCIAL_FACEBOOK_URL=https://www.facebook.com/profile.php?id=100071095101171
SOCIAL_TIKTOK_URL=
SOCIAL_YOUTUBE_URL=
```

Facebook est configuré avec le lien fourni. TikTok et YouTube restent masqués jusqu'à la saisie de leurs URL HTTPS valides. Les liens apparaissent dans le pied de page noir/or, s'ouvrent dans un nouvel onglet avec `noopener noreferrer` et n'intègrent aucun script ou widget de suivi des plateformes. Le serveur accepte uniquement les domaines officiels prévus pour chaque réseau.

## Suite

Le déploiement GitHub Actions est défini dans `.github/workflows/deploy.yml`, avec tests et compilation avant mise en production par SSH. Les paramètres du serveur fourni, les secrets à ajouter, la préparation Laravel et la procédure en cas d'échec sont détaillés dans [le guide de déploiement GitHub](docs/github-deployment.md). Le fichier GitLab d'origine reste une référence ; ses commandes Prisma/PM2 ne sont pas utilisées.

Le menu public comprend un volet de catégories utilisable au clavier et sur mobile. L’accueil met en avant une photo de la sélection lorsqu’elle existe, conserve les catégories après « À découvrir » et présente les étapes de demande de commande.

Les coordonnées publiques sont configurables : `WHATSAPP_CM=23755278597` (numéro fourni, sans `+` pour le lien `wa.me`) et `SHOP_CONTACT_EMAIL=infos@nhinu-creation.com`. Le bouton WhatsApp affiche un SVG avec trois pulsations discrètes, désactivées lorsque la réduction des animations est demandée. Il ouvre une conversation manuelle ; le lien e-mail du pied de page utilise `mailto:`. Ces réglages sont indépendants du transport des notifications e-mail. Après modification, exécuter `php artisan config:clear` en développement ou reconstruire le cache de production.

Les détails et les vérifications de l’accueil, du menu et des coordonnées figurent dans [ce compte rendu](docs/accueil-contact.md).

Les sept phases du MVP sont implémentées et la recette technique locale est réalisée. La mise en ligne reste soumise à la levée des réserves de configuration, aux vrais contenus/stocks et aux contrôles de l'hébergement cible.

La recette complète et ses réserves sont suivies dans [le compte rendu de recette](docs/recette-mvp.md). `php artisan boutique:preflight` contrôle en lecture seule la configuration et la présence de catalogues commandables par marché ; il retourne 1 tant qu'un prérequis de mise en ligne manque. `--json` produit un résultat sans secrets ni coordonnées personnelles. Ce contrôle ne remplace pas la vérification du TLS, du SMTP et des services sur l'hébergement cible.

`php tools/verify-production.php` vérifie un démarrage avec caches de configuration/routes/vues/événements dans un répertoire temporaire dédié, une clé synthétique et une base SQLite isolée, puis nettoie cet environnement. La base et les caches de travail sont conservés. Le namespace de test est interdit pendant la sonde HTTP de production. La suite MySQL teste séparément le moteur et les accès concurrents.

Le script `composer setup` conserve désormais la clé existante : il ne lance plus `key:generate`. Pour une première installation uniquement, générer explicitement la clé comme indiqué dans les commandes d'installation ; pour un projet déjà exploité, conserver impérativement sa clé d'origine.

Avant production : adresses et points de retrait réels, zones et frais de livraison, moyens et procédures de vérification des paiements, taxes et obligations Canada/Cameroun, délais de réservation et de paiement. Ces règles ne sont pas inventées par le logiciel.

Le détail des changements, commandes exécutées et résultats est dans les comptes rendus de [phase 1](docs/phase-1.md), [phase 2](docs/phase-2.md), [phase 3](docs/phase-3.md), [phase 4](docs/phase-4.md), [phase 5](docs/phase-5.md), [phase 6](docs/phase-6.md) et [phase 7](docs/phase-7.md).
