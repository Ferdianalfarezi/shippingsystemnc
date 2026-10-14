<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kbntmmin_print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('cycle', 10);
            $table->string('manifest_no', 50);
            $table->timestamp('printed_at')->nullable();
            $table->string('printed_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['manifest_no', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kbntmmin_print_logs');
    }
};