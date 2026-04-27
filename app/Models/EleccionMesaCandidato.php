<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EleccionMesaCandidato extends Model
{
    use HasFactory;

    protected $table = 'eleccion_mesa_candidatos';

    protected $fillable = [
        'eleccion_id',
        'eleccion_mesa_id',
        'candidato_id',
    ];
}

