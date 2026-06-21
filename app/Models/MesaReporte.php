<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class MesaReporte extends Model { use HasFactory; protected $table = 'mesa_reportes'; protected $fillable = ['eleccion_id','territorio_token_id','eleccion_puesto_id','eleccion_mesa_id','coordinacion_id','abogado_id','afluencia_9','afluencia_11','afluencia_14','testigos_presentes','censo_mesa','votos_en_urna','votos_incinerados','votos_blanco','votos_nulos','votos_no_marcados','e14_detalle','e14_reportado_at','e14_foto_path','reconteo','reclamacion','jurados_firmaron','reclamacion_origen','reclamacion_candidato_id','reclamacion_comentario','reclamacion_foto_path','control_final_at','created_by_abogado_id','updated_by_abogado_id']; protected $casts = ['e14_detalle' => 'array','e14_reportado_at' => 'datetime','control_final_at' => 'datetime','reconteo' => 'boolean','reclamacion' => 'boolean']; }
