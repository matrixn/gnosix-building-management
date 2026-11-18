<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });
        Schema::create('customer_admin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 24);
            $table->json('fields');
            $table->timestamp('created_at');
        });

        Schema::table('campaign_visits', function (Blueprint $table) {
            $table->date('traffic_day')->nullable();
            $table->string('referrer_host', 253)->nullable();
        });
        Schema::create('campaign_daily_totals', function (Blueprint $table) {
            $table->foreignId('campaign_link_id')->constrained()->cascadeOnDelete();
            $table->date('traffic_day');
            $table->char('country_code', 2);
            $table->string('referrer_host', 253);
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('converted_visits')->default(0);
            $table->unsignedBigInteger('contacts')->default(0);
            $table->unsignedBigInteger('registrations')->default(0);
            $table->primary(['campaign_link_id', 'traffic_day', 'country_code', 'referrer_host'], 'campaign_daily_pk');
            $table->index(['traffic_day', 'campaign_link_id'], 'campaign_daily_range');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_daily_totals');
        Schema::table('campaign_visits', fn (Blueprint $table) => $table->dropColumn(['traffic_day', 'referrer_host']));
        Schema::dropIfExists('customer_admin_events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
