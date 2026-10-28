# Référencement naturel

## Livraison technique

Les pages publiques disposent d’un titre et d’une description uniques, d’une URL canonique, de métadonnées Open Graph et Twitter et de données structurées JSON-LD. Les titres d’accueil et la description du site mentionnent Douala et Bonaberi. Les préférences de présentation sont conservées : le pays et la devise implicite ne sont pas ajoutés aux textes visibles de la boutique.

Les métadonnées sont calculées côté Laravel puis partagées avec React, afin de garder les mêmes informations dans le HTML initial et après navigation Inertia. Le JSON-LD est encodé avec échappement des balises HTML et un nonce CSP. Les données précédentes sont retirées lors d’une navigation vers le panier ou une page privée.

Les contenus essentiels de l’accueil, du catalogue, des catégories, des produits et des pages CMS sont aussi livrés en HTML dans le conteneur Inertia : titres, descriptions, photos, prix, liens, pagination et adresse. React remplace ce contenu lors de son montage et active les interactions habituelles. Le même HTML est envoyé aux visiteurs et aux robots, sans détection de user-agent. Il n’y a pas de serveur Node de rendu supplémentaire. Sans JavaScript, la consultation reste possible ; la sélection interactive des variantes, le panier et la commande nécessitent JavaScript.

## Catégories, pagination et contenus exclus

- Une catégorie publique avec des produits réels possède son titre, sa description et une URL canonique conservant `?category=…`.
- Une pagination valide conserve son numéro dans le titre et son URL canonique. Une page au-delà de la dernière page renvoie 404.
- Les recherches, tris et filtres combinés restent en `noindex,follow`. Les paramètres de suivi ne sont pas conservés dans la canonique.
- Les produits de démonstration, identifiés par un SKU `DEMO-NZ-…` y compris archivé, ne sont pas indexables et ne génèrent pas de données commerciales Product/Offer.
- Le panier, le formulaire de commande, les confirmations et l’administration restent en `noindex,nofollow`. Les commandes ne figurent jamais dans le sitemap.
- Seul le marché public principal est indexable. L’ouverture future d’autres marchés demandera des URL distinctes avant de les indexer ; les pages dépendant d’une session d’un autre marché restent en noindex.

## Données structurées

`ClothingStore` décrit Nzhinu Création avec son adresse réelle à Douala, Bonaberi Rail, Marché ChaCha 223, son logo et les profils sociaux configurés. Les champs d’adresse structurée sont centralisés dans `config/storefront.php`. `WebSite` décrit le site ; `BreadcrumbList` décrit le chemin vers le catalogue, la catégorie et le produit.

Pour un produit réel, `Product` et `Offer` ou `AggregateOffer` reprennent les prix unitaires, la devise et la disponibilité du catalogue. Les variantes peuvent fournir une fourchette de prix. Ces prix ne représentent pas un paiement ni un total final avec livraison : les frais restent confirmés par téléphone dans le workflow existant. Aucun avis, note, horaire, téléphone, coordonnée GPS, taxe ou politique de retour n’a été inventé.

## Sitemap et robots

`/sitemap.xml` est généré automatiquement et ne dépend pas de la session du visiteur. Il contient l’accueil, le catalogue réel, ses catégories avec produits réels, les fiches produits publiées et les pages CMS publiques. Les archives, brouillons, articles indisponibles pour le marché, tarifs dans une mauvaise devise et données de démonstration sont exclus. Le XML est échappé et envoyé en flux pour limiter la mémoire. Ce sitemap unique convient au MVP ; au-delà de 50 000 URL, prévoir un index de sitemaps.

`/robots.txt` est servi par Laravel (l’ancien fichier statique a été retiré). En mode public, il déclare le sitemap et exclut les parcours privés. Les scripts et images restent accessibles à l’exploration. Les règles robots ne remplacent pas les contrôles d’accès : signatures, permissions et en-têtes privés restent actifs.

## Activation lors de la mise en ligne

La configuration locale reste volontairement en `SEO_INDEXABLE=false`. Les pages ont alors une directive noindex, robots interdit l’exploration et le sitemap ne contient aucune URL. Le diagnostic `boutique:preflight` affiche l’état d’indexation sans en faire un blocage du fonctionnement de la boutique.

Sur le domaine public définitif :

1. Renseigner `APP_URL` avec l’adresse HTTPS réelle et vérifier les redirections de l’hébergeur vers ce domaine.
2. Archiver/remplacer les produits de démonstration et publier les vrais produits avec photos, textes et stocks validés.
3. Activer `SEO_INDEXABLE=true`, reconstruire le cache de configuration et vérifier `/robots.txt` et `/sitemap.xml`.
4. Vérifier le domaine dans Google Search Console puis y soumettre l’URL du sitemap. Aucune soumission externe n’est réalisée automatiquement.
5. Contrôler les URL avec l’inspection Google et les données structurées avec le test de résultats enrichis. Compléter la fiche Google Business Profile avec les informations réelles lorsque son propriétaire le souhaite.

Le classement et l’affichage des résultats enrichis sont décidés par les moteurs ; ces améliorations préparent l’exploration et la compréhension du contenu. Mesurer les performances et Core Web Vitals sur le véritable hébergement après publication.

## Fichiers et validation

`StorefrontSeo`, `StructuredData`, `SearchIndex`, `SearchIndexController`, `SeoHead.tsx`, vues `seo/`, template racine, pages publiques, configuration et traductions. Aucune migration ni dépendance ajoutée.

`SeoTest` vérifie les métadonnées et le HTML initial sans JavaScript, la pagination, les canoniques, les données locales et commerciales, l’échappement JSON, la désactivation de l’indexation, les marchés secondaires et les exclusions du sitemap. `seo.spec.ts` vérifie les métadonnées après navigation Inertia, la CSP et la lecture des pages avec JavaScript désactivé, sur ordinateur et mobile.

Références officielles : [SEO JavaScript](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics), [sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap), [données d’établissement local](https://developers.google.com/search/docs/appearance/structured-data/local-business), [données produit](https://developers.google.com/search/docs/appearance/structured-data/product-snippet), [robots.txt](https://developers.google.com/search/docs/crawling-indexing/robots/intro).

## Résultats de recette

- Suite Laravel complète : 169 réussis, 14 ignorés (spécifiques MySQL), 1 936 assertions.
- Tests SEO sur base MySQL temporaire : 7 réussis, 135 assertions ; migrations, double seeding et rollback vérifiés ; base temporaire supprimée.
- Navigateurs : 4 scénarios SEO réussis sur desktop/mobile, avec et sans JavaScript ; les 2 scénarios de commande invitée restent réussis.
- Mode production temporaire : migrations, seeders, caches de configuration/routes/vues/événements et pages publiques/connexion réussis ; environnement temporaire supprimé.
- Build TypeScript/Vite : réussi, 710 modules.
- Vérification finale après alignement du marché principal : 29 tests SEO, vitrine et préparation au déploiement réussis, 710 assertions. Pint et `git diff --check` réussis.

L’indexation de l’environnement local est explicitement désactivée. Aucun domaine n’a été déclaré auprès d’un moteur, aucune fiche externe n’a été modifiée et aucun déploiement ni commit n’a été effectué.
