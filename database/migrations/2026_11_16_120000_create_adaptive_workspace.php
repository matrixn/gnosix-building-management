<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('legal_name', 150)->nullable();
            $table->string('country', 2)->default('RO');
            $table->string('timezone', 64)->default('Europe/Bucharest');
            $table->string('locale', 2)->default('ro');
            $table->string('unit_system', 10)->default('metric');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_address', 500)->nullable();
            $table->string('logo_path')->nullable();
        });
        Schema::table('organization_memberships', fn (Blueprint $table) => $table->string('access_scope', 20)->default('all'));
        Schema::table('user_preferences', fn (Blueprint $table) => $table->boolean('guided_setup')->default(true));
        Schema::create('workspace_groups', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150); $table->string('kind', 20); $table->timestamps();
        });
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150); $table->string('country', 2)->nullable(); $table->timestamps();
        });
        Schema::create('properties', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150); $table->string('property_type', 40);
            $table->string('country', 2); $table->string('timezone', 64);
            $table->string('address', 500)->nullable(); $table->json('amenities')->nullable(); $table->timestamps();
        });
        Schema::create('property_group', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['property_id', 'workspace_group_id']);
        });
        Schema::create('buildings', function (Blueprint $table) {
            $table->id(); $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150); $table->timestamps();
        });
        Schema::create('floors', function (Blueprint $table) {
            $table->id(); $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150); $table->integer('position'); $table->timestamps();
        });
        Schema::create('spaces', function (Blueprint $table) {
            $table->id(); $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150); $table->string('space_type', 40);
            $table->string('number', 40)->nullable(); $table->unsignedInteger('bed_count')->nullable();
            $table->unsignedInteger('capacity')->nullable(); $table->decimal('area_m2', 12, 3)->nullable(); $table->timestamps();
        });
        Schema::create('property_accesses', function (Blueprint $table) {
            $table->id(); $table->foreignId('membership_id')->constrained('organization_memberships')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 20); $table->timestamps(); $table->unique(['membership_id', 'property_id']);
        });
        Schema::create('workspace_drafts', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('state'); $table->string('step', 20)->default('vision');
            $table->boolean('guided')->default(true); $table->unsignedInteger('version')->default(1);
            $table->json('result')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['workspace_drafts', 'property_accesses', 'spaces', 'floors', 'buildings', 'property_group', 'properties', 'legal_entities', 'workspace_groups'] as $table) Schema::dropIfExists($table);
        Schema::table('user_preferences', fn (Blueprint $table) => $table->dropColumn('guided_setup'));
        Schema::table('organization_memberships', fn (Blueprint $table) => $table->dropColumn('access_scope'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['legal_name', 'country', 'timezone', 'locale', 'unit_system', 'contact_email', 'contact_phone', 'contact_address', 'logo_path']));
    }
};
