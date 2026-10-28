# Compte rendu — phase 6 : paiements manuels et remise

## Périmètre

- Migration `2026_10_26_060000_create_manual_payments_and_fulfillments` : journal des encaissements/remboursements, opérations idempotentes, preuve de remise unique par commande, soldes vérifiés et contexte financier de l'historique. Contraintes de références, clés étrangères restrictives et métadonnées obligatoires pour un mouvement vérifié. Rollback refusé en présence d'historique financier ou de remise ; rollback à vide vérifié.
- Enums `PaymentEntryStatus`, `PaymentKind`, `PaymentMethod` ; états complémentaires de commande terminée, remboursement partiel et réservation consommée. Nouvelles permissions de déclaration, vérification, remboursement et remise.
- `ManualPaymentService`, `PaymentLedger`, `OrderAction`, `OrderJournal` : montants entiers CAD/XAF, déclaration séparée du contrôle, rejet motivé, références normalisées, cumuls vérifiés et budget de remboursement. Verrou de commande avant les écritures, lectures verrouillées du journal pour éviter les snapshots MySQL obsolètes et clés UUID liées à l'acteur, la commande et la charge utile. Aucun transfert financier ni justificatif client considéré comme preuve suffisante.
- `FulfillmentService`, `ReservationService` : préparation après paiement intégral vérifié, expédition/retrait selon le mode initial, consommation atomique du stock physique et réservé à la remise uniquement. Les doublons d'une opération retournent son résultat actuel sans nouvelle consommation. Une réservation consommée ne peut pas être réactivée.
- `OrderWorkflow` : confirmation et réactivation compatibles avec un crédit existant, annulation avec fonds réservée aux administrateurs, expiration des commandes partiellement payées sans perte des fonds, maintien du stock des commandes intégralement payées. Un paiement constaté après échéance clôture d'abord la réservation due et ne réactive rien automatiquement. Les états de paiement historiques incohérents sans journal vérifié bloquent les actions dangereuses et exigent une revue.
- Filament : déclaration depuis la commande, liste des paiements/remboursements avec vérification/rejet, préparation et remise, consultation du journal et des acteurs. Aucun CRUD direct des paiements. Responsables : déclarer/préparer/remettre ; administrateurs : vérifier/rejeter/rembourser en plus. Personnel ordinaire sans accès aux coordonnées/paiements.
- Confirmation privée React/Inertia : paiements reçus/remboursés/nets vérifiés, solde restant et référence de remise. Aucune référence bancaire, note de vérification ou identité staff exposée. Signature, non-indexation et absence de cache conservées ; identité noir/or et logo inchangés.
- Tests `ManualPaymentTest`, support `OrderFixture`, extension des workers et tests MySQL de concurrence : reprise de remise, remboursements simultanés, remboursement contre remise et vérification tardive contre expiration. La remise et le remboursement prennent le même verrou de commande. L'issue sérialisée détermine si la remise est encore autorisée ; un remboursement après remise ne restaure jamais le stock.

## Exploitation

Le paiement est constaté hors application. Chaque vérification indique une source indépendante et un compte rendu. Pour les espèces, compter réellement la caisse. Les références doivent désigner le mouvement financier, pas uniquement le client ou le numéro de commande. Les opérateurs choisissent la bonne méthode et une référence commune stable pour éviter de représenter un même mouvement sous des références différentes. Un mouvement rejeté reste dans le journal ; une erreur sur un mouvement vérifié est corrigée par un remboursement ou une nouvelle opération documentée.

Les remboursements sont également effectués hors application avant leur déclaration et vérification. Les déclarations de remboursement en attente réduisent le budget d'une nouvelle déclaration, mais seul un remboursement vérifié réduit le net payé. S'il réduit ce net sous le total avant remise, la préparation revient à « Non remis » ; compléter le paiement puis préparer de nouveau. Après remise, traiter un retour physique comme une procédure séparée avec contrôle du stock, jamais comme conséquence automatique du remboursement.

Un versement tardif ou un trop-perçu est signalé dans le détail staff. Décider explicitement d'une réactivation avec recontrôle du stock, ou d'un remboursement. Une commande payée peut conserver son stock au-delà de l'échéance : prévoir une revue humaine des commandes en attente de remise. Un retrait ne change pas implicitement de dépôt. L'expédition signifie remise au transporteur ; le suivi d'une livraison effectivement reçue n'est pas un nouvel état implémenté dans cette phase.

La vérification et les remboursements sont limités par défaut aux administrateurs ; aucun double contrôle par deux personnes distinctes n'est imposé. Aucun compte, paiement, stock, point de retrait ou commande de démonstration n'est ajouté à la base métier. Les données synthétiques restent dans les bases de test isolées. `.env` inchangé, aucune dépendance supplémentaire, aucun commit.

Les anciennes phases du README décrivent leurs livraisons historiques ; les comportements de phase 6 ci-dessus remplacent leurs limites relatives aux paiements et à l'expiration. Les règles fiscales/légales Canada/Cameroun et les procédures bancaires restent à valider par l'exploitation.

## Vérifications

PHP 8.3.2 utilisé via le PATH de la session. Commandes exécutées :

```text
git status --short
php artisan test --compact --filter=GuestOrderTest
php artisan test --compact --filter=ManualPaymentTest
php vendor/bin/pint
php artisan test --compact
php tools/verify-mysql.php
php tools/verify-mysql.php simultaneous_partial_payment_verifications
php artisan migrate --seed --no-interaction
php artisan migrate:status
php vendor/bin/pint --test
npm run build
git diff --check
```

- Tests de commandes de phase 5 : **18 réussis, 165 assertions**.
- Tests ciblés de phase 6 : **17 réussis, 116 assertions**, comprenant les actions Livewire réelles de déclaration, vérification, préparation et remise, permissions, espèces, CAD/XAF, gratuité, remboursements et versements tardifs.
- Suite SQLite : **120 réussis, 12 ignorés, 1151 assertions**, 117,43 s. Les tests MySQL sont ignorés ici.
- Dernier test MySQL ajouté pendant la vérification complète, exécuté également de façon ciblée : **1 réussi, 15 assertions**, 32,80 s. Deux processus vérifient simultanément deux paiements partiels : net final 10500 CAD mineurs, statut payé, deux incréments de version conservés. Base temporaire supprimée.
- Build TypeScript/Vite réussi : **703 modules, 29,58 s**. Pint et `git diff --check` réussis.
- Migration appliquée à la base configurée, permissions mises à jour ; **10 migrations appliquées**. Aucun compte/paiement/commande de démonstration inséré.
- Migrations fraîches, double seeding et rollback puis réinstallation MySQL réussis. Le script accepte maintenant un filtre de test facultatif et accorde 600 s à la suite agrandie. Les tests de concurrence utilisent uniquement une base `nzhinu_verify_*`, avec nettoyage explicite compatible avec la protection de l'historique financier.
- Suite MySQL complète : **132 tests réussis, 1351 assertions**, 426,02 s ; base temporaire supprimée. Le dernier cas de vérifications partielles simultanées a été ajouté après découverte des tests par cette exécution et validé séparément comme indiqué ci-dessus. La couverture globale comprend ainsi 133 scénarios distincts, dont les courses avec processus indépendants pour la remise, les remboursements et les paiements tardifs.

Prochaine phase : **7 — notifications, CMS, tableaux de bord, durcissement et tests de bout en bout**. Arrêt pour revue à la fin de la phase 6.
