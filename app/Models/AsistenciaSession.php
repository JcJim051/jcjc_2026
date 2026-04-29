<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsistenciaSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'reunion_id',
        'public_token',
        'activa',
        'abierta_en',
        'cerrada_en',
    ];

    public function reunion()
    {
        return $this->belongsTo(Reunion::class, 'reunion_id');
    }

    public function controles()
    {
        return $this->hasMany(Control::class, 'asistencia_session_id');
    }
}

