<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consommables (cartouches, toners, accessoires) suivis en quantité, par institution.
        Schema::create('consumables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('reference');                 // ex. HP 59A
            $table->string('designation');               // ex. Toner noir HP 59A
            $table->string('categorie', 30)->default('cartouche'); // cartouche | toner | accessoire
            $table->string('imprimantes_compatibles')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('seuil_alerte')->default(2);
            $table->timestamps();
            $table->unique(['institution_id', 'reference']);
        });

        Schema::create('consumable_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumable_id')->constrained()->cascadeOnDelete();
            $table->string('sens', 10); // entree | sortie
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('stock_apres');
            $table->foreignId('material_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('beneficiaire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif')->nullable(); // n° de bon de livraison, fournisseur, bénéficiaire…
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('consumable_id')->nullable()->after('materiel')->constrained()->nullOnDelete();
            $table->string('source', 20)->default('web')->after('statut'); // web | api (application TPI)
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumable_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('consumable_movements');
        Schema::dropIfExists('consumables');
    }
};
