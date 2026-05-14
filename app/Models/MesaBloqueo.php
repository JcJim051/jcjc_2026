<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MesaBloqueo extends Model
{
    use HasFactory;

    protected $table = 'mesa_bloqueos';

    protected $fillable = [
        'eleccion_id',
        'eleccion_puesto_id',
        'mesa_num',
        'origen',
        'lote',
        'fila_origen',
        'observacion',
        'created_by',
    ];
}
