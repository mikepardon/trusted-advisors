<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_gifts', function (Blueprint $table) {
            $table->string('target_type', 30)->default('all')->after('recipient_count');
            $table->json('target_user_ids')->nullable()->after('target_type');
            $table->json('target_criteria')->nullable()->after('target_user_ids');
            $table->string('status', 20)->default('sent')->after('target_criteria');
            $table->timestamp('scheduled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('admin_gifts', function (Blueprint $table) {
            $table->dropColumn(['target_type', 'target_user_ids', 'target_criteria', 'status', 'scheduled_at']);
        });
    }
};
