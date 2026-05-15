<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EleccionPuestoMeta extends Model
{
    protected $table = 'eleccion_puesto_metas';

    protected $fillable = [
        'eleccion_id',
        'eleccion_puesto_id',
        'meta_pactada',
        'origen',
        'lote',
    ];
}

