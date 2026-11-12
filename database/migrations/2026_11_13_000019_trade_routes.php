<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', fn (Blueprint $t) => $t->unsignedBigInteger('trade_to')->nullable()->after('queue'));
    }

    public function down(): void
    {
        Schema::table('cities', fn (Blueprint $t) => $t->dropColumn('trade_to'));
    }
};
