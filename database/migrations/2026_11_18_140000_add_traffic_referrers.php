<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_links', function (Blueprint $table) {
            $table->boolean('is_tagged')->default(true);
            $table->index(['is_tagged', 'last_seen_at'], 'campaign_link_kind_seen');
        });

        Schema::create('campaign_link_referrers', function (Blueprint $table) {
            $table->foreignId('campaign_link_id')->constrained()->cascadeOnDelete();
            $table->string('host', 253);
            $table->unsignedBigInteger('visits')->default(0);
            $table->primary(['campaign_link_id', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_link_referrers');
        Schema::table('campaign_links', function (Blueprint $table) {
            $table->dropIndex('campaign_link_kind_seen');
            $table->dropColumn('is_tagged');
        });
    }
};
