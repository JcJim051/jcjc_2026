<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    protected $fillable = [
        'cedula',
        'nombre',
        'nombre_original',
        'nombre_normalizado',
        'email',
        'telefono',
    ];
}
