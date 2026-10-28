# MVP au Cameroun

Le lancement public concerne uniquement le Cameroun, en francs CFA XAF (montants entiers, sans décimales). La boutique est située à **Douala, Bonaberi Rail, Marché ChaCha 223**.

## Configuration

`STOREFRONT_MARKETS=CM` dans `.env` ; la valeur par défaut dans `config/storefront.php` est également `CM`. Le modèle garde les deux pays pour une ouverture ultérieure. Les données canadiennes, leurs tarifs et les commandes historiques ne sont ni supprimés ni convertis.

Les marchés affichés, la sélection du marché et l’acceptation des nouveaux devis de commande sont filtrés côté serveur. Une ancienne session canadienne affiche désormais le marché camerounais ; un ancien panier incompatible doit être vidé explicitement. Un brouillon canadien antérieur à la fermeture ne permet pas de créer une nouvelle commande. Les liens privés et les reprises idempotentes de commandes déjà enregistrées continuent à fonctionner.

Le sélecteur de marché est absent lorsqu’un seul pays est proposé. L’adresse est affichée sur l’accueil et dans le pied de page, avec un lien d’itinéraire Google Maps ouvert à la demande, sans carte intégrée ni géolocalisation supposée.

### Présentation simplifiée

La vitrine ne mentionne pas le pays ni la devise implicite : bandeau, en-tête, pied de page, accueil, catalogue, fiches produit, filtres, panier, formulaire de commande et confirmation. Les prix XAF sont affichés avec séparateurs de milliers, sans décimales ni suffixe (par exemple `25 000`). Le bloc de choix de marché sur l’accueil n’est pas affiché avec un seul pays ; il reste uniquement l’adresse de la boutique. Les titres SEO du catalogue et des fiches ne comportent plus de pays.

La devise et le pays restent présents dans les données, contrôles, liens d’itinéraire et l’administration. Les calculs ne sont pas modifiés. Les anciennes commandes canadiennes conservent leur format monétaire explicite pour éviter toute confusion avec les montants de la boutique actuelle.

Vérification de cette simplification : 153 tests Laravel réussis (14 tests MySQL ignorés), 1 683 assertions ; 2 parcours navigateur réussis sur desktop/mobile contrôlant l’absence de pays et de devise sur l’accueil, le catalogue et ses filtres, le produit, le panier, le formulaire et la confirmation, avec maintien des montants et de l’adresse. Build TypeScript/Vite, Pint et `git diff --check` réussis. Fichiers concernés : composants d’adresse et de mise en page, pages publiques, formatage des prix, traductions et titres SEO ; aucune donnée métier modifiée.

## Boutique et retrait

```bash
php artisan config:clear
php artisan db:seed --class=CameroonShopSeeder
```

Sur une installation neuve, le seeder standard appelle automatiquement ce seeder après création des marchés lorsque `CM` est le seul marché public. Il crée le lieu actif `CM-DOUALA-CHACHA223` et le point de retrait associé, à l’adresse indiquée. Il conserve les modifications, désactivations et archives du personnel et ne réactive pas un marché désactivé. Les dépôts exemples restent désactivés.

Aucune quantité physique n’est ajoutée ou transférée. Saisir les stocks réellement disponibles à Bonaberi depuis l’administration. Le retrait n’est proposé que si tous les articles du panier viennent de ce lieu. La remise reste interdite avant paiement intégral vérifié. Aucun horaire, numéro de téléphone ou tarif de livraison n’a été inventé.

`DemoCatalogSeeder` respecte les marchés publics configurés : sur une nouvelle installation Cameroun, 12 produits, 22 variantes, 22 prix XAF, 24 images et aucun stock. Sur la base existante, les prix et médias déjà présents sont préservés.

## Recette

La suite générale teste volontairement l’architecture historique à deux marchés, via la configuration explicite de PHPUnit et de Playwright. `CameroonLaunchTest` teste le mode de lancement réel en restreignant la configuration à `CM`, y compris une commande de retrait complète jusqu’à la réservation du stock.

```powershell
$env:E2E_CAMEROON_ONLY = '1'
npx playwright test tests/e2e/cameroon.spec.ts
Remove-Item Env:E2E_CAMEROON_ONLY
```

Ce scénario utilise exclusivement la base SQLite isolée des tests et un stock fictif de test. Aucun stock réel ni commande réelle n’est créé.

Le contrôle `boutique:preflight` exige un catalogue et du stock commandable uniquement pour les marchés ouverts. Les contrôles de production, de HTTPS, des sessions et du paiement manuel restent applicables.

## Résultats de livraison

- Configuration locale vérifiée : seul `CM/XAF` est public ; un lieu réel actif et un seul point de retrait à l’adresse fournie. Seeder exécuté deux fois sans doublon ; aucune ligne de stock ajoutée.
- `php artisan test --compact` : 153 réussis, 14 ignorés (tests spécifiques MySQL), 1 683 assertions.
- `php tools/verify-mysql.php CameroonLaunchTest` : migrations, double seeding, rollback et réinstallation réussis ; 4 tests réussis, 78 assertions ; base temporaire supprimée.
- Recette Playwright Cameroun : 2 parcours réussis, desktop et mobile, jusqu’à la confirmation de la commande invitée avec retrait à Bonaberi. Capture mobile inspectée.
- `php tools/verify-production.php` : caches et pages publiques/connexion réussis en environnement production temporaire ; environnement supprimé.
- `npm run build` : TypeScript et Vite réussis, 707 modules. Pint et `git diff --check` réussis.

Aucun déploiement ni commit effectué. Le stock réel reste à saisir avant de rendre les produits commandables.
