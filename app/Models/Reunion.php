<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reunion extends Model
{
    use HasFactory;

    protected $table = 'reuniones';

    protected $fillable = [
        'fecha',
        'hora_inicio',
        'hora_fin',
        'lugar',
        'aforo',
        'motivo',
    ];

    public function abogados()
    {
        return $this->belongsToMany(Abogado::class, 'abogado_reunion')->withTimestamps();
    }

    public function asistenciaSession()
    {
        return $this->hasOne(AsistenciaSession::class, 'reunion_id');
    }
}
