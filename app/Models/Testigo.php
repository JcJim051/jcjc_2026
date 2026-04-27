<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testigo extends Model
{
    use HasFactory;

    protected $fillable = [
        'persona_id',
        'tipo',
        'nivel',
        'activo',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}
