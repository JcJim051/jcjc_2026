<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferidoImportRow extends Model
{
    use HasFactory;

    protected $table = 'referido_import_rows';

    protected $fillable = [
        'batch_id',
        'row_number',
        'cedula',
        'nombre',
        'email',
        'telefono',
        'puesto_texto',
        'municipio_texto',
        'mesa_num',
        'observacion',
        'eleccion_puesto_id',
        'status',
        'error_code',
        'error_message',
        'referido_id',
    ];

    public function batch()
    {
        return $this->belongsTo(ReferidoImportBatch::class, 'batch_id');
    }
}
