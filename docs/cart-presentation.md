# Présentation du panier

La page `/panier` reprend les fonds ivoire, les bordures et la typographie de la boutique. Les articles sont présentés dans des fiches distinctes avec photo, nom, variante, emplacement, prix unitaire et total. Un repère montre les étapes panier, coordonnées et confirmation. L’état vide propose une découverte du catalogue, sans inventer de recommandations ou de promotions.

Le récapitulatif noir et or est placé à droite sur ordinateur et après les articles sur téléphone. Le sous-total vient du serveur. Les frais et le montant final restent à confirmer par l’équipe ; il n’y a aucun paiement en ligne. Le conseil WhatsApp utilise le contact configuré. La page conserve `noindex,nofollow` et les en-têtes de confidentialité existants.

Les boutons de quantité respectent le minimum de 1, le stock disponible et le plafond de 99. « Actualiser » enregistre explicitement la modification. Tant qu’une quantité est modifiée ou qu’une opération de ligne est en cours, le lien permettant de poursuivre la demande est remplacé par un bouton désactivé. Un texte indique les quantités à enregistrer. Revenir à la quantité enregistrée permet de poursuivre sans nouvelle requête. Un article devenu indisponible ne peut pas être commandé ; le retrait et le vidage restent accessibles.

Les validations, calculs de prix, contrôles de stock et mutations de session restent dans les services existants. Aucune réservation de stock n’est ajoutée au panier. Aucune migration ou dépendance supplémentaire.

Fichiers principaux : `resources/js/Pages/Cart.tsx`, `lang/fr/shop.php`, `tests/e2e/cart.spec.ts`, `README.md`.

La recette couvre le panier vide, l’enregistrement des quantités, le récapitulatif, les quantités invalides, la remise à la valeur enregistrée, le retrait, le vidage, les articles indisponibles et l’accès aux coordonnées. Les scénarios visuels enrichissent uniquement la réponse UI avec une photo locale ou un article indisponible ; les modifications du panier utilisent la base de test isolée. Les contrôles PHPUnit existants vérifient les indisponibilités réelles et les changements de marché côté serveur.

## Vérifications locales

- `php artisan test --compact` : **170 réussis, 14 tests MySQL ignorés**, 1989 assertions.
- `php vendor/bin/pint --test`, `git diff --check` et `npm run build` : réussis.
- Playwright, avec `E2E_CAMEROON_ONLY=1`, sur `cart.spec.ts`, `product.spec.ts`, `cameroon.spec.ts` et `seo.spec.ts` : **12 scénarios réussis** sur Edge ordinateur et mobile.
- La recette visuelle globale place désormais le contact WhatsApp dans l’en-tête sur les écrans compacts et dans la marge extérieure sur grand écran. Les montants restent lisibles sans marges artificielles autour des totaux de ligne.
