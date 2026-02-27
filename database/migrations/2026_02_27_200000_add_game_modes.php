<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('game_mode')->default('single')->after('status');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('game_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('game_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['game_id', 'receiver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_invites');

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('game_mode');
        });
    }
};
