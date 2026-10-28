# Compte rendu — phase 5 : commandes invitées

## Périmètre livré

- Migration `2026_10_26_050000_create_guest_orders` : commandes, lignes figées, historique, zones de livraison et points de retrait. Référence aléatoire et clé de soumission uniques ; lien unique vers la réservation ; devises et quantités entières, clés étrangères restrictives. Les réservations invitées autorisent un acteur nul sans créer de faux compte utilisateur.
- Modèles `Order`, `OrderItem`, `OrderHistory`, `DeliveryZone`, `PickupPoint` ; enums distincts de commande, paiement, remise et méthode de réception ; policies/permissions spécifiques aux commandes et options de réception.
- `GuestCheckout` : devis issu du panier côté serveur, contexte de session et clé UUID, vérification des prix/quantités, création des snapshots, de la commande et de la réservation dans une transaction unique. Les stocks sont verrouillés par ID croissant, puis les prix par ID de variante. Une reprise valide retourne la même commande. Le panier n'est vidé qu'après succès, sans effacer une nouvelle sélection lors d'une ancienne reprise.
- Snapshots : SKU, nom du produit, libellé/taille/couleur de variante, prix unitaire, quantité, total de ligne, devise, inventaire et lieu. Les modifications/archivages ultérieurs du catalogue ne changent pas les lignes de commande.
- `DeliveryOptions` : zones par marché et éventuels frais estimés, points de retrait actifs associés à un dépôt et une adresse publique réelle. Le retrait exige que toutes les lignes viennent de ce dépôt, sans transfert implicite. Livraison possible sans zone configurée, avec frais à confirmer par téléphone. Aucun tarif ou point fictif n'est ajouté.
- `OrderWorkflow` : confirmation de l'appel et des frais/taxes saisis explicitement, échéance de paiement configurable, annulation, expiration et réactivation contrôlées. Verrou de commande avant réservation et stocks ; version contre les actions obsolètes ; refus des mutations incompatibles avec un paiement ou une remise. La réservation liée ne peut pas être manipulée directement via les actions internes du module d'inventaire.
- Expiration raccordée au job de phase 3 : libération et mise à jour du statut de commande atomiques/idempotentes. Réactivation avec nouveau contrôle de stock et nouvel appel requis. Aucune décrémentation physique liée à une commande dans cette phase.
- `OrderAccess` : URL temporairement signée vers une référence UUID ; pas de consultation par numéro de commande seul. Récapitulatif minimal avec téléphone masqué, sans e-mail ni adresse de livraison privée. Les coordonnées et l'adresse sont chiffrées en base, masquées dans la sérialisation du modèle et non recopiées dans les anciens champs de formulaire en session.
- Routes `/commander` GET/POST et `/commandes/{reference}` GET signé ; validation, CSRF, verrouillage de session, limitation du débit. Pages privées `no-store`, `noindex`, `no-referrer`. Les confirmations restent accessibles avec un lien valide même si les marchés sont désactivés.
- React/Inertia : formulaire invité, coordonnées, réception, récapitulatif, envoi et confirmation. Lien réel depuis le panier, logo et palette noir/or conservés. Sélecteur de marché masqué durant la commande et sur la confirmation ; aucun paiement ni remise proposés.
- Filament : demandes invitées avec détail privé et historique, confirmation d'appel, annulation, réactivation ; configuration des zones et points de retrait. Les rôles administrateur/responsable ont ces permissions ; le personnel ne voit pas les coordonnées par défaut.
- Configuration `orders.php`, exemple `.env`, traductions `lang/fr/orders.php`, README et tests. La limite du script MySQL passe de 180 à 300 secondes pour la suite agrandie ; les workers de concurrence restent limités individuellement.

## Commandes exécutées

PHP 8.3.2 utilisé via le PATH de la session.

```text
git status --short
php artisan migrate --seed --no-interaction
php artisan test --compact --filter=GuestOrderTest
php artisan test --compact
php tools/verify-mysql.php
php vendor/bin/pint
php vendor/bin/pint --test
php artisan migrate:status
npm run build
git diff --check
```

