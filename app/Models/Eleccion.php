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
    ];
}
