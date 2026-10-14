<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kbntmmin_schedule', function (Blueprint $table) {
            $table->id();
            $table->string('cycle', 10)->unique();
            $table->time('jam_keluar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kbntmmin_schedule');
    }
};