Les migrations ont été appliquées à la base MySQL configurée et les permissions mises à jour sans compte ni données commerciales fictives. Le `.env` existant n'a pas été modifié. Aucune dépendance supplémentaire, API distincte ou passerelle de paiement.

## Vérifications

- Tests ciblés des commandes : **18 réussis, 165 assertions**. Ils couvrent la création invitée, livraison/retrait, snapshots, chiffrement, reprise après interruption, non-effacement d'un nouveau panier, refus des ruptures et des prix changés, coordonnées obligatoires/e-mail facultatif, pays/dépôt, liens signés et expiration de liens, absence de consultation non autorisée, workflow staff, CSRF et XAF entier.
- Suite SQLite : **103 réussis, 8 ignorés, 1035 assertions**. Les ignorés sont les tests propres à MySQL, exécutés séparément.
- Suite MySQL finale : **111 tests réussis, 1168 assertions**, 173,11 s. Elle inclut deux nouveaux tests avec processus indépendants : demandes de deux clients pour la dernière unité (une seule commande/réservation persistée), et reprise simultanée d'une même soumission (une seule commande/réservation/historique). Les neuf migrations sont appliquées à la base configurée.
- Le script vérifie aussi migrations fraîches, double seeding, rollback puis réinstallation dans une base temporaire supprimée après utilisation. Les reprises et échecs ne laissent aucune commande ou réservation partielle.
- TypeScript/Vite : succès, 703 modules, 10,91 s pour le build final des pages. Pint : succès. `git diff --check` : succès.

Les vérifications du formulaire et des workflows utilisent les réponses HTTP et les pages/actions Livewire réelles, avec des données de test isolées. Aucune fausse commande n'a été créée dans la base de travail pour obtenir un aperçu navigateur.

## Exploitation et limites

- Configurer de vraies zones et points de retrait dans Filament. Sans point compatible, seul le mode livraison est proposé. Les frais de zone restent des estimations ; frais/taxes et total définitifs sont saisis après appel, sans calcul fiscal automatique. Les règles fiscales et juridiques Canada/Cameroun restent à faire valider.
- Délais : réservation initiale `INVENTORY_RESERVATION_HOURS` (24 h), paiement après appel `ORDER_PAYMENT_HOURS` (24 h), lien signé `ORDER_LINK_DAYS` (30 jours, borné à 1–90). Le planificateur et le worker de file d'attente de phase 3 doivent fonctionner pour les expirations.
- Le lien signé est une capacité privée, à conserver confidentielle. Aucun e-mail ni WhatsApp automatique n'est envoyé dans cette phase. Conserver et sauvegarder `APP_KEY` pour les données chiffrées ; configurer correctement URL/HTTPS/proxy en déploiement.
- Les variantes, prix et coordonnées figés sont conservés dans la commande. Une réactivation conserve les prix des articles et exige une nouvelle confirmation des frais ; un point de retrait déplacé/désactivé n'est pas remplacé silencieusement.
- Une reprise de création déjà traitée retourne l'état actuel de la commande sans prolonger sa réservation. Les actions staff obsolètes sont refusées par le numéro de version ; elles ne répètent ni transitions ni mouvements.
- Le rollback de la seule phase 5 laisse volontairement `inventory_reservations.requested_by` nullable pour ne pas invalider d'éventuelles réservations invitées historiques. Le rollback complet sur une base fraîche est vérifié.
- Les statuts de paiement/remise sont préparés mais aucun enregistrement de paiement, remboursement, expédition ou retrait physique n'est disponible. Une commande portant déjà un paiement ou une remise n'est pas automatiquement annulée/expirée par ces workflows. Les opérations correspondantes seront implémentées en phase 6 avec la vérification du paiement intégral.

Aucun commit effectué. Arrêt pour revue avant **phase 6 : paiements manuels, vérification, préparation et remise sécurisée**.
