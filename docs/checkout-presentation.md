# Présentation de la demande de commande

La page `/commander` reprend le noir, l’or, l’ivoire et les étapes du panier. Les coordonnées et le choix de réception sont présentés dans deux blocs numérotés. Le récapitulatif des pièces, le sous-total et le bouton d’envoi sont placés à droite sur ordinateur, après les champs sur mobile.

Nom et téléphone restent obligatoires ; l’e-mail est facultatif. Les champs requis sont signalés visuellement et conservent la validation native. Les longueurs maximales et les attributs d’autocomplétion suivent les champs métier. Les erreurs du serveur sont associées aux champs et regroupées dans un bloc qui reçoit le focus après un envoi invalide.

Les cartes de livraison et de retrait utilisent des boutons radio natifs. Le changement de mode conserve les coordonnées, l’adresse et les choix dans l’état du formulaire, et efface les erreurs propres au mode quitté. L’adresse de livraison n’est demandée qu’en livraison. Le point choisi est requis en retrait et son adresse est affichée séparément. Le retrait reste désactivé si aucun point compatible n’est fourni par le serveur.

Une option retirée par une réponse actualisée du serveur est désélectionnée pour éviter l’envoi d’un identifiant invisible dans le sélecteur. Le contact WhatsApp est intégré à l’en-tête sur les écrans compacts et placé dans la marge extérieure sur grand écran : il reste accessible pendant la saisie sans recouvrir les champs. Le lien du récapitulatif est également conservé.

La zone de livraison est facultative : l’équipe peut confirmer les frais par téléphone. Un montant estimé provenant de la zone sélectionnée apparaît séparément du sous-total. Aucun montant final ou frais de retrait gratuit n’est inventé. Le texte rappelle la confirmation humaine et la remise après paiement intégral vérifié.

La clé de demande, les validations, les prix et les réservations transactionnelles restent gérés par les services existants. Le bouton est désactivé pendant l’envoi. Aucun compte, paiement en ligne, stockage local de coordonnées ou nouvelle dépendance n’est introduit. La page conserve `noindex,nofollow` et les protections de confidentialité existantes.

Fichiers principaux : `resources/js/Pages/Checkout.tsx`, `lang/fr/orders.php`, `tests/e2e/checkout.spec.ts`, `README.md`.

La recette navigateur couvre la livraison et le retrait réels, les erreurs de téléphone et le focus, les champs conservés au changement de mode, le choix obligatoire du point et l’absence de point compatible. Une zone et une photo synthétiques enrichissent uniquement la réponse UI initiale de test pour contrôler l’estimation et le rendu ; la réponse réelle de validation retire cette zone, ce qui vérifie la désélection d’un choix devenu indisponible. Les demandes utilisent exclusivement la base de test isolée.

## Vérifications locales

- `php artisan test --compact` : **170 réussis, 14 tests MySQL ignorés**, 1989 assertions.
- `php vendor/bin/pint --test`, `git diff --check` et `npm run build` : réussis.
- Avec `E2E_CAMEROON_ONLY=1`, `npx playwright test tests/e2e/checkout.spec.ts` : **6 scénarios réussis** sur Edge ordinateur et mobile après les corrections.
- Les scénarios existants du panier et du retrait à Bonaberi ont également réussi sur les deux formats. Les captures de livraison et de retrait ont été examinées pour contrôler la présentation et l’absence de débordement.
