# Recette de cohérence visuelle

Le parcours public est examiné sous Edge/Chromium à **320, 390, 768, 1024 et 1440 px**. Les vues mobiles utilisent une hauteur de 844 px, les vues ordinateur 900 px. Les cinq parcours utilisent une base SQLite de recette isolée.

## Pages et états examinés

- Accueil, menu ouvert et pied de page commun.
- Page d’information CMS.
- Boutique, filtres ouverts et fiche article.
- Carrousel, photos agrandies et zoom, y compris à 320 px.
- Panier vide et panier rempli.
- Coordonnées, livraison et retrait avec adresse du point choisi.
- Confirmation, progression et suivi après remise.

Les titres, fonds ivoire, cartes blanches, encarts noirs, accents or, champs et boutons sont comparés entre pages. Les contrôles vérifient aussi les débordements horizontaux, l’alignement du menu avec le logo, la lisibilité des champs et l’absence de superposition du contact sur les montants ou les contrôles de formulaire.

## Corrections

L’en-tête ne passe plus sur deux lignes sur un téléphone de 320 px. Le libellé de marque s’adapte à l’espace disponible, avec le logo conservé. Le bouton WhatsApp animé rejoint la navigation en dessous de 1440 px ; au-delà, il est placé dans la marge extérieure à la largeur maximale du contenu. Son rendu flottant sort du conteneur filtré de l’en-tête pour rester correctement fixé au viewport. Le numéro, le message prérempli, l’ouverture dans un nouvel onglet et le mouvement réduit sont conservés.

Les marges ajoutées auparavant autour des totaux pour contourner le bouton flottant sont retirées. Les champs passent à 16 px sur petit écran, les sélecteurs peuvent se réduire dans leur colonne, les titres longs se replient et les boutons de quantité ne doublonnent plus les flèches natives. Les pages CMS reprennent l’en-tête et le cadre de lecture de la boutique, avec leur texte toujours affiché sans interprétation HTML.

Les règles de commande, les prix, les réservations, les paiements et les liens privés restent inchangés. Aucune migration, dépendance ou modification de données commerciales n’est ajoutée.

## Reproduction

```powershell
$env:E2E_CAMEROON_ONLY = '1'
npx playwright test tests/e2e/visual-review.spec.ts
node tools/visual-review-gallery.mjs
```

Le test exécute cinq parcours réels et ignore les cinq combinaisons de projet non applicables. Il attend le titre de la page de destination avant chaque capture pour éviter de photographier la vue précédente pendant une navigation Inertia. Les photos et l’état de remise utilisés pour les vues de test enrichissent uniquement les réponses UI ; ils ne créent pas de faux paiements ou de mouvements de stock.

Les captures complètes et les planches de comparaison sont conservées sous `storage/framework/testing/visual-review/`, répertoire ignoré par Git. Les jeux de recette doivent rester séparés des créations intensives de commandes pour respecter les protections contre les demandes répétées.

## Vérifications techniques

- `php artisan test --compact` : **170 réussis, 14 tests MySQL ignorés**, 2001 assertions.
- `npm run build` : compilation TypeScript/Vite réussie, sans nouvelle dépendance.
- `php vendor/bin/pint --test` et `git diff --check` : réussis.
- Vingt scénarios de navigation, catégories, pied de page, animations, galerie et SEO validés sur ordinateur et mobile. Un test SEO a été synchronisé avec l’arrivée sur le catalogue avant le clic produit, puis rejoué avec succès.
- Dix scénarios de panier et de commande validés sur ordinateur et mobile : quantités, indisponibilités, livraison, retrait, erreurs et coordonnées conservées.
- Cinq parcours visuels complets validés à 320, 390, 768, 1024 et 1440 px, y compris les commandes du zoom. Les cinq combinaisons de projet ignorées sont celles qui ne correspondent pas au format testé, et non des contrôles en échec.

Les résultats du navigateur concernent Edge/Chromium, avec émulation des formats mobiles. Les scénarios sont exécutés par lots pour conserver les protections applicatives contre les requêtes répétées.
