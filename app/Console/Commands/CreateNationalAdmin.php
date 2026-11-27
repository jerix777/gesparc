<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class CreateNationalAdmin extends Command
{
    protected $signature = 'gesparc:create-admin {email} {--name=Administrateur national}';

    protected $description = 'Crée le premier compte administrateur national (installation)';

    public function handle(): int
    {
        $password = $this->secret('Mot de passe (8 caractères minimum)');
        if (strlen((string) $password) < 8) {
            $this->error('Mot de passe trop court.');

            return self::FAILURE;
        }
        User::updateOrCreate(['email' => $this->argument('email')], [
            'name' => $this->option('name'),
            'password' => $password,
            'role' => Role::AdminNational,
            'institution_id' => null,
            'active' => true,
        ]);
        $this->info('Administrateur national prêt : '.$this->argument('email'));

        return self::SUCCESS;
    }
}
