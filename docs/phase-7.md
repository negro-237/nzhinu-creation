# Compte rendu — phase 7

## Modules livrés

- Migration `2026_10_26_070000_create_content_and_notification_outbox` : pages CMS, bannières, événements et destinataires de notifications. Références uniques par historique et destinataire/canal, clés étrangères, versions de contenu et archivage. Aucun contenu éditorial, numéro WhatsApp ou compte de démonstration dans le seeding métier.
- `ContentService`, policies et ressources Filament : création en brouillon, publication générale ou par marché, dates UTC et ordre des bannières, liens strictement internes, contrôle de version et archivage. Rendu React en texte simple avec métadonnées SEO et pied de page public, sans HTML exécutable. Identité noir/or et logo conservés.
- `OrderNotifications`, `OrderEventKind`, deux jobs et `OrderUpdateNotification` : événement enregistré avec l'historique, développement des destinataires après commit, notification Filament et e-mail facultatif. Les transitions de commande, vérification des paiements/remboursements, préparation et remise alimentent ce journal. Pas d'e-mail client pour les déclarations/rejets financiers internes. Le payload des jobs ne contient que des identifiants et les contenus restent minimaux.
- Reprise planifiée des envois oubliés après un arrêt, verrou de livraison et UUID de notification stable, recontrôle des permissions staff, limite de cinq échecs puis relance CLI explicite. Les notifications en base sont atomiques avec leur statut ; les doublons SMTP en cas d'arrêt après acceptation externe sont documentés.
- `WhatsAppContact` : numéro public configurable par marché, lien manuel de la boutique et de la confirmation privée, lien staff vers le client uniquement pour un numéro international explicite. Aucun message automatique ni signature privée transmis à WhatsApp.
- `OperationsDashboard` et widget Filament : volumes de commandes, fonds reçus/remboursés/nets par marché et devise, revue financière, stocks faibles et état des notifications. Autorisation réservée par défaut aux administrateurs et responsables. Les soldes couvrent aussi les fonds de commandes annulées/expirées et ne sont pas présentés comme un chiffre d'affaires.
- `SecurityHeaders` : CSP avec nonce sur la vitrine, exceptions Alpine limitées aux routes staff/Livewire, protection anti-frame, `nosniff`, politique de référent, interdiction des fonctions navigateur inutiles, HSTS sur HTTPS en production et absence de cache sur les réponses privées/staff/Livewire. Prise en compte des routes Livewire à préfixe généré de la version installée.
- Sessions chiffrées et cookies HTTPS par défaut en production, domaine de production limité à `APP_URL`, champs sensibles exclus du flash de validation, limitations de fréquence du panier et du catalogue. Les valeurs explicites du `.env` local restent préservées. Polices système et avatars SVG locaux pour éviter les appels tiers contenant les initiales du personnel.
- Tests fonctionnels CMS, notifications, tableaux de bord et sécurité ; processus MySQL concurrents pour une notification unique. Playwright/Edge teste le CMS et les parcours invités jusqu'à la remise avec données SQLite isolées, sur ordinateur et mobile. Les scripts refusent la base métier avant toute recréation de schéma. Les traces restent ignorées par Git.
- README et [guide d'exploitation](operations.md) : déploiement, SMTP, worker/scheduler, reprise des échecs, sauvegardes/restauration, HTTPS/proxy, contraintes de rollback, données personnelles, limites et revue métier Canada/Cameroun.

## Commandes et vérifications

PHP 8.3.2 utilisé dans le PATH de la session ; Edge installé sur la machine, sans téléchargement de navigateur supplémentaire.

```text
git status --short
php artisan test --compact --filter="GuestOrderTest|ManualPaymentTest"
php artisan test --compact --filter="ContentTest|OrderNotificationTest|OperationsSecurityTest"
php artisan test --compact
php artisan test --compact --filter=OperationsSecurityTest
php tools/verify-mysql.php
php artisan migrate --seed --no-interaction
php artisan migrate:status
php artisan schedule:list
php vendor/bin/pint
php vendor/bin/pint --test
npm install --save-dev @playwright/test
npm run build
npm run test:e2e
composer audit --no-interaction
npm audit
git diff --check
```

- Régression commandes/paiements : **35 tests réussis, 281 assertions**, 16,34 s.
- Nouveaux modules ciblés : **13 tests réussis, 195 assertions**, 22,78 s.
- Suite SQLite complète finale : **133 réussis, 14 ignorés, 1351 assertions**, 59,78 s. Les ignorés concernent MySQL.
- Suite MySQL : **147 tests réussis, 1576 assertions**, 331,19 s, avec migrations fraîches, double seeding, rollback et réinstallation. La base temporaire a été supprimée. Le nom d'une contrainte initialement trop long pour MySQL a été raccourci avant validation finale ; le script retourne désormais un échec explicite en cas d'exception de préparation.
- Derniers ajustements de confidentialité staff et suivi de la file : **4 tests réussis, 104 assertions**, 3,51 s, après retrait des polices/avatars tiers et ajout du compteur des événements non développés. Les nouvelles protections Livewire sont aussi vérifiées dans le navigateur.
- Build TypeScript/Vite : **704 modules, 27,05 s**. Formatage Pint et `git diff --check` réussis.
- Audit Composer : aucun avis de vulnérabilité trouvé. Audit npm : **0 vulnérabilité**, également vérifié après installation (149 paquets).
- Migration appliquée à la base du projet et permissions mises à jour : **11 migrations appliquées**. Le `.env` et les données commerciales existantes n'ont pas été réécrits. Aucun e-mail ou WhatsApp réel envoyé pendant les tests.
- Parcours navigateur finaux : **6 scénarios réussis, 3,5 minutes**, Edge sur ordinateur et format mobile. Dans chaque format : CMS publié et changement de marché, absence d'exécution des balises, refus d'une requête sans jeton CSRF, demande invitée puis paiement déclaré/vérifié, préparation, expédition ou retrait, absence de remise avant paiement, consultation privée et refus sans signature. Vérification directe après chaque remise : une seule consommation, stock physique attendu, réservation consommée et notifications staff présentes.

Le serveur navigateur garde CSRF activé malgré son environnement `testing` ; une requête sans jeton est rejetée avec 419. Les formulaires Inertia et Livewire passent ensuite avec leurs jetons réels. Les tâches d'expiration et de reprise des notifications sont présentes dans `schedule:list`, toutes deux chaque minute.

## Points d'exploitation

`ORDER_EMAIL_ENABLED=false` par défaut. Configurer un vrai SMTP et son expéditeur, puis activer explicitement les notifications clients ; la délivrabilité réelle doit être validée avec le fournisseur. Les envois désactivés ne sont pas rattrapés rétroactivement. Le scheduler et le worker restent nécessaires pour notifications et expirations. Les alertes de stock faible de phase 3 sont conservées.

Renseigner les vrais numéros WhatsApp et contenus publics depuis l'exploitation ; aucun numéro ni texte légal n'est inventé. Les permissions sont globales et aucune MFA n'a été ajoutée. Revoir ces exigences, les durées de conservation et les procédures fiscales/juridiques par marché avant production. Les tests de format mobile utilisent Edge avec une fenêtre mobile ; ils ne constituent pas une certification sur tous les appareils iOS/Android.

Les scripts de test, comptes fixes et traces sont exclusivement dédiés aux bases synthétiques. Aucun commit, publication ou déploiement effectué. Arrêt pour revue de la phase 7 et préparation de la recette du MVP.
