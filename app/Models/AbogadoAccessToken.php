<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbogadoAccessToken extends Model
{
    use HasFactory;

    public const TYPE_CHARACTERIZATION = 'characterization';
    public const TYPE_UPDATE = 'update';

    protected $fillable = [
        'type',
        'token',
        'label',
        'active',
        'expires_at',
        'used_at',
        'created_by',
        'completed_count',
    ];

    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function isAvailable(): bool
    {
        return $this->active && !$this->expires_at->isPast();
    }

    public function publicUrl(): string
    {
        $route = $this->type === self::TYPE_UPDATE
            ? 'public.abogados.update.identify'
            : 'public.abogados.characterization.form';

        return route($route, $this->token);
    }
}
