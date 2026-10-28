# Présentation de la boutique

La page `/boutique` reprend les fonds ivoire, la typographie et les accents noir et or de l’accueil. L’en-tête comporte un fil d’Ariane, le titre de la catégorie lorsqu’elle est sélectionnée et un encart de marque, compact sur téléphone.

Les catégories publiques sont accessibles par raccourcis illustrés. Les filtres restent visibles sur ordinateur et repliables sur mobile. Le tri placé au-dessus des résultats s’applique immédiatement. Les critères appliqués peuvent être retirés individuellement ; les autres critères sont conservés. Chaque changement de sélection repart de la première page. Les champs se synchronisent avec les raccourcis, la réinitialisation et l’historique du navigateur. Le tri par défaut est omis de l’URL pour conserver des liens de catégories simples.

Les cartes communes à l’accueil, au catalogue et aux suggestions présentent une photo non recadrée, l’état réel du stock, la catégorie, le nom et le prix. Les animations existantes respectent la préférence de mouvement réduit. Un lien de conseil WhatsApp apparaît sur ordinateur lorsque le contact est configuré.

Fichiers principaux : `resources/js/Pages/Catalog.tsx`, `resources/js/Components/ProductCard.tsx`, `lang/fr/shop.php`, `resources/views/seo/content.blade.php`. Les critères et les règles de prix, de stock et de commande restent contrôlés par les services existants. Aucune migration ni dépendance supplémentaire.

Vérifications : `php artisan test --compact` (170 réussis, 14 tests MySQL ignorés, 1989 assertions), `php vendor/bin/pint --test`, `git diff --check` et `npm run build`. Les scénarios Playwright couvrent les critères cumulés, le tri, le retrait individuel, le retour arrière, la remise à zéro, les résultats vides, les photos et les liens produits sur Edge ordinateur et mobile. Les parcours de retrait, les contacts, le SEO et les articles similaires ont également été vérifiés. Aucun stock commercial n’est ajouté pendant ces essais.
