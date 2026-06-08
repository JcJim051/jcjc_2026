<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferidoImportBatch extends Model
{
    use HasFactory;

    protected $table = 'referido_import_batches';

    protected $fillable = [
        'eleccion_id',
        'territorio_token_id',
        'filename',
        'status',
        'created_by',
        'total_rows',
        'valid_rows',
        'error_rows',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(ReferidoImportRow::class, 'batch_id');
    }
}
