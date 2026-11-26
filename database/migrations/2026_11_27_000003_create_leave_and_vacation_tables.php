<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Personnel, lot 1 : autorisations d'absence, vacances judiciaires (campagnes, demandes, listes
 * définitives), jours fériés et historique des étapes de chaque demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('numero', 20);
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif');
            $table->string('precision', 250)->nullable();
            $table->date('debut');
            $table->date('fin');
            $table->unsignedSmallInteger('jours_ouvres');
            $table->unsignedSmallInteger('jours_calendaires');
            $table->string('statut', 20)->default('en_attente'); // en_attente | transmise | accordee | refusee | annulee
            $table->foreignId('first_decider_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('first_decided_at')->nullable();
            $table->foreignId('decider_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('decider_fonction')->nullable();
            $table->boolean('signe_po')->default(false); // décision prise par un adjoint « pour ordre »
            $table->timestamp('decided_at')->nullable();
            $table->string('motif_refus')->nullable();
            $table->string('verification_code', 32)->nullable()->unique();
            $table->timestamps();
            $table->unique(['institution_id', 'numero']);
            $table->index(['institution_id', 'statut']);
            $table->index(['staff_id', 'debut']);
        });

        Schema::create('vacation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->json('mois'); // ex. ["Août", "Septembre"]
            $table->string('statut', 20)->default('ouverte'); // ouverte | cloturee
            $table->foreignId('closed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('reouvertures')->nullable(); // historique des réouvertures (motifs)
            $table->timestamps();
            $table->unique(['institution_id', 'annee']);
        });

        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vacation_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('mois_demande', 20);
            $table->string('mois_retenu', 20)->nullable(); // 1er niveau (provisoire)
            $table->string('mois_accorde', 20)->nullable(); // décision définitive
            $table->string('statut', 20)->default('en_attente'); // en_attente | transmise | validee | refusee
            $table->foreignId('first_decider_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('first_decided_at')->nullable();
            $table->foreignId('decider_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('observation', 500)->nullable();
            $table->string('motif_refus', 500)->nullable();
            $table->timestamps();
            $table->index(['vacation_campaign_id', 'statut']);
        });

        Schema::create('vacation_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vacation_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('numero', 30);
            $table->string('mois', 20)->nullable(); // null = liste générale
            $table->json('lignes');
            $table->foreignId('edited_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('scan_path')->nullable(); // liste signée à la main puis numérisée
            $table->foreignId('scan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scan_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'numero']);
        });

        Schema::create('workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('action', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auteur')->nullable(); // nom et fonction au moment de l'action
            $table->string('details', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_events');
        Schema::dropIfExists('vacation_lists');
        Schema::dropIfExists('vacation_requests');
        Schema::dropIfExists('vacation_campaigns');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('public_holidays');
    }
};
