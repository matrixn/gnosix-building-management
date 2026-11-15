<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestamps();
        });
        Schema::create('platform_admin_passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authenticatable_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('credential_id', 64)->unique();
            $table->json('data');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value');
            $table->foreignId('updated_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('platform_admin_passkeys');
        Schema::dropIfExists('platform_admins');
    }
};
