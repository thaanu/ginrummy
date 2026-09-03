<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('nickname', 20);
            $table->unsignedTinyInteger('seat_number');
            $table->string('session_token_hash', 64)->unique();
            $table->boolean('is_host')->default(false);
            // The player's private hand, as card codes.
            $table->json('hand')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
