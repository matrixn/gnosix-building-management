<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_visits', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable();
        });

        Schema::create('campaign_link_countries', function (Blueprint $table) {
            $table->foreignId('campaign_link_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('converted_visits')->default(0);
            $table->primary(['campaign_link_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_link_countries');
        Schema::table('campaign_visits', fn (Blueprint $table) => $table->dropColumn('country_code'));
    }
};
