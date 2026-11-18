<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'building_revisions' => ['tm_structure_time' => ['building_id', 'entity_type', 'created_at', 'id']],
        'asset_audits' => ['tm_operational_time' => ['property_id', 'organization_id', 'entity_type', 'created_at', 'id']],
        'evidence_assertions' => ['tm_assertion_time' => ['building_id', 'created_at', 'id']],
        'evidence_reviews' => ['tm_review_time' => ['assertion_id', 'created_at', 'id']],
        'evidence_audits' => ['tm_document_time' => ['building_id', 'entity_reference', 'action', 'created_at', 'id']],
        'evidence_versions' => ['tm_version_time' => ['building_id', 'created_at', 'id']],
        'service_reports' => ['tm_report_time' => ['property_id', 'organization_id', 'created_at', 'id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $name => $columns) {
                    $blueprint->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach (array_keys($indexes) as $name) {
                    $blueprint->dropIndex($name);
                }
            });
        }
    }
};
