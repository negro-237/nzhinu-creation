# Animations de la boutique

La vitrine utilise CSS, IntersectionObserver et Web Animations API : aucune bibliothèque ni dépendance supplémentaire. Le logo et les couleurs noir/or sont conservés. L’administration Filament n’est pas concernée.

- Apparition douce des textes et du logo d’accueil, des annonces, cartes produits, marchés, lignes du panier, récapitulatifs et contenus CMS à leur entrée dans la fenêtre.
- Décalage de 60 ms entre cartes, plafonné à 180 ms ; apparition de 520 ms et déplacement vertical de 16 px. Chaque élément apparaît une seule fois par montage.
- Survol des cartes et boutons sur les appareils munis d’une souris, pression des boutons, soulignement doré de navigation et retour visuel sur le compteur du panier.
- Fondu de page et apparition des messages de confirmation et du panneau des filtres mobiles. Aucun défilement automatique ni animation en boucle.

Les mouvements utilisent principalement `opacity` et `transform` sans modifier les dimensions de mise en page. Les éléments ne sont jamais masqués par CSS en attendant JavaScript ou le défilement. Si les API d’animation manquent, le contenu reste accessible. Un élément contenant le focus n’est pas animé lors de son apparition.

`prefers-reduced-motion: reduce` désactive les animations et transitions de la vitrine, y compris celles de la galerie. Un changement de préférence en cours de session annule immédiatement les animations natives actives. Les observateurs et écouteurs sont nettoyés lors de la navigation et du démontage ; les nouveaux éléments insérés par Inertia sont détectés sans écouteur de défilement permanent.

Implémentation : `resources/js/lib/useStorefrontMotion.ts`, `resources/css/app.css`, `ShopLayout.tsx`, `ProductCard.tsx` et marqueurs `data-reveal` sur les pages concernées. La navigation, les formulaires et les données métier conservent leur fonctionnement.

Validation : build TypeScript/Vite, suite Laravel et tests Playwright desktop/mobile. `tests/e2e/motion.spec.ts` contrôle le démarrage au défilement, la visibilité finale, la désactivation des mouvements après changement de préférence, la navigation et le fonctionnement sans IntersectionObserver.

Résultats :

- `npm run build` : TypeScript/Vite réussis, 706 modules ; aucune modification de `package.json` ni du verrou des dépendances.
- `php artisan test --compact` : 148 réussis, 14 ignorés (spécifiques MySQL), 1 593 assertions.
- Playwright : 10 scénarios de non-régression réussis (galerie, contenus, panier, sur les deux tailles) et 4 scénarios d’animation réussis via `npx playwright test tests/e2e/motion.spec.ts`.
- `php vendor/bin/pint --test` et `git diff --check` : réussis.

Limite volontaire : les navigateurs sans les API requises affichent le contenu sans apparition au défilement. Aucun changement de base de données, aucun déploiement et aucun commit.
