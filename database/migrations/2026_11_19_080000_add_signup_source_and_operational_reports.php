<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->json('registration_source')->nullable());
        Schema::create('operational_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->json('filters');
            $table->string('locale', 2);
            $table->string('scope_hash', 64);
            $table->string('state', 16)->default('queued');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'organization_id', 'created_at']);
        });
        Schema::create('operations_preferences', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('layout', 16)->default('overview');
            $table->boolean('weekly_digest')->default(false);
            $table->date('last_digest_week')->nullable();
            $table->primary(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_preferences');
        Schema::dropIfExists('operational_reports');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('registration_source'));
    }
};
