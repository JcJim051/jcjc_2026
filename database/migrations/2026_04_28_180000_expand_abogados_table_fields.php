<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('abogados', function (Blueprint $table) {
            $table->string('comuna')->nullable()->after('direccion');
            $table->string('puesto')->nullable()->after('comuna');
            $table->string('mesa')->nullable()->after('puesto');
            $table->string('estudios')->nullable()->after('mesa');
            $table->string('titulo')->nullable()->after('estudios');
            $table->string('experiencia')->nullable()->after('titulo');
            $table->string('electoral')->nullable()->after('experiencia');
            $table->string('rol')->nullable()->after('electoral');
            $table->string('face')->nullable()->after('rol');
            $table->string('insta')->nullable()->after('face');
            $table->date('fecha_inicio')->nullable()->after('insta');
            $table->string('funcionario')->nullable()->after('fecha_inicio');
            $table->string('rol_actual')->nullable()->after('funcionario');
            $table->string('alcaldia')->nullable()->after('rol_actual');
            $table->string('lugar')->nullable()->after('alcaldia');
            $table->string('entidad')->nullable()->after('lugar');
            $table->string('secretaria')->nullable()->after('entidad');
            $table->string('honorarios')->nullable()->after('secretaria');
            $table->string('disponibilidad')->nullable()->after('honorarios');
            $table->string('observacion')->nullable()->after('disponibilidad');
            $table->string('foto')->nullable()->after('observacion');
            $table->string('pdf_cc')->nullable()->after('foto');
        });
    }

    public function down()
    {
        Schema::table('abogados', function (Blueprint $table) {
            $table->dropColumn([
                'comuna','puesto','mesa','estudios','titulo','experiencia','electoral',
                'rol','face','insta','fecha_inicio','funcionario','rol_actual','alcaldia',
                'lugar','entidad','secretaria','honorarios','disponibilidad','observacion',
                'foto','pdf_cc'
            ]);
        });
    }
};

