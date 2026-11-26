<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Réforme en deux temps : l'informaticien propose, le responsable valide ou rejette.
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->text('reforme_motif')->nullable()->after('observations');
            $table->foreignId('reforme_proposee_par')->nullable()->after('reforme_motif')->constrained('users')->nullOnDelete();
            $table->timestamp('reforme_proposee_le')->nullable()->after('reforme_proposee_par');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reforme_proposee_par');
            $table->dropColumn(['reforme_motif', 'reforme_proposee_le']);
        });
    }
};
