<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('layout', 20);
            $table->unsignedInteger('score');
            $table->unsignedInteger('duration'); // seconds
            $table->unsignedSmallInteger('hints')->default(0);
            $table->unsignedSmallInteger('shuffles')->default(0);
            $table->unsignedSmallInteger('undos')->default(0);
            $table->timestamps();

            $table->index(['layout', 'score']);
            $table->index(['user_id', 'layout']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
