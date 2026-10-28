#!/usr/bin/env bash
# Executed on the production server by GitHub Actions, never on pull requests.
set -Eeuo pipefail
umask 0022

fail() { printf '%s\n' "$*" >&2; exit 1; }
[[ $# -eq 5 ]] || fail 'Usage: deploy-production.sh PATH SHA GITHUB_REPOSITORY PHP_BIN COMPOSER_BIN'
deploy_path=$1
revision=$2
repository=$3
php_bin=$4
composer_bin=$5
[[ "$deploy_path" == /* && "$deploy_path" != / ]] || fail 'Chemin de projet absolu requis.'
[[ "$revision" =~ ^[a-f0-9]{40}$ ]] || fail 'Révision Git invalide.'
[[ "$repository" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || fail 'Dépôt GitHub invalide.'
for executable in git flock npm curl "$php_bin" "$composer_bin"; do
    command -v "$executable" >/dev/null || fail "Commande absente : $executable"
done
cd -- "$deploy_path"
[[ -d .git && -f artisan && -f composer.lock && -f package-lock.json ]] || fail 'Le chemin doit contenir le dépôt Laravel initialisé.'
[[ -f .env && -f vendor/autoload.php ]] || fail 'Préparer le .env et installer Composer avant le premier déploiement ; voir docs/github-deployment.md.'
[[ ! -e public/hot ]] || fail 'Un fichier Vite public/hot est présent : arrêter le serveur de développement et le retirer avant production.'
exec 9>.git/nzhinu-deployment.lock
flock -n 9 || fail 'Un déploiement est déjà en cours sur ce serveur.'
[[ -z "$(git status --porcelain --untracked-files=normal)" ]] || fail 'Le dépôt serveur contient des modifications ; les préserver et les résoudre avant de déployer.'
[[ "$(git branch --show-current)" == main ]] || fail 'Le dépôt serveur doit être sur main.'
origin=$(git remote get-url origin)
case "$origin" in
    "git@github.com:$repository"|"git@github.com:$repository.git"|"https://github.com/$repository"|"https://github.com/$repository.git"|"ssh://git@github.com/$repository"|"ssh://git@github.com/$repository.git") ;;
    *) fail 'origin doit pointer vers le même dépôt GitHub que le workflow (sans jeton dans son URL).' ;;
esac

# Read Laravel configuration without printing secrets or sourcing .env as shell code.
"$php_bin" -r '
require "vendor/autoload.php";
// Validate .env itself, even if an old config cache is present.
putenv("APP_CONFIG_CACHE=".sys_get_temp_dir()."/nzhinu-preflight-".bin2hex(random_bytes(16)).".php");
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment("production") || config("app.debug") || !config("app.key") || config("database.default") !== "mysql" || config("app.maintenance.driver") !== "file") {
    fwrite(STDERR, "Configuration requise : production, debug désactivé, clé existante, MySQL, maintenance file.\n"); exit(1);
}
if ($app->isDownForMaintenance()) { fwrite(STDERR, "Maintenance déjà active : intervention requise avant de relancer.\n"); exit(1); }
'
for directory in storage bootstrap/cache; do
    [[ -w "$directory" ]] || fail "Répertoire non accessible en écriture : $directory"
done
git fetch --no-tags origin refs/heads/main:refs/remotes/origin/main
git cat-file -e "$revision^{commit}"
git merge-base --is-ancestor "$revision" origin/main || fail 'La révision ne fait pas partie de main.'
git merge-base --is-ancestor HEAD "$revision" || fail 'Déploiement antérieur ou historique divergent : intervention manuelle requise.'

maintenance=0
on_failure() {
    result=$?
    if [[ $maintenance -eq 1 ]]; then
        printf '%s\n' 'Échec : maintenance conservée. Consulter les journaux et réparer avant php artisan up. Aucune migration annulée automatiquement.' >&2
    fi
    exit "$result"
}
trap on_failure ERR
"$php_bin" artisan down --retry=60
maintenance=1
git merge --ff-only "$revision"
[[ "$(git rev-parse HEAD)" == "$revision" ]] || fail 'Révision installée incorrecte.'
"$composer_bin" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
npm ci --include=dev
npm run build
"$php_bin" artisan migrate --force
"$php_bin" artisan storage:link
"$php_bin" artisan optimize
"$php_bin" artisan filament:optimize
"$php_bin" artisan queue:restart
# Probe the actual framework while maintenance still protects the public site.
"$php_bin" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make("db")->connection()->getPdo();
if (!is_file("public/build/manifest.json")) { throw new RuntimeException("Manifest Vite absent."); }
'
"$php_bin" artisan up
maintenance=0
curl --fail --silent --show-error --location --max-time 20 --retry 2 --retry-all-errors \
    'https://nzhinu-creation.com/up' >/dev/null
printf 'Déploiement terminé : %s\n' "$revision"
