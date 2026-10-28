# Images de démonstration et galerie produit

Les 12 articles de démonstration disposent de deux images chacun : vue d’ensemble et détail. Les 24 fichiers WebP se trouvent dans `public/images/demo`. Ce sont des illustrations générées par IA, pas des photographies du stock réel. Remplacer ou archiver ces produits avant la mise en vente réelle.

## Installation et reprise

```bash
php artisan storage:link
php artisan db:seed --class=DemoCatalogSeeder
# Ajouter uniquement les images aux produits de démonstration déjà présents :
php artisan db:seed --class=DemoProductImagesSeeder
```

Les seeders refusent les environnements autres que local/testing et exigent un compte existant autorisé à gérer le catalogue. Les images utilisent la collection Spatie `images`, disque `public`, avec texte alternatif et marqueur `demo_asset`. Les sources WebP sont conservées. Un second passage ne crée pas de doublons ; les produits archivés, renommés ou sans SKU de démonstration correspondant sont ignorés. Toute photo sans marqueur de démonstration entraîne la conservation intégrale de la galerie du personnel.

Le stockage public doit être exposé via `public/storage` et `APP_URL` doit correspondre à l’adresse du site. Aucun prix, stock physique, compte, commande ou paiement n’est modifié par le seeder d’images.

## Galerie publique

La photo s’ouvre au clic dans une fenêtre agrandie native. Le zoom permet de parcourir les détails par défilement et peut être réinitialisé. Les flèches, le clavier et le balayage restent disponibles dans la vue agrandie ; changer de photo réinitialise le zoom. Échap ou le bouton Fermer ferment la fenêtre, libèrent le défilement de la page et rendent le focus au bouton de la photo active. Les miniatures suivent automatiquement la sélection horizontalement sans déplacer la page.

Le dialogue natif limite le focus à ses commandes et conserve les textes alternatifs. Les diapositives inactives sont inertes pour éviter des boutons invisibles au clavier. Une image seule peut être agrandie ; la galerie vide conserve son message de photo à venir.

## Articles similaires

La section « Vous aimerez aussi » suit le contenu de la fiche. Elle propose au maximum quatre autres produits de la même catégorie, triés par ajout récent, avec photo, nom, prix et état de disponibilité. La sélection passe par les mêmes règles de catalogue que la boutique : publication, catégorie active, disponibilité sur le marché courant, variante active et prix dans la bonne devise. La pièce consultée, les archives, brouillons et articles d’un autre marché sont exclus. Les produits sans stock peuvent être découverts avec leur indication d’indisponibilité. Sans résultat, la section n’est pas affichée.

Les règles sont dans `StorefrontCatalog`, le rendu réutilise `ProductCard`, sans dépendance supplémentaire et sans modification de stock, réservation ou paiement. Tests serveur : `SimilarProductsTest`. Recette navigateur : `gallery.spec.ts` et `similar-products.spec.ts`.

Validation de la vue agrandie et des suggestions : `php artisan test --compact` — 162 réussis, 14 ignorés (spécifiques MySQL), 1 801 assertions ; 8 scénarios Playwright réussis sur desktop/mobile (galeries vide/unique/multiple, agrandissement, zoom, navigation, boucle de tabulation, Échap, retour du focus et suggestions). Build TypeScript/Vite, Pint et `git diff --check` réussis. Captures inspectées. Contrôle local en lecture seule : la robe de démonstration propose 4 articles avec photo, le sac et le collier en proposent 2 chacun. Aucun commit ni déploiement.

## Commandes de la galerie

Les flèches et miniatures utilisent un curseur pointer. Les chevrons sont des SVG centrés dans leurs boutons ronds, indépendants de la ligne de base d’une police ; un changement de couleur accompagne le survol des flèches.

Correction visuelle vérifiée : build TypeScript/Vite réussi, six scénarios Playwright de galerie réussis (desktop/mobile), capture inspectée et `git diff --check` réussi.

`ProductGallery.tsx` affiche les images de la collection dans leur ordre existant. Deux images ou plus activent les flèches en boucle, les miniatures sélectionnables, le compteur annoncé aux lecteurs d’écran et le balayage horizontal tactile. Le défilement vertical reste possible. Clavier : gauche/droite, Début/Fin. Aucun défilement automatique. Le réglage système de réduction des animations supprime la transition. Une image reste statique ; une galerie vide conserve le message de photo à venir.

L’équipe peut ajouter plusieurs photos réelles depuis la fiche produit de l’administration, action d’ajout d’image, avec un texte alternatif pour chaque vue.

## Génération des visuels

Outil intégré `image_gen` via le skill `imagegen`, sans CLI ni clé API. Les PNG originaux restent dans le dossier `generated_images` de Codex ; seules les versions WebP optimisées sont livrées dans le projet. `tools/optimize-demo-images.php` effectue uniquement la conversion PNG vers WebP qualité 82 et refuse d’écraser un fichier existant.

Les prompts et les sujets sont conservés dans `docs/demo-image-prompts.json`. Les vues de détail ont été générées à partir de la vue d’ensemble correspondante comme référence pour conserver le dessin et les matières.

## Vérification

Tests Laravel : fichiers présents, 24 médias pour 12 produits, répétabilité, photos du personnel conservées, archives ignorées, autorisations et refus en production, deux vues exposées par Inertia. Tests Playwright desktop/mobile : galeries de zéro, une et deux images ; chargement réel des fichiers WebP, flèches, boucle, miniatures, clavier, événements de balayage et absence de débordement horizontal. Les tests navigateur injectent uniquement le tableau d’images dans une réponse Inertia réelle et utilisent les assets locaux ; ils ne copient aucun média dans le stockage réel.

Résultats de cette livraison :

- `php artisan test --compact` : 148 réussis, 14 ignorés (tests spécifiques MySQL), 1 593 assertions.
- `php tools/verify-mysql.php DemoCatalogTest` : migrations, double seeding standard, rollback et réinstallation réussis ; 6 tests réussis, 130 assertions ; base temporaire supprimée.
- `npx playwright test tests/e2e/gallery.spec.ts` : 6 réussis, Edge desktop et mobile. Captures inspectées aux deux tailles.
- `npm run build` : TypeScript et Vite réussis, 705 modules.
- `php vendor/bin/pint --test` et `git diff --check` : réussis.
- Seeder d’images exécuté deux fois sur la base locale : 12 produits, 24 médias, fichiers et chemins publics présents, toujours zéro ligne de stock de démonstration. Vérification en lecture seule disponible avec `php tools/verify-demo-images.php`.
- 24 WebP : 3 895 584 octets au total.

Fichiers principaux : `resources/js/Components/ProductGallery.tsx`, `resources/js/Pages/Product.tsx`, `lang/fr/shop.php`, `database/seeders/DemoProductImagesSeeder.php`, `database/seeders/DemoCatalogSeeder.php`, `tests/Feature/DemoCatalogTest.php`, `tests/e2e/gallery.spec.ts`, `public/images/demo`, documentation et outils de contrôle. Aucun commit ni déploiement effectué.
