<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One character per tile, '1' once seen. Null means the whole map is known (AI, older games).
        Schema::table('empires', fn (Blueprint $t) => $t->text('explored')->nullable()->after('great_count'));
    }

    public function down(): void
    {
        Schema::table('empires', fn (Blueprint $t) => $t->dropColumn('explored'));
    }
};
