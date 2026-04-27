<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidato extends Model
{
    use HasFactory;

    protected $table = 'candidatos';

    protected $fillable = [
        'eleccion_id',
        'codigo',
        'nombre',
        'partido',
        'campo_e14',
        'activo',
    ];
}
