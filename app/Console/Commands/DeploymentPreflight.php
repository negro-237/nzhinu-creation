<?php

namespace App\Console\Commands;

use App\Services\DeploymentReadiness;
use Illuminate\Console\Command;

class DeploymentPreflight extends Command
{
    protected $signature = 'boutique:preflight {--json : Résultat JSON sans secrets}';

    protected $description = 'Contrôler en lecture seule les prérequis techniques et les catalogues avant mise en ligne';

    public function handle(DeploymentReadiness $service): int
    {
        $checks = $service->checks();
        $blocked = collect($checks)->contains(fn ($check) => ! $check['ok'] && $check['blocking']);
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => ! $blocked, 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Contrôle', 'Résultat'], array_map(fn ($check) => [$check['label'], $check['ok'] ? 'OK' : ($check['blocking'] ? 'À corriger' : 'Facultatif / désactivé')], $checks));
            $this->line('Contrôles externes distincts : TLS/DNS, SMTP réel, worker, scheduler, sauvegarde/restauration et validation métier.');
        }

        return $blocked ? self::FAILURE : self::SUCCESS;
    }
}
