# Exploitation et préparation du déploiement

## Configuration et mise en service

Déployer le monolithe Laravel avec PHP 8.2+, MySQL 8+, HTTPS et une racine web pointant exclusivement vers `public`. Les répertoires `.env`, `vendor`, `storage` privé, sauvegardes et sources ne doivent pas être servis. Seuls `storage/app/public` et les médias produits y sont volontairement publics via `storage:link`. Les tests et outils E2E ne sont pas des endpoints HTTP et ne doivent pas être exposés.

Configurer `APP_ENV=production`, `APP_DEBUG=false`, une `APP_URL=https://domaine-public`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `QUEUE_CONNECTION=database` et un cache partagé comme `database`. Ne pas élargir le cookie à tous les sous-domaines. Le `.env` local existant n'est pas réécrit par les phases de développement : revoir explicitement ses valeurs de session lors du déploiement.

Conserver `APP_KEY` et ses sauvegardes hors du dépôt, avec accès restreint : perdre/changer cette clé rend les coordonnées chiffrées et sessions illisibles et invalide les liens signés. Ne jamais lancer `key:generate` sur un site déjà exploité. Employer un utilisateur MySQL applicatif aux droits adaptés ; les droits CREATE/DROP DATABASE du script de vérification sont destinés à l'environnement de test séparé.

Le middleware des hôtes accepte le domaine exact d'`APP_URL` en production. Rediriger les autres domaines au niveau du serveur web. Si un proxy termine TLS, configurer uniquement les adresses réelles de vos proxies de confiance via `trustProxies` dans `bootstrap/app.php`, selon l'infrastructure choisie ; ne pas faire confiance à tous les expéditeurs. Vérifier que Laravel voit la requête HTTPS pour les URL signées, cookies et HSTS. HSTS est envoyé sur HTTPS en production, sans imposer tous les sous-domaines.

Après sauvegarde, déployer puis exécuter :

```text
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
php artisan queue:restart
```

Le seeder réapplique la matrice de rôles du code ; conserver les éventuelles personnalisations sous contrôle avant de le lancer. Il n'ajoute aucun compte, produit, paiement ou contenu légal fictif. Créer le premier administrateur avec `php artisan staff:create`, puis vérifier son accès à `/admin`. Les droits sont globaux ; un cloisonnement du personnel par pays n'est pas implémenté.

## Worker, planificateur et notifications

Superviser un worker redémarré automatiquement :

```text
php artisan queue:work --sleep=3 --tries=3 --timeout=60
```

Configurer `php artisan schedule:run` chaque minute (cron ou Planificateur de tâches Windows). Le scheduler libère les réservations dues et réenfile les événements/envois de notification en attente avec un verrou de chevauchement. Les contraintes et transactions rendent les doublons de jobs sans effet sur les stocks et les notifications en base.

Les événements sont enregistrés avec l'historique de commande, puis développés en destinataires après commit. Les payloads en file contiennent des identifiants, sans téléphone, adresse, e-mail client ou lien signé. Les notifications staff contiennent le numéro de commande et un message de suivi ; elles renvoient à une page staff protégée. Les permissions sont revérifiées avant livraison. Retirer un rôle ne supprime pas les anciennes notifications déjà reçues, qui ne contiennent pas les coordonnées du client.

Les commandes suivantes sont réservées à l'opérateur ayant accès au serveur :

```text
php artisan orders:dispatch-notifications
php artisan queue:failed
php artisan orders:retry-notification ID
```

