<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addressinoac', function (Blueprint $table) {
            $table->id();
            $table->string('part_no')->unique();
            $table->string('part_name')->nullable();
            $table->string('rack_no')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addressinoac');
    }
};
