# Déploiement sur GitHub Actions

Le fichier `.github/workflows/deploy.yml` remplace le pipeline GitLab pour ce projet Laravel. Le fichier `.gitlab-ci.yml` fourni est conservé comme référence ; ses commandes NestJS, Prisma et PM2 ne sont pas exécutées par GitHub.

## Fonctionnement

Un push sur `main` lance les tests PHP, Pint, la compilation TypeScript/Vite et le contrôle de démarrage avec les caches de production. Le déploiement SSH attend leur réussite. Le bouton **Actions → Vérification et déploiement Laravel → Run workflow**, sur `main`, permet aussi de le lancer. Une autre branche peut être vérifiée manuellement mais ne sera pas déployée.

Les tests CI utilisent SQLite : les tests spécifiques à MySQL restent ignorés par cette suite. Avant la première mise en ligne et lors de changements de migrations ou de stocks, exécuter aussi `php tools/verify-mysql.php` dans un environnement de recette MySQL, avec un compte autorisé à créer des bases temporaires. Ne pas lui fournir les identifiants de production. La recette navigateur reste disponible via les commandes Playwright du projet ; elle n'est pas incluse dans ce workflow.

Le script `tools/deploy-production.sh` réserve le serveur avec `flock`, refuse les modifications locales et vérifie le dépôt GitHub d'origine. Il récupère `main`, installe exactement le commit vérifié sans réinitialisation forcée, puis exécute sous maintenance :

1. `composer install --no-dev --optimize-autoloader` ;
2. `npm ci --include=dev` et `npm run build` ;
3. `php artisan migrate --force` et `storage:link` ;
4. `php artisan optimize`, `filament:optimize` et `queue:restart` ;
5. contrôle de connexion MySQL et du manifeste Vite, sortie de maintenance, puis requête HTTPS sur `/up`.

Les déploiements sont séquentiels. Une ancienne révision ne peut pas remplacer automatiquement une révision plus récente. La compilation se fait sur le serveur, comme dans le modèle fourni : prévoir assez de mémoire et d'espace disque ainsi qu'un accès réseau à GitHub, Composer et npm. Il y a une interruption de service pendant l'installation ; ce n'est pas un déploiement sans coupure.

## Secrets et variables GitHub

Créer l'environnement **production** dans **Settings → Environments** du dépôt GitHub. Limiter ses branches de déploiement à `main`. Ajouter les secrets dans cet environnement (ou dans les secrets Actions du dépôt) :

| Secret | Contenu |
| --- | --- |
| `SSH_PRIVATE_KEY` | Clé privée dédiée permettant au runner GitHub de se connecter au compte `admin` du serveur. Placer sa clé publique dans `~admin/.ssh/authorized_keys`. Utiliser une clé utilisable sans saisie interactive. |
| `SSH_KNOWN_HOSTS` | Ligne(s) OpenSSH de la clé d'hôte vérifiée du serveur, pour le port 5804. |

La vérification de l'hôte est stricte. Depuis un poste de confiance, collecter sa clé avec :

```bash
ssh-keyscan -p 5804 -H 180.149.199.229
```

Comparer son empreinte avec celle affichée par la console ou l'administrateur de l'hébergement avant de la placer dans le secret. Une collecte réseau seule n'authentifie pas le serveur. Le workflow ne fait pas confiance automatiquement au résultat de `ssh-keyscan`.

Les variables Actions suivantes sont facultatives : les valeurs du fichier fourni sont déjà les valeurs par défaut.

| Variable | Défaut |
| --- | --- |
| `SSH_HOST` | `180.149.199.229` |
| `SSH_PORT` | `5804` |
| `SSH_USER` | `admin` |
| `DEPLOY_PATH` | `/home/admin/web/nzhinu-creation.com/public_html` |
| `PHP_BIN` | `php` |
| `COMPOSER_BIN` | `composer` |

Les deux dernières variables acceptent un nom de commande ou un chemin exécutable, sans arguments. Le `PATH` de la session SSH non interactive doit aussi sélectionner le bon PHP pour Composer et contenir Node/npm. Le contrôle HTTPS final utilise `https://nzhinu-creation.com/up` ; modifier ce contrôle et l'URL de l'environnement dans le workflow si le domaine change.

## Préparer le serveur une première fois

Cette préparation est manuelle ; le workflow ne se connecte à aucun serveur tant que sa configuration n'est pas publiée et exécutée.

