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
        'eleccion_mesa_id',
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

    public function mesa()
    {
        return $this->belongsTo(EleccionMesa::class, 'eleccion_mesa_id');
    }

    public function eleccionMesa()
    {
        return $this->mesa();
    }

    public function abogado()
    {
        return $this->belongsTo(Abogado::class, 'abogado_id');
    }
}
