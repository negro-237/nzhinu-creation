# Recette fonctionnelle du MVP

## Périmètre et décision

Recette locale du monolithe Laravel, des deux marchés CA/CAD et CM/XAF, de la vitrine mobile/ordinateur et des workflows Filament. Les données de scénario restent isolées : SQLite en mémoire pour les tests HTTP, base MySQL aléatoire pour les contraintes/concurrences, SQLite dédiée pour Edge et environnement temporaire séparé pour le démarrage de production. Aucun versement, e-mail ou message WhatsApp réel, aucune modification du `.env` métier et aucun déploiement.

La décision finale distingue **logiciel vérifié localement** et **configuration d'exploitation prête à ouvrir**. Les résultats sont détaillés ci-dessous ; les réserves de déploiement ne sont pas masquées en modifiant les réglages du poste local.

## Matrice de couverture

| Domaine | Scénarios vérifiés | Preuves |
|---|---|---|
| Authentification | connexion/déconnexion, mauvais mot de passe, compte sans accès, absence d'inscription publique, rôles | FoundationTest, navigateur administrateur/responsable/personnel |
| Catalogue | création/édition/archivage, variantes et SKU, prix CAD/XAF, publication par marché, images autorisées/alternatives et formats refusés | CatalogTest, StorefrontTest |
| Stocks | disponibilité par pays et dépôt, seuils, réservations/libération/expiration, conservation de l'historique | InventoryTest, InventoryConcurrencyTest |
| Vitrine | logo, pages CMS, bannière, filtre/recherche/pagination, fiches, absence d'exécution HTML, format mobile sans débordement | ContentTest, StorefrontTest, captures Edge |
| Panier | quantités absolues et reprises, isolement des sessions, prix recalculés, invalidation d'un article, confirmation/annulation du changement de marché | StorefrontTest, Edge |
| Demande invitée | coordonnées requises, e-mail facultatif, livraison/retrait, point compatible, snapshots et chiffrement, panier obsolète | GuestOrderTest, Edge CA et CM |
| Validation staff | appel et total définitif, actions obsolètes, annulation/réactivation avec recontrôle de stock | GuestOrderTest, ManualPaymentTest |
| Paiements | déclaration sans effet sur le net, validation/rejet, contrôle indépendant, partiels, remboursements, références uniques et reprises | ManualPaymentTest, AcceptanceRegressionTest, Edge XAF partiel |
| Remise | préparation/remise refusée avant couverture vérifiée, expédition et retrait par marché, une consommation physique exacte | ManualPaymentTest, concurrence MySQL, Edge et vérification des stocks après chaque remise |
| Échecs sensibles | fonds tardifs, expiration partielle, annulation payée, remboursement avant/après remise, absence de restauration implicite | GuestOrderTest, ManualPaymentTest, courses MySQL |
| Notifications | événements transactionnels, reprises, droits revérifiés, absence de données privées dans les jobs, échecs bornés et retry | OrderNotificationTest, course MySQL, AcceptanceRegressionTest |
| CMS et suivi | publication différée, restrictions par marché, archivage, version, liens internes, séparation des devises des tableaux de bord | ContentTest, OperationsSecurityTest, Edge |
| Sécurité HTTP | CSRF réel, liens signés, refus sans signature, CSP/nonce, confidentialité des réponses, limitation de fréquence | tests HTTP, Edge, sonde production |
| Exploitation | migrations/rollback/double seed, build, audits, caches, hôtes et cookies de production | verify-mysql, verify-production, preflight, Pint, Composer/npm |

Les vérifications navigateur complètent les tests HTTP et de services ; elles ne simulent pas un fournisseur bancaire, un transporteur, un SMTP externe ou tous les appareils mobiles physiques.

## Corrections issues de la recette

