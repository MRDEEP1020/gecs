<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourrierHistorique extends Model
{
    // Append-only (Règle n°5) : pas d'updated_at, et le modèle n'expose aucune voie de mise à jour.
    const UPDATED_AT = null;

    protected $fillable = ['courrier_id', 'auteur_id', 'action', 'commentaire'];

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
