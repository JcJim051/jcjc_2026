<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbogadoCoordinacion extends Model
{
    use HasFactory;

    protected $table = 'abogado_coordinaciones';

    protected $fillable = [
        'abogado_id',
        'eleccion_id',
        'codpuesto',
        'seller_id',
        'assigned_by',
        'assigned_at',
        'released_at',
        'validacion_estado',
        'observaciones',
        'observacion',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}
