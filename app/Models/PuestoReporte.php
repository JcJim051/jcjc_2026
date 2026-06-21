<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuestoReporte extends Model
{
    use HasFactory;

    protected $table = 'puesto_reportes';

    protected $fillable = [
        'eleccion_id',
        'eleccion_puesto_id',
        'territorio_token_id',
        'abogado_id',
        'testigos_presentes',
        'created_by_abogado_id',
        'updated_by_abogado_id',
    ];
}
