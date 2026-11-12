<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Happiness from distinct luxury resources and natural wonders inside the borders, refreshed each turn.
        Schema::table('empires', fn (Blueprint $t) => $t->unsignedTinyInteger('luxuries')->default(0)->after('belief'));
    }

    public function down(): void
    {
        Schema::table('empires', fn (Blueprint $t) => $t->dropColumn('luxuries'));
    }
};
