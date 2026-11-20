<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    // une partie en ligne n'attend pas longtemps : au-delà, l'hôte est sans doute reparti seul
    public const DUREE_MINUTES = 10;

    protected $fillable = ['hote_id', 'invite_id', 'mode', 'code', 'libelle', 'etat', 'expire_le'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime'];
    }

    public function hote() { return $this->belongsTo(User::class, 'hote_id'); }
    public function invite() { return $this->belongsTo(User::class, 'invite_id'); }

    public function enAttente(): bool
    {
        return $this->etat === 'envoyee' && $this->expire_le->isFuture();
    }

    public function fiche(): array
    {
        return [
            'id' => $this->id,
            'hote' => $this->hote->pseudo,
            'invite' => $this->invite->pseudo,
            'mode' => $this->mode,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'etat' => $this->enAttente() || $this->etat !== 'envoyee' ? $this->etat : 'expiree',
            'expire_dans' => (int) max(0, now()->diffInSeconds($this->expire_le, false)),
        ];
    }
}
