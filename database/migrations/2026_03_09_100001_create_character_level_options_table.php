<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_level_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // bump_dice_face, bump_two_dice_faces, start_with_item, extra_item_slot, passive_stat_bonus, add_wild, card_redraw
            $table->json('config')->nullable();
            $table->integer('available_at_level'); // 1-7
            $table->foreignId('character_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('max_selections')->default(0); // 0 = unlimited per incarnation
            $table->integer('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_level_options');
    }
};
