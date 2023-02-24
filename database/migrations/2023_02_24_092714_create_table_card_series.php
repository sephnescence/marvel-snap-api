<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marvel_snap_card_series', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->string('name');
            // It seems that tstzrange isn't supported so I have to make do with two dates
            // $table->addColumn('tstzrange', 'lifespan');
            $table->timestampTz('lifespan_start');
            $table->timestampTz('lifespan_end');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('marvel_snap_cards', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->string('name');
            $table->timestampTz('lifespan_start');
            $table->timestampTz('lifespan_end');
            $table->jsonb('snapfan_data')->default('{}'); // Default to an empty object instead of null
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('marvel_snap_card_card_series', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('marvel_snap_card_series_id')->references('id')->on('marvel_snap_card_series');
            $table->foreignUuid('marvel_snap_card_id')->references('id')->on('marvel_snap_cards');
            $table->timestampTz('lifespan_start');
            $table->timestampTz('lifespan_end');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marvel_snap_card_card_series');
        Schema::dropIfExists('marvel_snap_card');
        Schema::dropIfExists('marvel_snap_card_series');
    }
};
