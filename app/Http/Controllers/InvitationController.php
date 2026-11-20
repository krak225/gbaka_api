<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\Fcm;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    // L'hôte a créé sa partie et invite un ami : l'ami reçoit une notification avec le code.
    public function envoyer(Request $r)
    {
        $d = $r->validate([
            'pseudo' => 'required|string|max:20',
            'mode' => 'required|in:coop,course',
            'code' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/'],
            'libelle' => 'nullable|string|max:80',
        ]);
        $u = $r->user();
        $ami = User::parPseudo($d['pseudo']);
        if (!$ami || !$u->estAmiDe($ami)) return response()->json(['message' => 'Tu ne peux inviter que tes amis.'], 403);

        // une seule invitation en cours par ami et par partie
        Invitation::where('hote_id', $u->id)->where('invite_id', $ami->id)->where('etat', 'envoyee')->update(['etat' => 'annulee']);
        $inv = Invitation::create([
            'hote_id' => $u->id, 'invite_id' => $ami->id, 'mode' => $d['mode'], 'code' => strtoupper($d['code']),
            'libelle' => $d['libelle'] ?? null, 'expire_le' => now()->addMinutes(Invitation::DUREE_MINUTES),
        ]);
        $quoi = $d['mode'] === 'course' ? 'une course en ligne' : 'jouer à deux';
        $notifiee = Fcm::envoyer($ami, $u->pseudo . ' t\'invite', $u->pseudo . ' t\'invite à ' . $quoi
            . ($inv->libelle ? ' (' . $inv->libelle . ')' : '') . '. Touche pour accepter ou refuser.',
            ['type' => 'invitation', 'invitation' => $inv->id, 'mode' => $inv->mode, 'code' => $inv->code, 'hote' => $u->pseudo]);
        return response()->json(['invitation' => $inv->fiche(), 'notifiee' => $notifiee, 'en_ligne' => $ami->enLigne()], 201);
    }

    // invitations reçues encore valables (le jeu les montre au démarrage et quand on touche la notification)
    public function recues(Request $r)
    {
        $l = Invitation::with('hote', 'invite')->where('invite_id', $r->user()->id)->where('etat', 'envoyee')
            ->where('expire_le', '>', now())->latest()->limit(5)->get();
        return response()->json(['invitations' => $l->map->fiche()->values()]);
    }

    // l'hôte suit ses invitations (acceptée, refusée) pendant qu'il attend dans le salon
    public function envoyees(Request $r)
    {
        $l = Invitation::with('hote', 'invite')->where('hote_id', $r->user()->id)
            ->where('updated_at', '>', now()->subMinutes(Invitation::DUREE_MINUTES))->latest()->limit(10)->get();
        return response()->json(['invitations' => $l->map->fiche()->values()]);
    }

    public function repondre(Request $r, Invitation $invitation)
    {
        $d = $r->validate(['reponse' => 'required|in:accepter,refuser']);
        abort_unless($invitation->invite_id === $r->user()->id, 404);
        if (!$invitation->enAttente())
            return response()->json(['message' => 'Cette invitation n\'est plus valable : la partie a commencé ou a été annulée.'], 410);

        $oui = $d['reponse'] === 'accepter';
        $invitation->update(['etat' => $oui ? 'acceptee' : 'refusee']);
        Fcm::envoyer($invitation->hote, 'Gbaka Abidjan', $r->user()->pseudo . ($oui ? ' a accepté ton invitation : il arrive.' : ' a refusé ton invitation.'),
            ['type' => 'reponse', 'invitation' => $invitation->id, 'etat' => $invitation->etat]);
        return response()->json(['invitation' => $invitation->load('hote', 'invite')->fiche()]);
    }

    // l'hôte ferme sa partie : ses invitations en attente ne servent plus
    public function annuler(Request $r)
    {
        Invitation::where('hote_id', $r->user()->id)->where('etat', 'envoyee')->update(['etat' => 'annulee']);
        return response()->json(['ok' => true]);
    }
}
