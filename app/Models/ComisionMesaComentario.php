<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComisionMesaComentario extends Model
{
    use HasFactory;

    protected $table = 'comision_mesa_comentarios';

    protected $fillable = [
        'eleccion_id',
        'eleccion_mesa_id',
        'territorio_token_id',
        'autor_nombre',
        'comentario',
    ];
}