Le tableau de bord indique aussi les événements à préparer avant création des destinataires, les envois en attente et ceux en échec définitif. Le registre `order_notification_deliveries` conserve `status`, `attempts`, `last_error` (classe d'erreur uniquement) et `sent_at`. Après cinq échecs, l'envoi passe à `failed` ; corriger la cause et relancer explicitement son ID. Un envoi réussi ou ignoré n'est pas relancé par cette commande. Les jobs en échec restent consultables via Laravel ; traiter et purger les anciennes entrées après diagnostic.

Configurer un véritable transport SMTP, l'expéditeur et les paramètres fournis par votre service, puis `ORDER_EMAIL_ENABLED=true`. Ne pas l'activer avec `MAIL_MAILER=log` en production : cela écrirait les liens privés dans les logs. Aucun e-mail réel n'est envoyé dans les tests ; la connexion au fournisseur SMTP et la délivrabilité doivent être vérifiées au déploiement. Les e-mails transactionnels ne constituent pas une inscription à une liste marketing.

Un événement client est envoyé uniquement si l'adresse facultative existe et que l'envoi est activé lors de son développement et de sa livraison. Les événements déjà traités sans mail ne sont pas renvoyés rétroactivement lors de l'activation. Un lien signé est généré au moment de l'envoi avec `ORDER_LINK_DAYS` ; il affiche ensuite l'état actuel de la commande. Les événements décrivent l'étape historique et n'affirment pas qu'elle est encore l'état actuel.

Les notifications en base et leur statut d'envoi sont écrits dans la même transaction. L'e-mail traverse un service externe : si SMTP accepte le message puis que le processus s'arrête avant le commit, une reprise peut envoyer un doublon. Il n'existe pas de garantie « exactement une fois » avec SMTP. Les reprises ne peuvent ni enregistrer un paiement ni modifier le stock.

Configurer les numéros publics `WHATSAPP_CA`/`WHATSAPP_CM` sans `+` ni espaces. Le site ne possède aucune API WhatsApp. Chaque message reste ouvert et envoyé manuellement par la personne qui clique ; aucune donnée de carte ni capture de paiement n'est collectée.

## Sécurité et limites techniques

La vitrine applique `script-src` avec nonce et sans `unsafe-eval`. Filament/Livewire reposent sur Alpine et conservent `unsafe-inline`/`unsafe-eval` pour les scripts de l'administration ; ne pas présenter cette politique comme une CSP stricte pour tout le site. Les styles inline nécessaires aux composants restent autorisés. Les polices système et les avatars SVG générés localement évitent tout appel à un fournisseur externe avec les initiales du personnel. Objets et frames sont interdits ; les fonctions caméra, micro et géolocalisation sont désactivées. Les écrans privés sont sans cache ni référent.

Les liens de confirmation et les traces de navigateur doivent rester privés. Le CMS rend le texte comme du texte, sans HTML riche ni scripts ; les boutons de bannières n'acceptent que des chemins publics internes. Les médias produits restent soumis aux formats et limites du module catalogue. Les secrets, références bancaires et notes internes ne sont pas partagés dans les props publiques.

Les limitations par IP complètent CSRF et les contrôles d'autorisation, sans remplacer une protection réseau contre un déni de service. La connexion Filament conserve sa limitation des tentatives. Le MVP ne comprend pas de MFA ; décider de cette exigence avec l'exploitation avant ouverture de l'administration sur Internet. Garder les dépendances à jour et exécuter `composer audit` et `npm audit` régulièrement.

## Sauvegardes, incidents et validation métier

Sauvegarder MySQL, les médias publics et `APP_KEY` avec chiffrement et accès restreint ; définir une rétention et tester une restauration sur un environnement isolé. Les sauvegardes et journaux peuvent contenir des données personnelles : ne pas les déposer dans `public` ou Git. Les migrations de phase 6 refusent un rollback qui détruirait l'historique financier ; préférer une migration corrective. Ne pas lancer `migrate:fresh`, `db:wipe` ou les fixtures sur une base métier.

Contrôler `/up`, la santé du worker et du scheduler, les échecs d'envoi, les stocks faibles et les commandes payées sans remise. Ne pas transférer implicitement les stocks entre pays ou dépôts. En cas de versement tardif, annulation ou trop-perçu, traiter explicitement la réactivation ou le remboursement. Une remise consommée ne restaure pas son stock après remboursement ; vérifier physiquement tout retour.

Avant ouverture : renseigner les vraies adresses, lieux de retrait, frais et procédures de livraison, coordonnées publiques, modalités et références de paiement, délais de réservation/paiement, contenus éditoriaux et obligations de confidentialité/consommation/taxes au Canada et au Cameroun. Le logiciel ne fournit aucun texte légal ou calcul fiscal présumé conforme. Fixer la politique de conservation des données avec les personnes responsables de cette revue.

## Vérifications reproductibles

```text
php artisan test
php vendor/bin/pint --test
npm run build
php tools/verify-mysql.php
php tools/verify-production.php
php artisan boutique:preflight
npm run test:e2e
composer audit --no-interaction
npm audit
```

PHP 8.2+ doit précéder les anciennes versions dans le PATH. Playwright utilise Edge installé ; adapter le canal du navigateur dans `playwright.config.ts` sur un autre environnement. Le port 8765 doit être libre. Aucune instance déjà démarrée n'est réutilisée.

Le serveur E2E exige `APP_ENV=testing`, `E2E_TESTING=1` et le chemin exact du fichier SQLite de test ; il refuse MySQL et les autres chemins avant toute migration destructive. Le compte et mot de passe E2E fixes ne sont créés que dans cette base ignorée, entièrement synthétique. Les traces/screenshots de tests sont dans `storage/framework/testing`, jamais dans les médias publics. Le script MySQL utilise une base aléatoire `nzhinu_verify_*`, puis la supprime même en cas d'échec.

Le contrôle `boutique:preflight` est en lecture seule pour les données métier. Il vérifie les réglages de production, la validité de la clé sans la modifier, les sessions, la file/cache, le build, les rôles et marchés et au moins un produit commandable avec stock physique local par pays. Il ne publie rien et ne crée aucun stock. Les e-mails peuvent rester volontairement désactivés ; si activés, leur transport doit être réel. Un résultat négatif dans l'environnement local est attendu pour les réglages réservés à la production. Ne pas passer le poste de développement en production simplement pour faire disparaître ces réserves.

Le contrôle `verify-production.php` utilise des caches distincts et une clé générée exclusivement pour ses données synthétiques. Il compile configuration/routes/vues/événements et vérifie les réponses HTTP, les cookies HTTPS et le rejet d'un hôte non autorisé via le noyau Laravel. Il ne fournit ni certificat TLS réel ni connexion au SMTP et ne remplace pas un essai de déploiement avec `composer install --no-dev` sur la cible. Les fichiers temporaires sont supprimés uniquement après vérification de leur chemin dans le répertoire des tests.

Le script `composer setup` n'effectue plus de rotation implicite de clé. Pour une installation neuve uniquement, générer la clé explicitement ; pour une installation existante, restaurer la clé d'origine avant de réutiliser ses données chiffrées.
