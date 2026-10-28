# Catégories sur la page d’accueil

La section **Explorez nos catégories** suit directement **À découvrir**. Chaque carte affiche une icône SVG décorative, le nom de la catégorie et sa description si elle est renseignée. Le lien mène au catalogue filtré (`/boutique?category=…`). La grille s’adapte au mobile et reprend les animations natives avec respect du mouvement réduit.

Les catégories viennent de la base existante. Seules les catégories actives, non archivées, possédant au moins un produit publié et disponible dans le catalogue public du marché courant sont proposées. Un stock physique nul n’empêche pas de découvrir une catégorie. Les catégories vides, désactivées ou uniquement proposées sur un autre marché ne sont pas mises en avant. La section est absente si aucune catégorie ne remplit ces critères.

## Gestion dans l’administration

Catalogue → Catégories → Modifier :

- **Nom** : utilisé sur la carte et dans les filtres.
- **Description** : facultative ; laisser vide pour une carte sans texte descriptif.
- **Icône illustrative** : vêtement, sac/accessoires, bijou ou icône générale. Les valeurs sont validées côté serveur ; aucun SVG ou HTML fourni par le personnel n’est exécuté.

La migration ajoute uniquement un champ nullable `categories.icon` ; le champ `description` existait déjà. Aucune catégorie supplémentaire, dépendance frontend ou image distante n’est nécessaire.

## Mise à jour des catégories existantes

```bash
php artisan migrate
php artisan db:seed --class=CatalogTaxonomySeeder
```

Vêtements, Accessoires et Bijoux reçoivent leur icône et une courte description par défaut lors de leur création. Les catégories anciennes sans icône sont enrichies une fois. Les noms et descriptions déjà renseignés, les icônes choisies et les catégories désactivées ou archivées sont conservés. Une description retirée après l’initialisation n’est pas réintroduite par un nouveau passage du seeder tant que l’icône reste choisie.

Les nouvelles catégories ou les catégories sans icône reconnue bénéficient d’une icône générale dans la vitrine.

## Contrôles

`HomeCategoriesTest` couvre l’éligibilité, le filtrage par marché, la description optionnelle, l’icône de secours, les mises à jour répétées, la validation des icônes, les permissions et les formulaires Filament. Le test navigateur `categories.spec.ts` vérifie la position après les produits, le rendu de l’icône et de la description, la navigation filtrée et l’absence de débordement horizontal sur desktop/mobile.

Fichiers principaux : modèle Category, migration du champ icon, configuration des icônes, TaxonomyService et formulaire Filament, CatalogTaxonomySeeder, StorefrontCatalog et contrôleur d’accueil, Welcome.tsx et CategoryIcon.tsx. Aucun stock, prix ni historique de commande n’est modifié.

Recette de livraison : suite Laravel complète à 159 réussis et 14 ignorés (spécifiques MySQL), 6 tests de catégories réussis sur MySQL temporaire avec migration/rollback et double seeding, et 2 scénarios navigateur réussis sur desktop/mobile. Captures inspectées. La migration a été appliquée localement et les trois catégories existantes sont proposées avec leur icône et leur description. Build TypeScript/Vite réussi (708 modules), formatage PHP et `git diff --check` validés. Aucun commit ni déploiement effectué.
