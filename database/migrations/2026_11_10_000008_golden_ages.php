<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empires', function (Blueprint $table) {
            $table->unsignedInteger('golden_age_until')->default(0)->after('army');
            $table->unsignedInteger('golden_points')->default(0)->after('golden_age_until');
        });
    }

    public function down(): void
    {
        Schema::table('empires', fn (Blueprint $t) => $t->dropColumn(['golden_age_until', 'golden_points']));
    }
};
