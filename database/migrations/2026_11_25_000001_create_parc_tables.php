<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiel national des types d'équipement.
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('categorie')->default('materiel'); // materiel | consommable
            $table->timestamps();
        });

        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('numero_inventaire');
            $table->foreignId('equipment_type_id')->constrained()->restrictOnDelete();
            $table->string('designation');
            $table->string('marque')->nullable();
            $table->string('modele')->nullable();
            $table->string('numero_serie')->nullable();
            $table->text('caracteristiques')->nullable();
            $table->string('fournisseur')->nullable();
            $table->date('date_acquisition')->nullable();
            $table->unsignedBigInteger('prix_achat')->nullable(); // FCFA
            $table->date('fin_garantie')->nullable();
            $table->string('statut', 20)->default('en_stock');
            $table->string('etat', 20)->default('bon');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bureau')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'numero_inventaire']);
            $table->index(['institution_id', 'statut']);
        });

        // Historique : chaque changement d'état ou d'affectation.
        Schema::create('equipment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('type', 30); // reception, affectation, transfert, restitution, panne, maintenance, reparation, reforme
            $table->string('statut_avant', 20)->nullable();
            $table->string('statut_apres', 20);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('to_bureau')->nullable();
            $table->text('motif')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('numero');
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('categorie', 20)->default('materiel'); // materiel | cartouche
            $table->string('materiel');
            $table->unsignedSmallInteger('quantite')->default(1);
            $table->string('bureau')->nullable();
            $table->text('caracteristiques')->nullable();
            $table->text('observations')->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('motif_refus')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'numero']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('numero');
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('statut', 20)->default('ouvert'); // ouvert, en_cours, resolu, irreparable
            $table->string('prestataire')->nullable();
            $table->unsignedBigInteger('cout')->nullable(); // FCFA
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'numero']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('equipment_movements');
        Schema::dropIfExists('equipments');
        Schema::dropIfExists('equipment_types');
    }
};
