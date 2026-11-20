<?php

namespace App\Http\Controllers;

use App\Models\Amitie;
use App\Models\User;
use App\Services\Fcm;
use Illuminate\Http\Request;

class AmiController extends Controller
{
    // amis acceptés + demandes reçues + demandes envoyées
    public function liste(Request $r)
    {
        $u = $r->user();
        $recues = Amitie::with('demandeur')->where('receveur_id', $u->id)->where('acceptee', false)->get();
        $envoyees = Amitie::with('receveur')->where('demandeur_id', $u->id)->where('acceptee', false)->get();
        return response()->json([
            'amis' => $u->amis()->map->fiche()->values(),
            'recues' => $recues->map(fn ($a) => ['id' => $a->id, 'pseudo' => $a->demandeur->pseudo])->values(),
            'envoyees' => $envoyees->map(fn ($a) => ['id' => $a->id, 'pseudo' => $a->receveur->pseudo])->values(),
        ]);
    }

    // demande d'ami par pseudo exact ; si l'autre m'avait déjà demandé, l'amitié est acceptée tout de suite
    public function demander(Request $r)
    {
        $r->validate(['pseudo' => 'required|string|max:20']);
        $u = $r->user();
        $autre = User::parPseudo($r->input('pseudo'));
        if (!$autre) return response()->json(['message' => 'Aucun joueur ne porte ce pseudo.'], 404);
        if ($autre->id === $u->id) return response()->json(['message' => 'C\'est ton propre pseudo.'], 422);

        $a = Amitie::entre($u, $autre);
        if ($a && $a->acceptee) return response()->json(['message' => $autre->pseudo . ' est déjà ton ami.', 'etat' => 'ami']);
        if ($a && $a->receveur_id === $u->id) {
            $a->update(['acceptee' => true]);
            Fcm::envoyer($autre, 'Gbaka Abidjan', $u->pseudo . ' a accepté ta demande d\'ami.', ['type' => 'ami']);
            return response()->json(['message' => 'Vous êtes amis.', 'etat' => 'ami']);
        }
        if ($a) return response()->json(['message' => 'Demande déjà envoyée à ' . $autre->pseudo . '.', 'etat' => 'envoyee']);

        if (Amitie::where('demandeur_id', $u->id)->where('acceptee', false)->count() >= 50)
            return response()->json(['message' => 'Trop de demandes en attente.'], 429);
        Amitie::create(['demandeur_id' => $u->id, 'receveur_id' => $autre->id]);
        Fcm::envoyer($autre, 'Gbaka Abidjan', $u->pseudo . ' veut être ton ami.', ['type' => 'ami']);
        return response()->json(['message' => 'Demande envoyée à ' . $autre->pseudo . '.', 'etat' => 'envoyee'], 201);
    }

    public function accepter(Request $r, Amitie $amitie)
    {
        abort_unless($amitie->receveur_id === $r->user()->id, 404);
        $amitie->update(['acceptee' => true]);
        Fcm::envoyer($amitie->demandeur, 'Gbaka Abidjan', $r->user()->pseudo . ' a accepté ta demande d\'ami.', ['type' => 'ami']);
        return response()->json(['ok' => true]);
    }

    // refuser une demande, l'annuler, ou retirer un ami (par pseudo)
    public function retirer(Request $r, string $pseudo)
    {
        $autre = User::parPseudo($pseudo);
        abort_unless($autre, 404);
        Amitie::entre($r->user(), $autre)?->delete();
        return response()->json(['ok' => true]);
    }
}
