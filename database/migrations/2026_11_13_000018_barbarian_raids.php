<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // War bands on their way: [{camp, city, strength, arrives}]
        Schema::table('games', fn (Blueprint $t) => $t->json('raids')->nullable()->after('choice'));
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn('raids'));
    }
};