- Installer PHP 8.3 et les extensions requises par `composer.lock`, Composer 2, Node.js 24/npm, Git, Bash, `flock` et curl ; configurer MySQL 8 et PHP-FPM. Vérifier ces commandes dans une session SSH non interactive.
- Placer le dépôt **de cette application Laravel**, branche `main`, dans `DEPLOY_PATH`. Son `origin` doit correspondre au dépôt GitHub exécutant le workflow. Si le chemin contient déjà une autre application, la préserver et préparer un autre répertoire avant de changer la variable. Le script refuse un dépôt différent.
- Pour un dépôt privé, le serveur a besoin de sa propre clé de déploiement GitHub en lecture seule et d'un `known_hosts` GitHub vérifié. Cette connexion **serveur → GitHub** est distincte de la clé **runner → serveur**. Ne pas mettre de jeton dans l'URL d'`origin`.
- Créer `.env` sur le serveur à partir du modèle et renseigner les vrais paramètres : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://nzhinu-creation.com`, `APP_MAINTENANCE_DRIVER=file`, MySQL, SMTP, coordonnées et marché public CM. Conserver l'`APP_KEY` d'une application existante. Pour une installation neuve uniquement, générer la clé une seule fois après installation des dépendances. Ne jamais copier les identifiants dans le workflow ou Git.
- Dans le répertoire du projet, installer une première fois les dépendances avec `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`. Cela prépare `vendor/autoload.php`, requis pour entrer en maintenance au premier déploiement automatique. Exécuter `php artisan config:clear` si un ancien cache de configuration existe.
- Donner à l'utilisateur SSH et à PHP-FPM les droits appropriés sur `storage` (y compris ses sous-dossiers) et `bootstrap/cache`. Ne pas utiliser de permissions universelles `777`. Les médias et `.env` restent hors Git et doivent être sauvegardés.
- Configurer la racine web du domaine sur **`/home/admin/web/nzhinu-creation.com/public_html/public`**. `public_html` est ici le dossier du projet, pas la racine à exposer. Si le panneau d'hébergement impose `public_html` comme racine publique, déplacer le projet hors de cette racine et adapter la configuration du domaine et `DEPLOY_PATH` avant de lancer le workflow.
- Configurer TLS et les réécritures Laravel. Désactiver la validation permanente des anciens scripts PHP par OPcache (`opcache.validate_timestamps=0`) uniquement si une procédure de rechargement PHP-FPM est ajoutée ; le workflow actuel ne redémarre pas PHP-FPM. Conserver la validation des timestamps active avec un délai approprié pour cette procédure.

Après le premier déploiement réussi, initialiser au besoin les données de base avec `php artisan db:seed --force`, puis créer le compte personnel avec la procédure d'administration du projet. Vérifier les marchés actifs et les données créées avant cette initialisation. Aucun seeder, produit de démonstration ou changement de stock n'est lancé automatiquement par le workflow.

Configurer un superviseur pour maintenir `php artisan queue:work --tries=3 --timeout=60` (le `retry_after` de la connexion doit dépasser le timeout ; sa valeur par défaut est 90 secondes). `queue:restart` demande aux workers de sortir après leur tâche ; le superviseur doit les relancer. Ce workflow n'installe pas le service. Configurer également la tâche cron de l'utilisateur applicatif, avec le chemin du bon PHP :

```cron
* * * * * cd /home/admin/web/nzhinu-creation.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Lancer `php artisan boutique:preflight` avant ouverture commerciale et corriger ses réserves : catalogue, stocks réels, livraison/retrait, configuration et notifications. Vérifier manuellement le domaine, les images, le panier, une commande de recette, l'administration, le worker et le scheduler. Le contrôle `/up` du workflow ne couvre pas ces parcours.

## Échec et restauration

Sauvegarder la base, `.env` et les médias avant la première migration et chaque livraison qui modifie les données. Garder une sauvegarde restaurable hors du répertoire de travail.

Un échec après l'entrée en maintenance laisse cette maintenance active. Consulter le journal de l'action et `storage/logs/laravel.log`, réparer l'installation ou restaurer une sauvegarde cohérente, puis vérifier le site avant `php artisan up`. Une relance est volontairement refusée tant qu'une maintenance existe déjà : intervenir avant de relancer. Si seule la requête HTTPS échoue après `artisan up`, le workflow échoue mais le site reste sorti de maintenance ; vérifier DNS, TLS et le serveur web.

Il n'y a ni `git reset --hard`, ni rollback automatique de migrations, ni régénération de clé. Un retour arrière doit prendre en compte la compatibilité entre code, schéma et données. Le script interdit aussi les déploiements si le dépôt a des fichiers non suivis non ignorés ; déplacer les sauvegardes hors du projet et préserver toute modification utile avant de recommencer.

## Vérification locale de la configuration

```bash
bash -n tools/deploy-production.sh
actionlint -shellcheck= .github/workflows/deploy.yml
php artisan test --compact
php vendor/bin/pint --test
npm run build
php tools/verify-production.php
```

`actionlint` valide le workflow et ses expressions ; la commande ci-dessus désactive l'intégration ShellCheck lorsque cet outil n'est pas installé. Ces vérifications ne prouvent pas que l'accès SSH, les secrets, les permissions serveur ou les migrations réelles fonctionneront. Aucun déploiement distant n'a été exécuté lors de l'ajout de ces fichiers.

Contrôles locaux du 28 octobre 2026 : `actionlint` 1.7.12 et `bash -n` réussis ; appels sans arguments et avec une révision invalide refusés avant toute opération ; lecture de configuration sans ancien cache vérifiée ; 170 tests PHP réussis (2001 assertions), 14 tests spécifiques MySQL ignorés ; Pint et compilation Vite/TypeScript réussis. La sonde de production isolée a confirmé les caches et les réponses HTTP 200 de `/up`, `/`, `/boutique` et `/admin/login`, ainsi que le refus d'un hôte non autorisé. La validation d'une exécution réelle sur GitHub et sur l'hébergement reste à faire après configuration des secrets et préparation du serveur.

Références : [déploiement Laravel](https://laravel.com/docs/12.x/deployment), [environnements GitHub Actions](https://docs.github.com/en/actions/concepts/workflows-and-actions/deployment-environments), [sécurité des actions et références immuables](https://docs.github.com/en/actions/reference/security/secure-use).
