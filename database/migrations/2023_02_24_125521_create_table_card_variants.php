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
        Schema::create('marvel_snap_card_variants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('gen_random_uuid()'));
            $table->foreignUuid('marvel_snap_card_id')->references('id')->on('marvel_snap_cards');
            $table->string('name');
            $table->timestampTz('lifespan_start'); // Probably won't accurately represent when the card became obtainable, as cards are generally created as soon as leaks are discovered
            $table->timestampTz('lifespan_end'); // I doubt I'll see variants removed, but you never know
            // It turns out that jsonb will change the order of the keys on me. Apparently by key length. So instead of reimplementing a PHP-PGSQL-compatible array sort I can just use a json field instead
            $table->json('snapfan_data')->default('{}'); // Default to an empty object instead of null
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marvel_snap_card_variants');
    }
};
