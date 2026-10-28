# Présentation de la fiche article

La fiche conserve le noir, l’or et l’ivoire de l’accueil et du catalogue. Sur ordinateur, la galerie et le bloc de sélection apparaissent côte à côte. Sur téléphone, le nom, la référence, le prix et la disponibilité précèdent les photos, puis le formulaire. Le fil d’Ariane relie aussi la catégorie.

Le prix et le badge de disponibilité suivent la variante sélectionnée. Le stock de départ reste choisi explicitement. Les boutons de quantité respectent la limite du stock choisi et le plafond de 99 ; le champ conserve sa validation native. Le changement de variante efface le choix du stock et ramène la quantité à un. Le changement de stock remet la quantité à un. Le serveur conserve la validation finale des stocks et quantités.

Les chevrons de la galerie sont noirs et or. Le carrousel tactile, les miniatures, la navigation clavier, le zoom, la fermeture par Échap et le retour du focus sont conservés. Les animations suivent la préférence de mouvement réduit.

La description et les collections sont regroupées dans une section dédiée. Un encart rappelle la confirmation humaine et la remise après paiement intégral vérifié. Les articles similaires restent visibles. Le lien WhatsApp préremplit le nom et l’URL canonique publique de l’article sans envoyer de message.

Fichiers principaux : `resources/js/Pages/Product.tsx`, `resources/js/Components/ProductGallery.tsx`, `lang/fr/shop.php`, `tests/e2e/product.spec.ts`. Aucune migration ni dépendance ajoutée.

Vérifications locales : `php artisan test --compact` (170 réussis, 14 tests MySQL ignorés, 1989 assertions), `php vendor/bin/pint --test`, `npm run build` et `git diff --check`. Les 16 scénarios Playwright sur Edge ordinateur/mobile couvrent galerie sans photo/une photo/plusieurs photos, zoom, variantes, quantité, ajout réel au panier, SEO, retrait et articles similaires. La présentation finale est également contrôlée après l’ajustement de l’ordre des éléments sur mobile. Les photos et la variante supplémentaire des essais proviennent d’une réponse UI enrichie ; l’ajout au panier utilise le stock réel de la base de test isolée.
