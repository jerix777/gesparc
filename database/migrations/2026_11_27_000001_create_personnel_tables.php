<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Personnel » (lot 1, fondations) : fiches du personnel, catégories,
 * fonctions d'autorité attribuées nommément et règles de décision par catégorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('nom');
            $table->boolean('magistrat')->default(false); // exclus des listes définitives de vacances
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('matricule', 40)->nullable();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->string('fonction')->nullable();
            $table->string('telephone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('bureau', 40)->nullable();
            $table->date('date_prise_service')->nullable();
            $table->string('statut', 20)->default('actif'); // actif | sorti
            $table->date('date_sortie')->nullable();
            $table->string('motif_sortie')->nullable();
            $table->string('source_ref', 40)->nullable(); // identifiant d'origine (application TPI)
            $table->timestamps();
            $table->index(['institution_id', 'statut']);
            $table->unique(['institution_id', 'matricule']);
            $table->unique(['institution_id', 'source_ref']);
        });

        Schema::create('staff_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('position', 40);
            $table->timestamps();
            $table->unique(['staff_id', 'position']);
            $table->index(['institution_id', 'position']);
        });

        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_category_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30); // absence | vacances | attestation
            $table->json('first_level')->nullable(); // fonctions habilitées au 1er niveau
            $table->string('final_level', 40)->nullable(); // fonction qui décide définitivement
            $table->json('copy_to')->nullable(); // fonctions informées de la décision
            $table->timestamps();
            $table->unique(['staff_category_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('staff_positions');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('staff_categories');
    }
};
