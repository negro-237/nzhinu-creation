# Pied de page et logo transparent

Le pied de page organise la marque et ses réseaux sociaux, la navigation, les informations pratiques et la visite de la boutique dans une grille adaptée au mobile. Une séparation dorée et un bandeau noir de signature structurent le fond crème, qui permet de conserver le logo noir et or lisible sans encadré blanc.

Les liens CMS et les profils sociaux proviennent toujours des réglages existants. TikTok, YouTube et WhatsApp restent absents si aucune URL ou coordonnée valide n’est configurée. Aucun horaire, numéro de téléphone ou texte juridique fictif n’a été ajouté.

Le fichier `public/images/logo-transparent.png` possède un canal alpha transparent. Il est utilisé dans l’en-tête, le visuel principal d’accueil et le pied de page. L’ancien `logo.png` est conservé. Les fonds blancs des conteneurs du logo ont également été retirés. L’adresse de Bonaberi et l’affichage des prix sans pays ni devise sont conservés.

La version transparente a été produite avec l’outil intégré `image_gen`, skill `imagegen`, à partir du logo original. Aucun mélange CSS n’est utilisé pour simuler la transparence. Prompt utilisé :

```text
Use case: background-extraction. Edit target: supplied existing Nzhinu logo image. Remove ONLY the plain white background and the white vertical empty gap around/between the lettering and mask, producing a real transparent alpha channel. Preserve the exact original black African mask, exact vertically arranged lettering, yellow/gold accents, thin gold and white interior decorative lines and small white ornamental dots as parts of the logo. Do not redraw, redesign, retype, alter spelling, change shapes, proportions, orientation or colors. No new elements, no shadows, no outline, no white matte, no checkerboard baked into pixels. Keep the complete original logo uncropped, centered with modest transparent margins. Output transparent PNG suitable for a fashion boutique website.
```

Implémentation : `ShopFooter.tsx`, `ShopLayout.tsx`, `BoutiqueAddress.tsx`, `Welcome.tsx`, traductions françaises, contrôle des assets dans DeploymentReadiness et tests navigateur.

Le scénario `footer.spec.ts` contrôle les rubriques, les liens, l’adresse, les logos et leur chargement, le fond CSS transparent, le canal alpha du PNG, l’absence de profils non configurés et le débordement horizontal. Captures desktop/mobile disponibles dans les résultats Playwright.

Résultats : `php artisan test --compact` — 159 réussis, 14 ignorés (spécifiques MySQL), 1 744 assertions ; recette navigateur pied de page et commande — 4 scénarios réussis sur desktop/mobile ; `npm run build` — TypeScript et Vite réussis, 709 modules ; Pint et `git diff --check` réussis. Captures du pied de page et de l’accueil inspectées aux deux formats. Aucun changement de données métier, commit ou déploiement.
