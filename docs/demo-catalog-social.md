# Catalogue de démonstration et liens sociaux

## Livré

- `DemoCatalogSeeder` ajoute explicitement 12 produits, 22 variantes et 44 prix indépendants CAD/XAF. Les vêtements ont des tailles ; les accessoires et bijoux une variante Standard. Noms, descriptions, slugs `demo-*` et SKU `DEMO-NZ-*` identifient le jeu de démonstration.
- Le seeder s'exécute uniquement en local/testing, avec un compte existant autorisé à gérer le catalogue. Il utilise les factories pour préparer les attributs, puis le service de catalogue pour valider et créer les produits. La transaction évite une insertion partielle. Aucun compte, stock physique, mouvement, commande ou paiement n'est créé ; aucun dépôt fictif n'est activé.
- Les relances conservent les modifications, prix et archives. Un slug renommé est reconnu par ses SKU de démonstration. Le seeder standard de déploiement n'appelle pas ce jeu de données.
- `ProductFactory` propose `published()` et `withMarketPrices()` et conserve les variantes explicitement demandées sans ajouter une variante de trop. `MarketPriceFactory` déduit la devise du marché et réutilise CA si un marché doit être choisi par défaut.
- Le pied de page affiche des liens sociaux HTTPS configurables, accessibles et ouverts dans un nouvel onglet avec `noopener noreferrer`. Aucun SDK, iframe ou widget social n'est chargé. Le serveur rejette les domaines étrangers, URL non HTTPS, identifiants dans l'URL et ports inhabituels.
- Facebook configuré avec le lien fourni : `https://www.facebook.com/profile.php?id=100071095101171`. TikTok et YouTube sont disponibles dans la configuration mais restent masqués tant que leurs URL sont vides.

## Application locale

Le seeder a été exécuté dans la base locale existante. Le contrôle en lecture seule confirme **12 produits de démo, 22 variantes, 44 prix et 0 inventaire de démonstration**. Les autres produits et paramètres métier ont été conservés. Seules les trois variables sociales ont été ajoutées au `.env`, avec le lien Facebook fourni ; les paramètres de base de données, sessions et clé de chiffrement ne sont pas modifiés.

```text
php artisan db:seed --class=DemoCatalogSeeder --no-interaction
php artisan config:clear
```

Les produits sont visibles dans le catalogue CA et CM, mais restent indisponibles à la commande sans quantités réelles dans des dépôts validés. Leurs photos et données commerciales sont à renseigner/valider, ou les produits à archiver, avant ouverture publique. Le logo et la palette noir/or restent conservés.

## Vérifications

```text
php artisan test --compact --filter="DemoCatalogTest|SocialLinksTest"
php artisan test --compact --filter=DemoCatalogTest
php artisan test --compact
php tools/verify-mysql.php "DemoCatalogTest|SocialLinksTest"
npm run build
npm run test:e2e -- --grep="contenus publiés" --max-failures=1
php vendor/bin/pint
php vendor/bin/pint --test
git diff --check
```

- Tests ciblés initiaux : **6 réussis, 82 assertions**, 9,27 s.
- Suite SQLite globale : **146 réussis, 14 ignorés, 1523 assertions**, 106,37 s. Les tests propres à MySQL restent ignorés dans cette suite.
- Tests ciblés MySQL : **6 réussis, 82 assertions**, 39,86 s ; migrations, double seeding et rollback vérifiés dans une base temporaire supprimée après utilisation.
- Edge : **2 scénarios réussis, 1,1 minute**, ordinateur/mobile ; URL Facebook exacte, nouvel onglet, attributs de sécurité et absence des liens non configurés vérifiés sans ouvrir les plateformes.
- TypeScript/Vite : **704 modules, 49,66 s**, succès.
- Vérification complémentaire après ajout du cas de factory de prix autonome sur marchés déjà présents : **4 tests de catalogue réussis, 61 assertions**, 71,09 s ; le scénario autonome a aussi été confirmé seul via PHPUnit. Formatage Pint et `git diff --check` réussis.

L'affichage des 12 articles et des variantes/prix par marché est vérifié par les tests HTTP. Aucun envoi sur les réseaux sociaux, aucune nouvelle dépendance ni migration. Aucun commit ou déploiement effectué.
