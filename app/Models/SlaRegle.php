<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaRegle extends Model
{
    protected $fillable = ['type_courrier', 'delai_max_heures', 'seuil_alerte_heures'];
}
