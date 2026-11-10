<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('focus', 12)->default('balanced')->after('current_build');
        });
        Schema::table('tiles', function (Blueprint $table) {
            $table->boolean('camp')->default(false)->after('resource');
        });
        Schema::table('games', function (Blueprint $table) {
            $table->json('diplomacy')->nullable()->after('events');
        });
    }

    public function down(): void
    {
        Schema::table('cities', fn (Blueprint $t) => $t->dropColumn('focus'));
        Schema::table('tiles', fn (Blueprint $t) => $t->dropColumn('camp'));
        Schema::table('games', fn (Blueprint $t) => $t->dropColumn('diplomacy'));
    }
};
