<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_identities', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('target_type', 15);
            $t->uuid('target_uuid');
            foreach (['building', 'floor', 'space', 'asset'] as $k) {
                $t->foreignId($k.'_id')->nullable()->constrained()->nullOnDelete();
            }$t->timestamps();
            $t->unique(['target_type', 'target_uuid'], 'qr_target_unique');
        });
        Schema::create('qr_locators', function (Blueprint $t) {
            $t->id();
            $t->foreignId('qr_identity_id')->constrained()->restrictOnDelete();
            $t->string('locator', 48)->unique();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('field_invitations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->string('email')->nullable();
            $t->string('company_name', 160)->nullable();
            $t->boolean('allow_costs')->default(false);
            $t->timestamp('expires_at');
            $t->timestamp('redeemed_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['redeemed_by', 'revoked_at', 'expires_at'], 'field_user_grants');
        });
        Schema::create('field_invitation_work', function (Blueprint $t) {
            $t->foreignId('field_invitation_id')->constrained()->restrictOnDelete();
            $t->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $t->primary(['field_invitation_id', 'work_order_id'], 'field_work_pk');
        });
        Schema::create('field_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('field_invitation_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->nullOnDelete();
            $t->uuid('source_uuid');
            $t->timestamp('created_at');
            $t->unique(['field_invitation_id', 'source_uuid'], 'field_document_unique');
        });
        Schema::create('service_reports', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('field_invitation_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->unsignedInteger('work_revision');
            $t->string('state', 16);
            $t->json('snapshot');
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->nullOnDelete();
            $t->uuid('source_uuid')->nullable();
            $t->timestamp('created_at');
            $t->unique(['work_order_id', 'number'], 'service_report_number');
        });
        Schema::create('service_report_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_report_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('decision', 16);
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason');
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->nullOnDelete();
            $t->uuid('source_uuid')->nullable();
            $t->timestamp('created_at');
            $t->unique(['service_report_id', 'sequence'], 'service_review_sequence');
        });
        Schema::table('work_orders', function (Blueprint $t) {
            $t->unsignedInteger('minutes_spent')->nullable();
            $t->text('observed_condition')->nullable();
            $t->boolean('require_photo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', fn (Blueprint $t) => $t->dropColumn(['minutes_spent', 'observed_condition', 'require_photo']));
        foreach (['service_report_reviews', 'service_reports', 'field_documents', 'field_invitation_work', 'field_invitations', 'qr_locators', 'qr_identities'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
