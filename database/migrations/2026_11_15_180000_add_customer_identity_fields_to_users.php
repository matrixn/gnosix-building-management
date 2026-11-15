<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('ro');
            $table->string('profile_type', 30)->nullable();
            $table->timestamp('first_signed_in_at')->nullable();
            $table->timestamp('last_signed_in_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->unsignedInteger('auth_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['locale', 'profile_type', 'first_signed_in_at', 'last_signed_in_at', 'onboarding_completed_at', 'auth_version']));
    }
};
