<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->json('choice')->nullable()->after('events'));
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn('choice'));
    }
};
