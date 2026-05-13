<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerritorioToken extends Model
{
    use HasFactory;

    protected $table = 'territorio_tokens';

    protected $casts = [
        'activo' => 'boolean',
        'expires_at' => 'datetime',
        'es_consulta' => 'boolean',
        'municipios' => 'array',
    ];

    protected $fillable = [
        'eleccion_id',
        'dd',
        'mm',
        'comuna',
        'es_consulta',
        'municipios',
        'token',
        'responsable',
        'activo',
        'expires_at',
    ];
}
