<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empires', function (Blueprint $table) {
            $table->string('government', 20)->default('despotism')->after('golden_points');
            $table->unsignedInteger('anarchy_until')->default(0)->after('government');
            $table->unsignedInteger('captures')->default(0)->after('anarchy_until');
        });
        Schema::table('users', fn (Blueprint $t) => $t->json('achievements')->nullable()->after('is_guest'));
    }

    public function down(): void
    {
        Schema::table('empires', fn (Blueprint $t) => $t->dropColumn(['government', 'anarchy_until', 'captures']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('achievements'));
    }
};
