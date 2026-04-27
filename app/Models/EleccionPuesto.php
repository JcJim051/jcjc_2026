<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EleccionPuesto extends Model
{
    use HasFactory;

    protected $table = 'eleccion_puestos';

    protected $fillable = [
        'eleccion_id',
        'dd',
        'mm',
        'zz',
        'pp',
        'codigo_puesto',
        'departamento',
        'municipio',
        'puesto',
        'comuna',
        'direccion',
        'mesas_total',
    ];
}
