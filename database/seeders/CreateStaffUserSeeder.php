<?php

namespace Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use RuntimeException;

class CreateStaffUserSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command === null || $this->command->option('no-interaction')) {
            throw new RuntimeException('Lancez ce seeder dans une session Artisan interactive.');
        }

        if ($this->command->call('staff:create') !== Command::SUCCESS) {
            throw new RuntimeException('Le compte du personnel n’a pas été créé.');
        }
    }
}
