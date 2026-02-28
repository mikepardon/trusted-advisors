<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize existing usernames to lowercase, strip invalid chars
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $user->name));
            if ($normalized === '') {
                $normalized = 'user' . $user->id;
            }

            // Ensure uniqueness
            $base = $normalized;
            $counter = 1;
            while (DB::table('users')->where('name', $normalized)->where('id', '!=', $user->id)->exists()) {
                $normalized = $base . $counter;
                $counter++;
            }

            if ($normalized !== $user->name) {
                DB::table('users')->where('id', $user->id)->update(['name' => $normalized]);
            }
        }
    }

    public function down(): void
    {
        // Cannot reverse username normalization
    }
};
