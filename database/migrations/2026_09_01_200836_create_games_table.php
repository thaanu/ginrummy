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
            $table->string('code', 8)->unique();
            $table->string('status', 16)->index();
            $table->string('turn_phase', 16)->nullable();

            $table->unsignedBigInteger('host_player_id')->nullable();
            $table->unsignedBigInteger('current_player_id')->nullable();
            $table->unsignedBigInteger('winner_player_id')->nullable();

            // Undealt cards as codes, top of the pile last.
            $table->json('stock')->nullable();
            // Discard pile as codes, top card last.
            $table->json('discard')->nullable();
            // Player ids in turn order.
            $table->json('player_order')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
