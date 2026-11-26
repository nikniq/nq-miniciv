<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empires', function (Blueprint $table) {
            $table->json('policies')->nullable();
            $table->unsignedInteger('policy_points')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('empires', function (Blueprint $table) {
            $table->dropColumn(['policies', 'policy_points']);
        });
    }
};
