<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empires', function (Blueprint $t) {
            $t->json('great_progress')->nullable()->after('golden_points');
            $t->json('great_people')->nullable()->after('great_progress');
            $t->unsignedSmallInteger('great_count')->default(0)->after('great_people');
        });
    }

    public function down(): void
    {
        Schema::table('empires', fn (Blueprint $t) => $t->dropColumn(['great_progress', 'great_people', 'great_count']));
    }
};
