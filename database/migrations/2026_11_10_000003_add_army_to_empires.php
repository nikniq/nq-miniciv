<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empires', function (Blueprint $table) {
            $table->unsignedInteger('army')->default(0)->after('settlers');
            $table->boolean('eliminated')->default(false)->after('is_ai');
        });
    }

    public function down(): void
    {
        Schema::table('empires', function (Blueprint $table) {
            $table->dropColumn(['army', 'eliminated']);
        });
    }
};
