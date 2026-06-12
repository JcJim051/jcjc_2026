<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbogadoContactUpdate extends Model
{
    use HasFactory;

    protected $table = 'abogado_phone_updates';

    protected $fillable = [
        'abogado_id',
        'reunion_id',
        'asistencia_session_id',
        'old_phone',
        'new_phone',
        'old_email',
        'new_email',
        'source',
    ];

    public function abogado()
    {
        return $this->belongsTo(Abogado::class);
    }

    public function reunion()
    {
        return $this->belongsTo(Reunion::class);
    }

    public function session()
    {
        return $this->belongsTo(AsistenciaSession::class, 'asistencia_session_id');
    }
}
