<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['maintenance_issues', 'maintenance_plans', 'work_orders'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->string('reference', 30)->unique();
                $t->foreignId('organization_id')->constrained()->restrictOnDelete();
                $t->foreignId('property_id')->constrained()->restrictOnDelete();
                $t->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
                $t->string('title', 180);
                $t->text('description')->nullable();
                $t->string('category', 30);
                $t->unsignedInteger('revision')->default(1);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['organization_id', 'property_id', 'id'], $name.'_scope');
                if ($name === 'maintenance_issues') {
                    $t->string('state', 20)->default('draft');
                    $t->string('severity', 20);
                    $t->string('impact', 20)->default('unknown');
                    $t->timestamp('reported_at')->nullable();
                    $t->timestamp('closed_at')->nullable();
                } else {
                    $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                    $t->string('team', 120)->nullable();
                    $t->json('checklist')->nullable();
                    if ($name === 'maintenance_plans') {
                        $t->foreignId('managed_by')->nullable()->constrained('users')->nullOnDelete();
                        $t->string('state', 20)->default('active');
                        $t->string('frequency', 20);
                        $t->unsignedInteger('interval_count')->default(1);
                        $t->string('interval_unit', 10)->default('month');
                        $t->date('anchor_on');
                        $t->date('next_on')->nullable();
                        $t->timestamp('next_at')->nullable()->index();
                        $t->unsignedInteger('occurrence')->default(0);
                        $t->unsignedInteger('estimated_minutes')->nullable();
                        $t->boolean('notify_assignee')->default(false);
                        $t->text('suspension_reason')->nullable();
                        $t->index(['state', 'next_on']);
                    } else {
                        $t->string('state', 24)->default('reported');
                        $t->string('priority', 20);
                        $t->timestamp('due_at')->nullable();
                        $t->foreignId('plan_id')->nullable()->constrained('maintenance_plans')->restrictOnDelete();
                        $t->date('scheduled_for')->nullable();
                        $t->unique(['plan_id', 'scheduled_for'], 'work_plan_occurrence');
                        $t->text('work_performed')->nullable();
                        $t->json('parts')->nullable();
                        $t->decimal('labor_cost', 14, 2)->nullable();
                        $t->decimal('external_cost', 14, 2)->nullable();
                        $t->decimal('materials_cost', 14, 2)->nullable();
                        $t->string('currency', 3)->nullable();
                        $t->timestamp('resolved_at')->nullable();
                        $t->timestamp('verified_at')->nullable();
                        $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                        $t->timestamp('closed_at')->nullable();
                        $t->index(['organization_id', 'state', 'due_at'], 'work_queue_idx');
                        $t->index(['asset_id', 'category', 'resolved_at'], 'work_repeat_idx');
                    }
                }
            });
        }
        Schema::create('maintenance_issue_work', function (Blueprint $t) {
            $t->foreignId('issue_id')->constrained('maintenance_issues')->restrictOnDelete();
            $t->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $t->primary(['issue_id', 'work_order_id']);
        });
        Schema::create('maintenance_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('private')->default(false);
            $t->text('body');
            $t->timestamp('created_at');
        });
        Schema::create('maintenance_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('issue_id')->nullable()->constrained('maintenance_issues')->restrictOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('version_id')->nullable()->constrained('evidence_versions')->nullOnDelete();
            $t->uuid('source_uuid');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
            $t->unique(['work_order_id', 'source_uuid'], 'work_document_unique');
            $t->unique(['issue_id', 'source_uuid'], 'issue_document_unique');
        });
        Schema::table('asset_audits', function (Blueprint $t) {
            $t->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('entity_type', 20)->nullable();
            $t->uuid('entity_uuid')->nullable();
            $t->index(['entity_type', 'entity_uuid', 'id'], 'operational_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asset_audits', function (Blueprint $t) {
            $t->dropIndex('operational_history_idx');
            $t->dropConstrainedForeignId('property_id');
            $t->dropColumn(['entity_type', 'entity_uuid']);
        });
        foreach (['maintenance_attachments', 'maintenance_comments', 'maintenance_issue_work', 'work_orders', 'maintenance_plans', 'maintenance_issues'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
