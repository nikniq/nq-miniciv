<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('difficulty', 12)->default('prince')->after('max_turns');
            $table->json('history')->nullable()->after('diplomacy'); // per-turn scores by empire
        });
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn(['difficulty', 'history']));
    }
};
