<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Control extends Model
{
    use HasFactory;

    protected $table = 'controls';

    protected $fillable = [
        'codigo_abogado',
        'asistencia_session_id',
        'fecha',
        'lugar',
        'motivo',
    ];

    public function session()
    {
        return $this->belongsTo(AsistenciaSession::class, 'asistencia_session_id');
    }
}

