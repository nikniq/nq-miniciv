<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->json('log')->nullable()->after('history'));
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn('log'));
    }
};