1. **Clé de chiffrement :** `composer setup` ne doit pas régénérer une clé existante, sous peine de perdre l'accès aux coordonnées chiffrées et d'invalider les liens signés. Suppression de cette génération automatique ; première initialisation explicite documentée. Cohérence Composer validée sans exécuter ce script sur le projet métier.
2. **Alerte de stock différée :** les canaux des notifications Laravel sont déjà figés dans le job. Ajout de `shouldSend` pour revérifier les permissions au moment de l'exécution ; un rôle retiré après mise en file ne reçoit plus l'alerte. Régression vérifiée avec un job Laravel sérialisé puis restauré après modification du rôle.
3. **Référence de paiement :** certains caractères Unicode s'allongent lors du passage en majuscules. La longueur est désormais vérifiée après normalisation pour éviter une troncature MySQL puis une erreur serveur. Le rejet ne laisse ni paiement ni opération partielle.
4. **Quotas de requêtes :** les limites numériques anonymes du suivi et de la création partageaient la même clé par IP. Des consultations pouvaient ainsi déclencher un refus 429 de commande. Deux limiteurs nommés indépendants maintiennent désormais 5 créations/minute et 30 consultations/minute. Les régressions vérifient l'indépendance et le maintien de chaque plafond ; les limites n'ont pas été relevées pour masquer le problème.
5. **Récapitulatif après confirmation :** la page n'annonce plus systématiquement un prochain appel ni des frais restant à confirmer après remise. Titre et introduction suivent le statut actuel, et une zone non précisée reste décrite de façon neutre. Le message de notification de création renvoie aussi à l'état courant sans promettre un appel futur si son envoi est différé.
6. **Couverture des deux pays :** extension des fixtures et parcours navigateur au Cameroun, avec XAF entier, dépôt/point de retrait distinct et deux versements partiels pour la livraison. Les contrôles de stock s'effectuent séparément pour chaque inventaire. Vérifications des droits du responsable et du personnel, et changement de marché avec panier rempli.
7. **Préparation opérationnelle :** ajout de `boutique:preflight`, sans affichage de secrets, et d'un test de démarrage/caches de production entièrement isolé. Les chemins de cache relatifs tiennent compte du fonctionnement de Laravel sur Windows.

## Résultats

Commandes exécutées avec PHP 8.3.2 dans le PATH :

```text
php artisan test --compact
php artisan test --compact --filter=AcceptanceRegressionTest
php artisan test --compact --filter=DeploymentReadinessTest
php artisan test --compact --filter="AcceptanceRegressionTest|DeploymentReadinessTest"
php tools/verify-mysql.php
php tools/verify-production.php
php artisan boutique:preflight --json
php vendor/bin/pint
php vendor/bin/pint --test
npm run build
npm run test:e2e
composer audit --no-interaction
npm audit
composer validate --strict
git diff --check
```

- Référence avant corrections : 133 tests SQLite réussis, 14 ignorés, 1351 assertions (49,22 s).
- Audits Composer/npm : aucun avis de vulnérabilité connu ; `composer validate --strict` valide.
- Parcours ciblé CM/livraison avec deux paiements : réussi dans Edge (1,8 min avec préparation).
- Sonde production : migrations et seeders, caches de configuration/routes/vues/événements réussis ; réponses 200 sur `/up`, `/`, `/boutique`, `/admin/login`, HSTS et cookies sécurisés, hôte non autorisé rejeté 400 ; namespace `Tests` interdit pendant le démarrage HTTP. Environnement temporaire supprimé.
- Build : 704 modules, 32,59 s, TypeScript réussi.
- Régressions et précontrôle avec l'inventaire existant : 23 tests réussis, 138 assertions (23,58 s).
- Suite SQLite globale après correction des quotas et du suivi : **140 réussis, 14 ignorés, 1440 assertions**, 73,27 s. Les ignorés concernent MySQL.
- Suite MySQL globale après ces corrections : **154 tests réussis, 1670 assertions**, 310,12 s ; migrations fraîches, rollback et double seeding validés, base temporaire supprimée.
- Complément sur le précontrôle après exclusion explicite des produits/variantes/dépôts archivés : **2 tests réussis, 24 assertions**, 3,86 s. Les archives ne sont pas comptées comme un catalogue commandable.
- Exigences de plateforme Composer hors développement : toutes satisfaites avec PHP 8.3.2.
- Formatage Pint, validité Composer et `git diff --check` réussis.
- Build final avec le texte de suivi corrigé : **704 modules, 7,02 s**, TypeScript réussi.
- Recette navigateur finale : **12 scénarios réussis, 8,2 minutes**, Edge ordinateur et format mobile. Chaque format couvre CMS/CSRF/logo, annulation puis confirmation du changement de marché avec panier, CA livraison/retrait, CM livraison/retrait, vérification des rôles et XAF partiel. Les récapitulatifs après remise affichent le suivi actuel, le net payé et un solde nul ; une seule consommation de stock est contrôlée par inventaire. Les captures d'accueil et de suivi ont été inspectées ; aucun débordement horizontal constaté, logo et noir/or conservés.

