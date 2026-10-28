# Confirmation et suivi de commande

La page privée conserve « Demande reçue » pour une demande en attente et présente « Suivi de votre commande » pour les autres états. L’en-tête noir et or met en avant la référence. Les statuts de commande, de paiement et de remise apparaissent séparément. Le numéro de téléphone reste masqué.

La progression utilise les codes d’état fournis par le serveur : demande, confirmation, paiement vérifié, préparation et expédition ou retrait enregistré. Elle n’invente aucun délai ni historique. Une expédition n’est pas présentée comme une livraison achevée. Pour une commande annulée, expirée ou remboursée, la progression ordinaire laisse place à un message adapté. Le détail d’une remise déjà enregistrée reste affiché après remboursement.

Les articles et les prix proviennent des instantanés de commande. Les frais et taxes manquants restent à confirmer. Les montants payés, remboursés et nets vérifiés restent distincts. Pour une demande clôturée ou remboursée, le reliquat numérique est intitulé « Écart avec le total », afin de ne pas présenter un remboursement comme une somme à payer.

Le bouton d’actualisation recharge uniquement le récapitulatif, avec le même lien signé et sans interrogation périodique. Le contact WhatsApp utilise la référence de commande et n’inclut pas le lien privé. Les protections `noindex,nofollow`, les en-têtes de confidentialité, la signature et son expiration sont conservés. Aucune coordonnée supplémentaire n’est ajoutée au récapitulatif public autorisé.

Fichiers principaux : `resources/js/Pages/OrderConfirmation.tsx`, `app/Services/OrderAccess.php`, `lang/fr/orders.php`, les tests de commande/paiement existants et `tests/e2e/tracking.spec.ts`. `OrderAccess` expose les codes de paiement et de remise en plus des libellés existants. Aucune migration, dépendance ou transition métier ajoutée.

Les tests PHP vérifient les codes d’état sur une commande réelle, les paiements vérifiés, la remise puis le remboursement sans effacement de la remise. La recette navigateur utilise de vraies demandes et de vrais liens privés. Les états intermédiaires de l’essai visuel sont des réponses UI enrichies après autorisation du serveur ; ils ne créent aucun faux paiement ou mouvement de stock. Les contrôles couvrent les montants en attente, la progression, les paiements partiels, la préparation, le retrait, l’expédition, le remboursement, l’expiration, l’annulation et le rejet d’une signature manquante ou modifiée.

## Vérifications locales

- `php artisan test --compact` : **170 réussis, 14 tests MySQL ignorés**, 2001 assertions.
- `npm run build`, `php vendor/bin/pint --test` et `git diff --check` : réussis.
- Avec `E2E_CAMEROON_ONLY=1`, `npx playwright test tests/e2e/tracking.spec.ts` : **4 scénarios réussis** sur Edge ordinateur et mobile. Les captures de confirmation et d’expédition ont été examinées.
- Les six scénarios de commande et les deux parcours de retrait à Bonaberi ont également réussi. Un regroupement initial a déclenché la limite de cinq demandes par minute ; la recette du suivi a donc été rejouée séparément. Les protections contre les requêtes répétées restent inchangées.
