<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referido extends Model
{
    use HasFactory;

    protected $table = 'referidos';

    protected $fillable = [
        'persona_id',
        'eleccion_id',
        'eleccion_puesto_id',
        'territorio_token_id',
        'mesa_num',
        'cedula_pdf_path',
        'estado',
        'observaciones',
    ];
}
