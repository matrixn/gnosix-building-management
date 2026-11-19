<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_handovers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('recipient_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->json('selection')->nullable();
            $table->json('snapshot')->nullable();
            $table->text('authority_basis')->nullable();
            $table->unsignedSmallInteger('access_days')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['building_id', 'created_at']);
        });
        Schema::create('handover_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('handover_id')->constrained('building_handovers')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('decision', 16)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['handover_id', 'email']);
            $table->index(['user_id', 'handover_id']);
        });
        Schema::create('building_stewardships', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('recipient_id')->unique()->constrained('handover_recipients')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 24);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('revoked_at')->nullable();
        });
        Schema::create('handover_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('handover_id')->constrained('building_handovers')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 24);
            $table->uuid('recipient_reference')->nullable();
            $table->unsignedBigInteger('reply_to')->nullable()->index();
            $table->text('message')->nullable();
            $table->timestamp('created_at');
            $table->index(['handover_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_events');
        Schema::dropIfExists('building_stewardships');
        Schema::dropIfExists('handover_recipients');
        Schema::dropIfExists('building_handovers');
    }
};
