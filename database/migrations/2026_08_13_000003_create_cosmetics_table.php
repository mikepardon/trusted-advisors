<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosmetics', function (Blueprint $table): void {
            $table->id();
            // title | frame | card_back | victory_fx | crest_shape | crest_pattern | crest_charge
            $table->string('type', 32);
            $table->string('slug')->unique();
            $table->string('name');
            // common | rare | epic | legendary
            $table->string('rarity', 16)->default('common');
            // The payload rendered for this cosmetic: title text, frame/fx identifier, colour, etc.
            $table->text('value')->nullable();
            $table->json('meta')->nullable();
            // Default cosmetics are owned by everyone without a user_cosmetics row.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmetics');
    }
};
