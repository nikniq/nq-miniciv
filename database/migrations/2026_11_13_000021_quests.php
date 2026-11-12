<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Favours rivals have asked of the player: [{empire, kind, target, amount, progress, until}]
        Schema::table('games', fn (Blueprint $t) => $t->json('quests')->nullable()->after('raids'));
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn('quests'));
    }
};
