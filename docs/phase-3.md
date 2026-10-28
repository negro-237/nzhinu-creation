# Compte rendu — phase 3 : stocks et réservations

## Périmètre livré

- Migration `2026_10_26_040000_create_inventory_tables` : `inventories`, `inventory_reservations`, `reservation_items`, `inventory_operations`, `stock_movements`, `reservation_histories`, notifications Laravel. Contraintes de non-négativité, réservé inférieur ou égal au physique, unicité lieu/variante, clés étrangères restrictives et indexes pour les expirations. Les contraintes de quantités existent aussi sous SQLite via triggers.
- Modèles, relations avec lieux/variantes, enums PHP `ReservationStatus` et `StockMovementType`, configuration `inventory.reservation_hours` et exemple `.env` (défaut 24 heures). Le `.env` existant n'a pas été modifié.
- `InventoryService` et `InventoryOperationService` : initialisations et corrections autorisées, validation des quantités/motifs, clé UUID liée à l'acteur et aux données, résultat réutilisé lors des reprises, transactions et verrouillage. Pas de corrections sous les quantités réservées.
- `ReservationService` : réservation de plusieurs lignes à un marché et des stocks explicitement choisis ; vérification des lieux/variantes/publications/prix, verrouillage individuel des stocks par ID croissant et transaction atomique. Réservation/libération/expiration ne décrémentent pas le physique. Réactivation avec contrôle du stock et nouveau cycle. Une reprise de création expirée ne réactive pas implicitement la réservation ; une reprise de libération d'un ancien cycle ne touche pas le nouveau cycle.
- `InventoryLedger` : variations physiques/réservées et quantités résultantes, acteur, motif et référence de réservation. Historique des transitions séparé, avec numéro de cycle.
- `StockLocationService` : validation d'adresse avant activation, marché immuable, refus d'archivage des lieux non vides ; création de stocks interdite dans les lieux fictifs. Les dépôts exemples existants restent fictifs et désactivés, sans ajout de stock de démonstration.
- Job `ReleaseExpiredReservations`, commande `inventory:release-expired` avec option `--sync`, planification chaque minute. Verrous/statuts rendent les exécutions concurrentes et les reprises idempotentes.
- `LowStockNotification` : notification Filament en file après commit aux utilisateurs ayant la permission de gestion de stock. Une alerte par passage au seuil ; remontée au-dessus du seuil nécessaire pour réarmer. Activation des notifications de base de données dans le panneau.
- Permissions/policies : consultation des stocks pour le personnel, gestion des stocks/réservations pour responsables et administrateurs. Policies distinctes pour réservations et journal ; aucune modification/suppression du journal par les ressources.
- Ressources Filament du groupe Stocks : lieux de stockage, stocks physiques (initialisation/correction/seuil), réservations (création, consultation des lignes/historique, libération, réactivation), journal des mouvements. Recherche et filtres, dont stock faible. Raccordement des erreurs des services aux formulaires et notification d'erreur lisible.
- Tests `InventoryTest`, fixture réutilisable, tests MySQL multiprocessus `InventoryConcurrencyTest` et worker limité explicitement à la base temporaire de vérification. README opérationnel mis à jour.

## Commandes exécutées

PHP 8.3.2 utilisé via le PATH de la session, car le terminal initial pointe vers PHP 7.4.

```text
git status --short
php artisan migrate --seed --no-interaction
php artisan test --compact --filter=InventoryTest
php artisan test --compact
php tools/verify-mysql.php
php vendor/bin/pint
php vendor/bin/pint --test
php artisan schedule:list
php artisan migrate:status
npm run build
git diff --check
```

## Résultats

- Suite MySQL finale : **71 tests réussis, 434 assertions**, 45,30 s. Base temporaire créée puis supprimée ; migrations fraîches, double exécution des seeders, rollback complet et réinstallation réussis.
- Cinq tests de concurrence réelle : deux réservations de la dernière unité, deux requêtes avec la même clé, libération contre expiration, réservations multilignes avec ordre d'entrée inversé, correction physique contre réservation. Deux processus indépendants attendent le verrou de stock détenu par le processus parent avant de poursuivre. Aucun dépassement de stock ni double libération ; les requêtes invalides ne laissent aucune réservation partielle.
- Suite SQLite finale : **65 réussis, 6 ignorés, 332 assertions**, 9,04 s. Les six tests ignorés sont les cinq tests MySQL multiprocessus et la contrainte de prix MySQL de phase 2, tous validés dans la suite MySQL.
- Pint : succès. TypeScript/Vite : succès, 695 modules, 17,32 s pour Vite. `schedule:list` confirme le job chaque minute. Les huit migrations sont appliquées dans la base configurée.
- Le premier test d'échéance exacte a révélé une différence de précision : les timestamps SQL sont à la seconde tandis que l'horloge de test conservait des microsecondes. Le test utilise désormais une horloge figée à la seconde ; les tests SQLite et MySQL passent.

## Exploitation et limites

Exécuter le planificateur Laravel et un worker de queue : `php artisan schedule:work` et `php artisan queue:work --tries=3 --timeout=60` en développement. En production, programmer `php artisan schedule:run` chaque minute et superviser le worker. Ces processus permanents n'ont pas été démarrés par cette intervention. Sans eux, les expirations automatiques et notifications en file ne seront pas traitées. Un rattrapage est possible avec `php artisan inventory:release-expired --sync`.

La réservation est actuellement un outil interne pour le personnel. La création atomique avec la commande invitée sera raccordée en phase 5, ainsi que l'échéance de paiement après confirmation. La consommation physique lors de la remise et la vérification du paiement intégral seront ajoutées ensemble en phase 6. Aucun flux de remise, paiement, remboursement ou transfert international n'est ajouté dans cette phase. Les corrections physiques sont réservées aux réceptions et écarts de comptage, avec motif obligatoire.

Les permissions restent globales aux deux pays. Une variante ou un lieu désactivé ne peut plus servir à une nouvelle réservation ou réactivation ; les réservations existantes peuvent toujours être libérées, y compris si le catalogue a été archivé. L'expiration devient effective au passage du job ; elle ne libère pas du stock simplement parce que l'échéance est dépassée dans une requête de lecture.

Les clés de reprise empêchent les doubles effets mais ne rejouent pas une ancienne réponse figée : les services retournent l'état actuel de l'objet déjà traité. Une ancienne clé de réservation ne prolonge donc jamais son échéance. Les mouvements et historiques sont conservés et consultables, sans écran d'édition/suppression.

Aucun commit effectué. Arrêt pour revue avant **phase 4 : boutique publique, recherche/filtres, fiches produits et panier de session**, en conservant l'identité visuelle fournie.
