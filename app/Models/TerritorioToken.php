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
    ];

    protected $fillable = [
        'eleccion_id',
        'dd',
        'mm',
        'comuna',
        'token',
        'responsable',
        'activo',
        'expires_at',
    ];
}
