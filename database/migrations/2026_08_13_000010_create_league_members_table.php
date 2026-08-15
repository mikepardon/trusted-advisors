<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cohort_id')->constrained('league_cohorts')->cascadeOnDelete();
            // Null user_id marks a bot filler; real players carry a user reference.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('bot_name')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();

            // A real player appears at most once per cohort; bots (null user_id)
            // are distinct under Postgres' default null handling.
            $table->unique(['cohort_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_members');
    }
};
