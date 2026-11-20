<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Envoi des notifications par Firebase Cloud Messaging (API HTTP v1).
// Le compte de service (clé privée JSON téléchargée depuis la console Firebase) reste sur le serveur :
// chemin dans FCM_COMPTE_SERVICE (.env), par défaut storage/app/private/firebase.json. Ne jamais le mettre dans le jeu.
class Fcm
{
    public static function compte(): ?array
    {
        $p = env('FCM_COMPTE_SERVICE', storage_path('app/private/firebase.json'));
        if (!is_file($p)) return null;
        $j = json_decode(file_get_contents($p), true);
        return isset($j['private_key'], $j['client_email'], $j['project_id']) ? $j : null;
    }

    // jeton d'accès OAuth 2 (une heure), obtenu en signant un JWT avec la clé du compte de service
    static function jetonAcces(array $c): ?string
    {
        return Cache::remember('fcm_jeton', 3000, function () use ($c) {
            $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $t = time();
            $tete = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $corps = $b64(json_encode([
                'iss' => $c['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $t,
                'exp' => $t + 3600,
            ]));
            openssl_sign("$tete.$corps", $sig, $c['private_key'], OPENSSL_ALGO_SHA256);
            $r = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "$tete.$corps." . $b64($sig),
            ]);
            return $r->successful() ? $r->json('access_token') : null;
        });
    }

    // Notification visible (titre, texte) + données lues par le jeu quand le joueur la touche.
    // Renvoie false si le joueur n'a pas de téléphone enregistré ou si Firebase n'est pas configuré.
    public static function envoyer(User $u, string $titre, string $texte, array $donnees = []): bool
    {
        if (!$u->fcm_token) return false;
        $c = self::compte();
        if (!$c) { Log::info('FCM non configuré : notification non envoyée à ' . $u->pseudo); return false; }
        $jeton = self::jetonAcces($c);
        if (!$jeton) { Cache::forget('fcm_jeton'); Log::warning('FCM : jeton d\'accès refusé'); return false; }

        $r = Http::withToken($jeton)->post("https://fcm.googleapis.com/v1/projects/{$c['project_id']}/messages:send", [
            'message' => [
                'token' => $u->fcm_token,
                'notification' => ['title' => $titre, 'body' => $texte],
                'data' => array_map('strval', $donnees),
                'android' => [
                    'priority' => 'high',
                    'ttl' => (\App\Models\Invitation::DUREE_MINUTES * 60) . 's',
                    'notification' => ['channel_id' => 'invitations', 'sound' => 'default', 'tag' => $donnees['type'] ?? 'gbaka'],
                ],
            ],
        ]);
        if ($r->status() === 404 || str_contains($r->body(), 'UNREGISTERED')) {
            $u->forceFill(['fcm_token' => null])->save();   // application désinstallée : on oublie ce téléphone
            return false;
        }
        if (!$r->successful()) { Log::warning('FCM : ' . $r->status() . ' ' . $r->body()); return false; }
        return true;
    }
}
