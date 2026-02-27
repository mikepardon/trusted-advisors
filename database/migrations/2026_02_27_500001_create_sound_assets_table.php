<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sound_assets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('category'); // 'ui' or 'actions'
            $table->string('path')->nullable();
            $table->timestamps();
        });

        $now = now();
        $sounds = [
            ['key' => 'clickNav', 'label' => 'Navigation Click', 'category' => 'ui'],
            ['key' => 'clickToggle', 'label' => 'Toggle Click', 'category' => 'ui'],
            ['key' => 'clickCard', 'label' => 'Card Click', 'category' => 'ui'],
            ['key' => 'clickButton', 'label' => 'Button Click', 'category' => 'ui'],
            ['key' => 'clickMenu', 'label' => 'Menu Click', 'category' => 'ui'],
            ['key' => 'dice', 'label' => 'Dice Roll', 'category' => 'ui'],
            ['key' => 'win', 'label' => 'Win', 'category' => 'actions'],
            ['key' => 'fail', 'label' => 'Fail', 'category' => 'actions'],
            ['key' => 'totalLoss', 'label' => 'Game Over Loss', 'category' => 'actions'],
        ];

        foreach ($sounds as $s) {
            DB::table('sound_assets')->insert([
                ...$s,
                'path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sound_assets');
    }
};
