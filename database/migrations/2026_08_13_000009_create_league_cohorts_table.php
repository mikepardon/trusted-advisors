<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_cohorts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('tier');
            $table->date('week_start');
            $table->timestamps();

            $table->index(['tier', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_cohorts');
    }
};
