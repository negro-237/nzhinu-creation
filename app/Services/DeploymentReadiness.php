<?php

namespace App\Services;

use App\Enums\StaffRole;
use App\Models\Inventory;
use App\Models\Market;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeploymentReadiness
{
    /** Read only. Never include secret values or customer coordinates in this report. */
    public function checks(): array
    {
        $checks = [];
        $add = function (string $code, string $label, bool $ok, bool $blocking = true) use (&$checks) {
            $checks[] = compact('code', 'label', 'ok', 'blocking');
        };
        $add('environment', 'Environnement production', app()->environment('production'));
        $add('debug', 'Débogage désactivé', ! config('app.debug'));
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $add('https', 'URL publique HTTPS et domaine de production', parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https'
            && is_string($host) && ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.test'));
        $key = (string) config('app.key');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $add('key', 'Clé de chiffrement valide (sans modification)', is_string($decoded) && Encrypter::supported($decoded, config('app.cipher')));
        $add('session_secure', 'Cookies de session sécurisés', (bool) config('session.secure'));
        $add('session_encrypted', 'Sessions chiffrées', (bool) config('session.encrypt'));
        $add('session_http_only', 'Cookies HttpOnly et SameSite adaptés', config('session.http_only') && in_array(config('session.same_site'), ['lax', 'strict'], true));
        $add('queue', 'File d’attente persistante', in_array(config('queue.default'), ['database', 'redis', 'sqs', 'beanstalkd'], true));
        $add('cache', 'Cache partagé pour les verrous et limitations', in_array(config('cache.default'), ['database', 'redis', 'memcached'], true));
        $add('build', 'Assets frontend compilés et logo présents', is_file(public_path('build/manifest.json')) && is_file(public_path('images/logo-transparent.png')));
        $add('hot', 'Serveur Vite de développement absent', ! is_file(public_path('hot')));
        $add('writable', 'Répertoires Laravel accessibles en écriture', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')));
        $mailer = (string) config('mail.default');
        $email = (bool) config('communications.order_email_enabled');
        $add('mail', 'Transport réel et expéditeur valide si les e-mails sont activés', ! $email || (! in_array($mailer, ['log', 'array', 'failover', 'roundrobin'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) && ! str_ends_with((string) config('mail.from.address'), '@example.com')));
        $add('email_enabled', 'E-mails clients activés (facultatif)', $email, false);
        foreach (array_map('strtolower', config('storefront.markets')) as $code) {
            $phone = (string) config('communications.whatsapp_'.$code);
            $add('whatsapp_'.$code, 'Numéro WhatsApp '.strtoupper($code).' valide ou désactivé', $phone === '' || preg_match('/\A[1-9][0-9]{7,14}\z/', $phone) === 1);
        }
        try {
            $add('database', 'Connexion MySQL disponible', DB::getDriverName() === 'mysql' && DB::select('SELECT 1 AS connected') !== []);
            $ready = collect(['markets', 'orders', 'order_payments', 'cms_pages', 'order_notification_deliveries'])->every(fn ($table) => Schema::hasTable($table));
            $add('schema', 'Schéma du MVP installé', $ready);
            if ($ready) {
                $add('administrator', 'Au moins un administrateur autorisé', User::role(StaffRole::Administrator)->get(['id'])->contains(fn ($user) => $user->can('access admin') && $user->can('manage staff')));
                $enabled = config('storefront.markets');
                $add('public_markets', 'Au moins un marché public reconnu configuré', count($enabled) > 0 && array_diff($enabled, ['CA', 'CM']) === []);
                foreach (array_intersect_key(['CA' => 'CAD', 'CM' => 'XAF'], array_flip($enabled)) as $code => $currency) {
                    $market = Market::where('code', $code)->first();
                    $active = $market && $market->is_active && $market->currency->value === $currency;
                    $add('market_'.$code, 'Marché '.$code.' actif avec sa devise', (bool) $active);
                    $stock = $active && Inventory::whereHas('location', fn ($q) => $q->where('market_id', $market->id)->where('is_active', true)->where('is_placeholder', false)->whereNull('stock_locations.deleted_at'))
                        ->whereColumn('quantity_on_hand', '>', 'quantity_reserved')->whereHas('variant', fn ($q) => $q->where('is_active', true)->whereNull('product_variants.deleted_at')
                        ->whereHas('product', fn ($q) => $q->availableIn($market)->whereNull('products.deleted_at'))->whereHas('prices', fn ($q) => $q->where('market_id', $market->id)->where('currency', $currency)))->exists();
                    $add('catalog_'.$code, 'Catalogue commandable avec stock local '.$code, (bool) $stock);
                }
                $hasMedia = Schema::hasTable('media') && DB::table('media')->where('disk', 'public')->exists();
                $add('public_media', 'Stockage des médias publics accessible si utilisé', ! $hasMedia || (is_dir(public_path('storage')) && realpath(public_path('storage')) === realpath(storage_path('app/public'))));
            }
        } catch (\Throwable) {
            $add('database_check', 'Vérification des données impossible ; contrôler connexion et migrations', false);
        }

        return $checks;
    }
}
