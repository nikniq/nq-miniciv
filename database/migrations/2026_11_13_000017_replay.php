<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Territory frames for the end-of-game replay: one character per tile per recorded turn.
        Schema::table('games', fn (Blueprint $t) => $t->longText('replay')->nullable()->after('log'));
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn('replay'));
    }
};
