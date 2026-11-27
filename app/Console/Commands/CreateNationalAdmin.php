<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class CreateNationalAdmin extends Command
{
    protected $signature = 'sigaci:create-admin {email} {--name=Administrateur national}';

    protected $description = 'Crée le premier compte administrateur national (installation). '
        .'Mot de passe demandé au clavier, ou lu dans la variable d\'environnement SIGACI_ADMIN_MDP (installation automatique).';

    public function handle(): int
    {
        // Variable d'environnement plutôt qu'option : un mot de passe en ligne de commande resterait dans l'historique.
        $password = getenv('SIGACI_ADMIN_MDP') ?: $this->secret('Mot de passe (8 caractères minimum)');
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
