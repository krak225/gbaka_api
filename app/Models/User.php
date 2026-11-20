<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

// Joueur de Gbaka Abidjan : un pseudo et un mot de passe, rien d'autre de personnel.
class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['pseudo', 'password', 'fcm_token', 'vu_le'];

    protected $hidden = ['password', 'fcm_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'vu_le' => 'datetime',
        ];
    }

    // les pseudos ne distinguent pas majuscules et minuscules (« Kouadio » = « kouadio »)
    public static function parPseudo(?string $pseudo): ?self
    {
        return self::whereRaw('LOWER(pseudo) = ?', [mb_strtolower(trim($pseudo ?? ''))])->first();
    }

    public function enLigne(): bool
    {
        return $this->vu_le !== null && $this->vu_le->gt(now()->subMinutes(2));
    }

    // ce que les autres joueurs voient
    public function fiche(): array
    {
        return ['id' => $this->id, 'pseudo' => $this->pseudo, 'en_ligne' => $this->enLigne()];
    }

    // amis acceptés, dans les deux sens
    public function amis()
    {
        $ids = Amitie::where('acceptee', true)
            ->where(fn ($q) => $q->where('demandeur_id', $this->id)->orWhere('receveur_id', $this->id))
            ->get()
            ->map(fn ($a) => $a->demandeur_id === $this->id ? $a->receveur_id : $a->demandeur_id);

        return User::whereIn('id', $ids)->orderBy('pseudo')->get();
    }

    public function estAmiDe(User $autre): bool
    {
        return Amitie::entre($this, $autre)?->acceptee ?? false;
    }
}
