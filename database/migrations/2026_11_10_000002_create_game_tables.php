<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('seed');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('turn')->default(1);
            $table->unsignedInteger('max_turns')->default(120);
            $table->string('status', 20)->default('active'); // active|won|lost|ended
            $table->unsignedBigInteger('winner_empire_id')->nullable();
            $table->json('events')->nullable(); // what happened last turn, for the player
            $table->timestamps();
        });

        Schema::create('empires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color', 7);
            $table->boolean('is_ai')->default(false);
            $table->integer('gold')->default(0);
            $table->unsignedInteger('settlers')->default(0);
            $table->json('technologies');
            $table->string('current_tech')->nullable();
            $table->unsignedInteger('research_progress')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empire_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tile_id');
            $table->string('name');
            $table->unsignedInteger('population')->default(1);
            $table->integer('food_stored')->default(0);
            $table->unsignedInteger('production_stored')->default(0);
            $table->string('current_build')->nullable();
            $table->json('buildings');
            $table->unsignedInteger('culture')->default(0);
            $table->boolean('is_capital')->default(false);
            $table->timestamps();
        });

        Schema::create('tiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('x');
            $table->unsignedSmallInteger('y');
            $table->string('terrain', 20);
            $table->string('resource', 20)->nullable();
            $table->foreignId('empire_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['game_id', 'x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiles');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('empires');
        Schema::dropIfExists('games');
    }
};
