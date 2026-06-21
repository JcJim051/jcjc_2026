<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class MesaReporteAudit extends Model { use HasFactory; protected $table = 'mesa_reporte_audits'; protected $fillable = ['mesa_reporte_id','abogado_id','accion','before_payload','after_payload']; protected $casts = ['before_payload' => 'array','after_payload' => 'array']; }
