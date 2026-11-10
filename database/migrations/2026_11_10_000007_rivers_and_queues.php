<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiles', fn (Blueprint $t) => $t->boolean('river')->default(false)->after('camp'));
        Schema::table('cities', fn (Blueprint $t) => $t->json('queue')->nullable()->after('focus'));
    }

    public function down(): void
    {
        Schema::table('tiles', fn (Blueprint $t) => $t->dropColumn('river'));
        Schema::table('cities', fn (Blueprint $t) => $t->dropColumn('queue'));
    }
};
