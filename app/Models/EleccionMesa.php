<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EleccionMesa extends Model
{
    use HasFactory;

    protected $table = 'eleccion_mesas';

    protected $fillable = [
        'eleccion_id',
        'eleccion_puesto_id',
        'mesa_num',
        'mesa_key',
    ];
}
