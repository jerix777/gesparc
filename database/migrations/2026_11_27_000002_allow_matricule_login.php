<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beaucoup d'agents n'ont pas d'adresse e-mail : ils se connectent avec leur matricule.
 * Le mot de passe provisoire remis par l'administrateur doit être changé à la première connexion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unique('matricule');
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
            $table->dropColumn('must_change_password');
        });
    }
};
