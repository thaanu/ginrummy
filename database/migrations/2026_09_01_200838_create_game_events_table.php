<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->json('payload');
            $table->unsignedInteger('sequence_number');
            $table->timestamp('created_at')->nullable();

            $table->unique(['game_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
