<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CompteController extends Controller
{
    const PSEUDO = ['required', 'string', 'min:2', 'max:16', 'regex:/^[\pL\pN _.\-]+$/u'];

    public function inscription(Request $r)
    {
        $d = $r->validate([
            'pseudo' => [...self::PSEUDO, fn ($a, $v, $echec) => User::parPseudo($v) ? $echec('Ce pseudo est déjà pris.') : null],
            'mot_de_passe' => ['required', 'string', 'min:6', 'max:100'],
        ], [
            'pseudo.regex' => 'Lettres, chiffres, espaces, points et tirets seulement.',
            'pseudo.min' => 'Le pseudo doit avoir au moins 2 caractères.',
            'pseudo.max' => 'Le pseudo doit avoir au plus 16 caractères.',
            'mot_de_passe.min' => 'Le mot de passe doit avoir au moins 6 caractères.',
        ]);
        $u = User::create(['pseudo' => trim($d['pseudo']), 'password' => $d['mot_de_passe'], 'vu_le' => now()]);
        return response()->json(['jeton' => $u->createToken('jeu')->plainTextToken, 'joueur' => $u->fiche()], 201);
    }

    public function connexion(Request $r)
    {
        $d = $r->validate(['pseudo' => 'required|string', 'mot_de_passe' => 'required|string']);
        $u = User::parPseudo($d['pseudo']);
        if (!$u || !Hash::check($d['mot_de_passe'], $u->password))
            return response()->json(['message' => 'Pseudo ou mot de passe incorrect.'], 401);
        $u->forceFill(['vu_le' => now()])->save();
        return response()->json(['jeton' => $u->createToken('jeu')->plainTextToken, 'joueur' => $u->fiche()]);
    }

    public function deconnexion(Request $r)
    {
        $u = $r->user();
        $u->currentAccessToken()->delete();
        $u->forceFill(['fcm_token' => null])->save();   // ce téléphone ne reçoit plus les invitations de ce compte
        return response()->json(['ok' => true]);
    }

    // Le jeu appelle « moi » au démarrage puis régulièrement : il tient le joueur « en ligne »
    // et ramène d'un coup les invitations et demandes d'amis en attente.
    public function moi(Request $r)
    {
        $u = $r->user();
        $u->forceFill(['vu_le' => now()])->save();
        return response()->json([
            'joueur' => $u->fiche(),
            'invitations' => app(InvitationController::class)->recues($r)->getData(true)['invitations'],
            'demandes' => \App\Models\Amitie::where('receveur_id', $u->id)->where('acceptee', false)->count(),
        ]);
    }

    // jeton Firebase du téléphone (il change de temps en temps : le jeu le renvoie à chaque démarrage)
    public function appareil(Request $r)
    {
        $d = $r->validate(['fcm_token' => 'nullable|string|max:400']);
        if (!empty($d['fcm_token']))   // un téléphone, un compte : l'ancien compte connecté sur ce téléphone ne le reçoit plus
            User::where('fcm_token', $d['fcm_token'])->where('id', '!=', $r->user()->id)->update(['fcm_token' => null]);
        $r->user()->forceFill(['fcm_token' => $d['fcm_token'] ?? null])->save();
        return response()->json(['ok' => true]);
    }

    public function supprimer(Request $r)
    {
        $r->validate(['mot_de_passe' => 'required|string']);
        $u = $r->user();
        if (!Hash::check($r->input('mot_de_passe'), $u->password))
            return response()->json(['message' => 'Mot de passe incorrect.'], 403);
        $u->tokens()->delete();
        $u->delete();   // amitiés et invitations partent avec (cascade)
        return response()->json(['ok' => true]);
    }
}
