<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate each card_option into its own card row
        $cards = DB::table('cards')->get();
        $newSortOrder = 1;

        foreach ($cards as $card) {
            $options = DB::table('card_options')->where('card_id', $card->id)->get();

            if ($options->isEmpty()) {
                // Card has no options, just add default effects
                DB::table('cards')->where('id', $card->id)->update([
                    'difficulty' => 'medium',
                    'requirements' => json_encode(['1-2' => ['charisma' => 2], '3-4' => ['charisma' => 3], '5-6' => ['charisma' => 4]]),
                    'success_effects' => json_encode(['happiness' => 2]),
                    'failure_effects' => json_encode(['happiness' => -2]),
                    'sort_order' => $newSortOrder++,
                ]);
                continue;
            }

            // First option updates the existing card
            $firstOption = $options->first();
            DB::table('cards')->where('id', $card->id)->update([
                'description' => $card->description . "\n\n" . $firstOption->text,
                'difficulty' => $firstOption->difficulty,
                'requirements' => $firstOption->requirements,
                'success_effects' => $firstOption->success_effects,
                'failure_effects' => $firstOption->failure_effects,
                'sort_order' => $newSortOrder++,
            ]);

            // Remaining options become new cards
            foreach ($options->skip(1) as $option) {
                DB::table('cards')->insert([
                    'title' => $card->title . ' (Alt)',
                    'description' => $card->description . "\n\n" . $option->text,
                    'difficulty' => $option->difficulty,
                    'requirements' => $option->requirements,
                    'success_effects' => $option->success_effects,
                    'failure_effects' => $option->failure_effects,
                    'sort_order' => $newSortOrder++,
                    'category' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Drop card_options table
        Schema::dropIfExists('card_options');
    }

    public function down(): void
    {
        // Recreate card_options table
        Schema::create('card_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('difficulty');
            $table->json('requirements');
            $table->json('success_effects');
            $table->json('failure_effects');
            $table->timestamps();
        });
    }
};
