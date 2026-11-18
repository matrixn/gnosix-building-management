<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_links', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('path', 8);
            $table->json('tags');
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('converted_visits')->default(0);
            $table->unsignedBigInteger('contacts')->default(0);
            $table->unsignedBigInteger('registrations')->default(0);
            $table->timestamp('last_seen_at')->index();
        });

        Schema::create('campaign_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('campaign_link_id')->constrained()->cascadeOnDelete();
            $table->boolean('contacted')->default(false);
            $table->boolean('registered')->default(false);
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_visits');
        Schema::dropIfExists('campaign_links');
    }
};
