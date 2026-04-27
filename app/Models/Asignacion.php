<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignacion extends Model
{
    use HasFactory;

    protected $table = 'asignaciones';

    protected $fillable = [
        'testigo_id',
        'eleccion_id',
        'eleccion_mesa_id',
        'rol',
        'estado',
    ];

    public function testigo()
    {
        return $this->belongsTo(Testigo::class);
    }

    public function eleccion()
    {
        return $this->belongsTo(Eleccion::class);
    }

    public function eleccionMesa()
    {
        return $this->belongsTo(EleccionMesa::class, 'eleccion_mesa_id');
    }
}
