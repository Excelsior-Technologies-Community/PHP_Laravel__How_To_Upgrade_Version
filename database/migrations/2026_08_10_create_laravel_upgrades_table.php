<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laravel_upgrades', function (Blueprint $table) {
            $table->id();
            $table->string('current_version');
            $table->string('target_version');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laravel_upgrades');
    }
};
