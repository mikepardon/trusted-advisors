<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotating_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->string('game_type')->default('cooperative'); // cooperative, duel
            $table->string('game_mode')->default('single'); // single, pass_and_play, online
            $table->json('modifiers')->nullable(); // e.g. {"starting_stats":5,"xp_multiplier":2}
            $table->integer('reward_coins')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rotating_event_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotating_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->integer('score');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['rotating_event_id', 'user_id', 'game_id']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('rotating_event_id')->nullable()->constrained()->nullOnDelete()->after('season_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['rotating_event_id']);
            $table->dropColumn('rotating_event_id');
        });

        Schema::dropIfExists('rotating_event_entries');
        Schema::dropIfExists('rotating_events');
    }
};
