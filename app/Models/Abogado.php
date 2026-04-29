<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Abogado extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'cc', 'correo', 'direccion', 'telefono', 'comuna', 'puesto', 'mesa',
        'estudios', 'titulo', 'experiencia', 'electoral', 'rol', 'face', 'insta',
        'fecha_inicio', 'funcionario', 'rol_actual', 'alcaldia', 'lugar', 'entidad',
        'secretaria', 'honorarios', 'disponibilidad', 'observacion', 'foto', 'pdf_cc',
        'activo',
    ];

    public function reuniones()
    {
        return $this->belongsToMany(Reunion::class, 'abogado_reunion')
            ->withTimestamps()
            ->orderByDesc('fecha');
    }
}
