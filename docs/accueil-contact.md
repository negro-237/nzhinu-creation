# Accueil, navigation et coordonnées publiques

Le menu reste visible pendant le défilement. Il propose des raccourcis vers l’accueil, le catalogue, la boutique physique et le panier, ainsi qu’un volet de catégories issues du catalogue public. Sur mobile, les raccourcis sont représentés par des SVG accessibles. Le volet se ferme après sélection, avec Échap, lorsque le focus quitte l’en-tête ou lors d’un clic extérieur.

L’accueil utilise la première photo disponible parmi les produits mis en avant ; à défaut, il conserve le logo transparent. Les catégories suivent toujours « À découvrir ». Une section présente les trois étapes de demande de commande et un espace de contact rappelle l’adresse de Bonaberi. Aucun stock ni délai commercial supplémentaire n’est annoncé.

## Configuration

```dotenv
WHATSAPP_CM=23755278597
SHOP_CONTACT_EMAIL=infos@nhinu-creation.com
```

Le numéro et l’adresse reprennent exactement les coordonnées fournies. WhatsApp ouvre `https://wa.me/23755278597` avec un message prérempli, sans envoi automatique. Le bouton noir et or utilise un SVG et trois pulsations CSS, désactivées en mode mouvement réduit. Il est intégré au menu sous 1440 px et flottant dans la marge extérieure sur grand écran pour laisser le contenu lisible. Aucun SDK externe n’est chargé. Les liens externes utilisent `noopener noreferrer`.

Le lien e-mail figure dans le pied de page et le menu. Il est également présent dans le HTML initial et dans les données structurées publiques. Une configuration invalide masque le contact concerné. Le transport SMTP et les notifications de commandes ne sont pas modifiés. En développement, appliquer une modification avec `php artisan config:clear` ; reconstruire le cache de configuration en production.

## Vérification locale

- `php artisan test --compact` : **170 réussis, 14 ignorés**, 1989 assertions. Les tests ignorés nécessitent MySQL ; aucune migration n’est ajoutée par cette modification.
- `npm run build` : compilation TypeScript/Vite réussie, 712 modules.
- `php vendor/bin/pint --test` et `git diff --check` : réussis.
- Playwright avec `E2E_CAMEROON_ONLY=1` : contacts/menu, pied de page et animations **8/8** sur Edge ordinateur et mobile. Les catégories, le parcours de commande en retrait et les vérifications SEO ont également réussi sur les deux formats. Le contrôle des animations attend la fin des transitions de navigation avant de vérifier leur arrêt.

Les essais WhatsApp et e-mail vérifient les liens et leur configuration ; ils n’envoient aucun message et ne vérifient pas l’existence du compte ou de la boîte de réception.
