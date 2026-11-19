<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            // Set when the game was the shared daily deal for that (UTC) date.
            $table->date('daily')->nullable()->after('layout');
            $table->index(['daily', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropIndex(['daily', 'score']);
            $table->dropColumn('daily');
        });
    }
};
