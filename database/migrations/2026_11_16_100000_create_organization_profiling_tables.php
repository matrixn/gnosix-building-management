<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'organization_id']);
        });
        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('profile_key', 60);
            $table->string('organization_kind', 40);
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamps();
        });
        Schema::create('organization_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 100);
            $table->boolean('enabled');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'capability']);
        });
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('active_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('onboarding_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->json('state');
            $table->string('current_step', 30)->default('profile');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['onboarding_sessions', 'user_preferences', 'organization_capabilities', 'organization_profiles', 'organization_memberships', 'organizations'] as $table) Schema::dropIfExists($table);
    }
};
