<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('difficulty')->default('medium')->after('description');
            $table->json('requirements')->nullable()->after('difficulty');
            $table->json('success_effects')->nullable()->after('requirements');
            $table->json('failure_effects')->nullable()->after('success_effects');
            $table->string('category')->nullable()->after('failure_effects');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'requirements', 'success_effects', 'failure_effects', 'category']);
        });
    }
};
