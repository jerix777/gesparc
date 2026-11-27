<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identifiant d'origine d'un équipement repris d'une ancienne application (inventaire du TPI de Bouaké…) :
 * relancer la reprise met la fiche à jour au lieu de la dupliquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->string('source_ref', 40)->nullable()->after('observations');
            $table->unique(['institution_id', 'source_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique(['institution_id', 'source_ref']);
            $table->dropColumn('source_ref');
        });
    }
};
