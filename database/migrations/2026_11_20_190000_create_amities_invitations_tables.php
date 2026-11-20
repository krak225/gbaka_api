<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Amitié : une demande de « demandeur » vers « receveur », acceptée ou non. Une seule ligne par paire de joueurs.
        Schema::create('amities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receveur_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('acceptee')->default(false);
            $table->timestamps();
            $table->unique(['demandeur_id', 'receveur_id']);
            $table->index(['receveur_id', 'acceptee']);
        });

        // Invitation à jouer : l'hôte a créé une partie en ligne (Unity Relay) et envoie son code à un ami.
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hote_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invite_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 12);                 // « coop » (jeu à deux) ou « course » (course en ligne)
            $table->string('code', 12);                 // code de la partie Relay
            $table->string('libelle', 80)->nullable();  // ex. « Gbaka · Adjamé – Yopougon », « Course · Pont HKB »
            $table->string('etat', 10)->default('envoyee');   // envoyee, acceptee, refusee, annulee
            $table->timestamp('expire_le');
            $table->timestamps();
            $table->index(['invite_id', 'etat']);
            $table->index(['hote_id', 'etat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('amities');
    }
};
