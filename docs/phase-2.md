# Compte rendu — phase 2 : catalogue

## Changements

- Migration `2026_10_26_030000_create_catalog_tables` : catégories, collections culturelles, produits, pivot des collections, variantes, `market_prices` et `market_products`. SKU, slugs et paires marché/variante et marché/produit uniques. Clés étrangères restrictives, prix entiers non négatifs sur MySQL, archivage des entités catalogue et variantes.
- Modèles et relations dans `app/Models`, enum `ProductStatus`, factories, et `CatalogTaxonomySeeder` répétable. Aucune création de produits fictifs dans la base de développement.
- `CatalogService` : validation, autorisation, transactions, création d'une variante par défaut, synchronisation des variantes/prix/publications, archivage des variantes retirées, verrou du produit et compteur de version contre les sauvegardes obsolètes. Validation des identifiants de variantes appartenant au produit. Le SKU reste réservé après archivage.
- `MoneyInput` : conversion décimale exacte CAD/XAF, sans flottants, devise déduite du marché. Une publication exige un prix par variante active et marché activé. `Product::availableIn()` fournit l'éligibilité catalogue, sans calcul de stock.
- `ProductMediaService` : contrôle d'accès, images raster validées, limite de taille/dimensions, noms générés et texte alternatif, suppression avec contrôle de propriété du média.
- `CatalogPolicy` et permissions `view catalog` / `manage catalog`. Administrateurs et responsables : modification ; personnel : consultation.
- Trois ressources Filament dans `app/Filament/Resources` : listes, recherche/filtres, création, consultation, modification et archivage. Variantes, prix et marchés dans le formulaire produit. Actions d'ajout/suppression d'images et galerie. Les classes dans `app/Filament/Support` raccordent les formulaires aux services ; les règles métier restent dans les services. Les erreurs de listes déshydratées sont remappées vers les clés UUID des champs Filament.
- Tests fonctionnels dans `tests/Feature/CatalogTest.php`, adaptation du comptage des permissions en phase 1 et mise à jour du README.

## Commandes et validation

Toutes les commandes PHP ont utilisé PHP 8.3.2 via le PATH de la session. Le `.env` et les comptes existants sont conservés.

```text
git status --short
php artisan migrate --seed --no-interaction
php artisan test --compact
php artisan test --compact --filter=CatalogTest
php tools/verify-mysql.php
php artisan storage:link
php vendor/bin/pint
php vendor/bin/pint --test
npm run build
git diff --check
```

La migration catalogue et les seeders ont été appliqués à la base MySQL configurée. La vérification MySQL isolée a créé une base temporaire, installé toutes les migrations, exécuté deux fois les seeders, réalisé un rollback complet puis réinstallé le schéma avant la suite : **47 tests réussis, 252 assertions**. La base temporaire a été supprimée. Le test MySQL vérifie notamment le refus d'un montant négatif par la contrainte SQL.

Suite SQLite finale : **46 tests réussis, 1 ignoré, 223 assertions** ; le test ignoré concerne la contrainte SQL propre à MySQL, validée dans la suite MySQL. Les sept migrations sont appliquées à la base configurée.

Pint : succès. Compilation TypeScript et Vite : succès, 695 modules, 14,51 s pour Vite. Le lien `public/storage` vers `storage/app/public` a été créé. Les tests passent par les services et les pages/actions Livewire réelles, notamment deux sauvegardes successives du même formulaire et l'envoi d'une image.

Une commande exploratoire `route:list --compact` a été refusée car cette option n'existe pas ; les routes et autorisations ont ensuite été exercées par les tests HTTP et Livewire. Pas de nouvelle dépendance installée pour cette phase.

## Limites et suite

Les images du disque public sont accessibles par URL, même pour un brouillon ; ne pas y déposer de documents privés. Les images sont ajoutées après création du produit par une action distincte, hors de la transaction de sauvegarde du catalogue. L'archivage conserve les images ; leur suppression explicite supprime les fichiers.

Les identités des marchés/devises restent fixes. Les règles fiscales, l'inventaire, la réservation physique et les commandes sont hors phase 2. La sauvegarde d'un produit est une opération sur l'ensemble du formulaire ; le compteur de version empêche les écrasements silencieux. Les contraintes MySQL protègent aussi les écritures concurrentes sur les clés uniques ; les tests de concurrence d'inventaire sont prévus en phase 3.

Les écrans proposent l'archivage ; la restauration et la suppression définitive ne sont pas exposées. Pour modifier un produit lié à une catégorie ou collection devenue inactive, sélectionner une catégorie active ou retirer la collection concernée. Les références historiques restent conservées.

Aucun commit effectué. Arrêt pour revue avant **phase 3 : inventaire physique, réservation/libération et tests de concurrence**.
