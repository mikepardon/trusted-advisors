<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('auth_id')->nullable()->unique()->after('id');
            $table->string('avatar_url')->nullable()->after('email_verified_at');
            $table->string('password')->nullable()->change();
            $table->string('refresh_token', 1024)->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_id', 'avatar_url', 'refresh_token']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
