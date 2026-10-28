<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Services\StaffService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateStaff extends Command
{
    protected $signature = 'staff:create';

    protected $description = 'Créer un compte du personnel (opérateur serveur autorisé uniquement)';

    public function handle(StaffService $service): int
    {
        $data = [
            'name' => $this->ask('Nom'),
            'email' => strtolower(trim((string) $this->ask('Adresse e-mail'))),
            'password' => $this->secret('Mot de passe (12 caractères minimum, majuscule, minuscule, chiffre et symbole)'),
            'role' => $this->choice('Rôle', array_column(StaffRole::cases(), 'value'), 2),
        ];

        try {
            $service->createForServerOperator($data);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Compte créé. Connexion disponible sur /admin/login.');

        return self::SUCCESS;
    }
}
