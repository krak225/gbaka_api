<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amitie extends Model
{
    protected $table = 'amities';

    protected $fillable = ['demandeur_id', 'receveur_id', 'acceptee'];

    protected function casts(): array
    {
        return ['acceptee' => 'boolean'];
    }

    public function demandeur() { return $this->belongsTo(User::class, 'demandeur_id'); }
    public function receveur() { return $this->belongsTo(User::class, 'receveur_id'); }

    public static function entre(User $a, User $b): ?self
    {
        return self::where(fn ($q) => $q->where('demandeur_id', $a->id)->where('receveur_id', $b->id))
            ->orWhere(fn ($q) => $q->where('demandeur_id', $b->id)->where('receveur_id', $a->id))
            ->first();
    }
}