## Réserves avant mise en ligne

Le précontrôle de la configuration actuelle retourne **non prêt pour ouverture**, ce qui est normal sur le poste local :

- `APP_ENV` et `APP_DEBUG` restent adaptés au développement ; URL locale sans HTTPS public.
- Cookies de session non sécurisés et chiffrement de session désactivé par les valeurs explicites du `.env` actuel. Corriger sur la cible HTTPS, sans réinitialiser `APP_KEY`.
- Aucun catalogue actuellement commandable avec stock local validé pour CA ou CM. Publier les vrais produits, définir leurs prix et disponibilités et initialiser les stocks dans les vrais dépôts avant ouverture des marchés.
- E-mails facultatifs désactivés : configurer et tester un SMTP réel avant activation si ce canal est souhaité. Renseigner les numéros WhatsApp publics si souhaité.
- Vérifier sur l'hébergement : domaine/certificat TLS et proxy, droits et racine `public`, build et médias, worker supervisé, scheduler chaque minute, envoi SMTP réel et sauvegarde/restauration.
- Faire valider les contenus, frais, modalités de paiement/livraison/retrait, fiscalité, confidentialité et durées de conservation propres au Canada et au Cameroun.

La clé actuelle, la connexion MySQL, le schéma du MVP, les marchés/devises, la présence d'un administrateur, le cache/la file persistants et les fichiers de build/logo sont reconnus par le contrôle. Celui-ci n'expose ni valeurs de secrets ni coordonnées des comptes/clients. Le contrôle ne certifie pas la délivrabilité SMTP ou la supervision des services à distance.

## Verdict de recette

**Recette technique locale validée, avec réserves d'exploitation avant ouverture publique.** Les cinq défauts fonctionnels/sécurité décrits dans les points 1 à 5 sont corrigés et couverts par les régressions concernées. Les tests de stock et de paiement, les deux pays, les parcours réels sur ordinateur/mobile, les audits, le build et les caches de production passent.

**La configuration actuelle n'est pas déclarée prête pour production.** Les sept prérequis bloquants signalés par `boutique:preflight` (environnement, débogage, URL HTTPS, cookies sécurisés, sessions chiffrées, catalogue commandable CA, catalogue commandable CM) doivent être levés sur la cible ou dans les données métier réelles. Les vérifications externes de la section précédente restent à réaliser sur l'hébergement. Ne pas ouvrir le site au public simplement sur la base des tests locaux.

Modifications principales : `composer.json`, alerte `LowStockNotification`, `ManualPaymentService`, limiteurs `AppServiceProvider` et routes publiques, `OrderAccess`/`OrderConfirmation`/métadonnées/traductions, précontrôle `DeploymentReadiness` et commande associée, outils de sonde production, fixtures et scénarios Edge CA/CM, tests de régression et documentation. Aucune nouvelle dépendance ; données métier et `.env` conservés. Aucun commit ni déploiement effectué.
