<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eleccion extends Model
{
    use HasFactory;

    protected $table = 'elecciones';

    protected $fillable = [
        'nombre',
        'tipo',
        'fecha',
        'estado',
        'alcance_tipo',
        'alcance_dd',
        'alcance_mm',
        'meta_testigos_pct',
        'habilitar_afluencia',
        'habilitar_datos_e14',
        'habilitar_informacion_final',
        'habilitar_foto_e14',
    ];

    protected $casts = [
        'meta_testigos_pct' => 'integer',
        'habilitar_afluencia' => 'boolean',
        'habilitar_datos_e14' => 'boolean',
        'habilitar_informacion_final' => 'boolean',
        'habilitar_foto_e14' => 'boolean',
    ];
}
