<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    function compte(string $pseudo): string
    {
        return $this->postJson('/api/inscription', ['pseudo' => $pseudo, 'mot_de_passe' => 'secret123'])
            ->assertCreated()->json('jeton');
    }

    function en(string $jeton) { return $this->withHeader('Authorization', 'Bearer ' . $jeton); }

    public function test_parcours_complet(): void
    {
        $kouadio = $this->compte('Kouadio');
        $awa = $this->compte('Awa');
        $this->postJson('/api/inscription', ['pseudo' => 'kouadio', 'mot_de_passe' => 'secret123'])->assertStatus(422);
        $this->postJson('/api/inscription', [])->assertStatus(422)->assertJsonPath('message', 'Choisis un pseudo. (and 1 more error)');
        $this->postJson('/api/connexion', ['pseudo' => 'Awa', 'mot_de_passe' => 'faux'])->assertStatus(401);
        $this->postJson('/api/connexion', ['pseudo' => 'Awa', 'mot_de_passe' => 'secret123'])->assertOk();

        // pas encore amis : invitation refusée par le serveur
        $this->en($kouadio)->postJson('/api/invitations', ['pseudo' => 'Awa', 'mode' => 'coop', 'code' => 'ABC123'])->assertStatus(403);

        // amitié
        $this->en($kouadio)->postJson('/api/amis', ['pseudo' => 'Awa'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $id = $this->en($awa)->getJson('/api/amis')->assertOk()->json('recues.0.id');
        $this->en($awa)->postJson("/api/amis/$id/accepter")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->en($kouadio)->getJson('/api/amis')->assertJsonPath('amis.0.pseudo', 'Awa');

        // invitation puis refus
        $this->en($kouadio)->putJson('/api/appareil', ['fcm_token' => null])->assertOk();
        $inv = $this->en($kouadio)->postJson('/api/invitations', ['pseudo' => 'Awa', 'mode' => 'coop', 'code' => 'abc123', 'libelle' => 'Gbaka · Adjamé'])
            ->assertCreated()->assertJsonPath('notifiee', false)->assertJsonPath('invitation.etat', 'envoyee')->json('invitation.id');
        $this->app['auth']->forgetGuards();
        $this->en($awa)->getJson('/api/moi')->assertJsonPath('invitations.0.code', 'ABC123')->assertJsonPath('invitations.0.hote', 'Kouadio');
        $this->en($awa)->postJson("/api/invitations/$inv/reponse", ['reponse' => 'refuser'])->assertOk()->assertJsonPath('invitation.etat', 'refusee');
        $this->en($awa)->postJson("/api/invitations/$inv/reponse", ['reponse' => 'accepter'])->assertStatus(410);
        $this->app['auth']->forgetGuards();
        $this->en($kouadio)->getJson('/api/invitations/envoyees')->assertJsonPath('invitations.0.etat', 'refusee');

        // nouvelle invitation, acceptée
        $inv2 = $this->en($kouadio)->postJson('/api/invitations', ['pseudo' => 'Awa', 'mode' => 'course', 'code' => 'XYZ789'])->json('invitation.id');
        $this->app['auth']->forgetGuards();
        $this->en($awa)->postJson("/api/invitations/$inv2/reponse", ['reponse' => 'accepter'])->assertJsonPath('invitation.code', 'XYZ789');

        // l'invitation d'un autre ne se touche pas
        $tiers = $this->compte('Yao');
        $inv3 = $this->en($kouadio)->postJson('/api/invitations', ['pseudo' => 'Awa', 'mode' => 'coop', 'code' => 'QQQ111'])->json('invitation.id');
        $this->app['auth']->forgetGuards();
        $this->en($tiers)->postJson("/api/invitations/$inv3/reponse", ['reponse' => 'accepter'])->assertStatus(404);
        $this->travel(11)->minutes();
        $this->app['auth']->forgetGuards();
        $this->en($awa)->getJson('/api/invitations')->assertJsonCount(0, 'invitations');
    }
}
