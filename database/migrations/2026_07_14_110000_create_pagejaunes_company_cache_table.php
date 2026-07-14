<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.7 — PageJaunes Integration Module:
 * `pagejaunes_company_cache`. Lives on our OWN app database connection
 * (default), not the `pagejaunes` connection — this table mirrors
 * selected fields from the external `companies`/`companies_details`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagejaunes_company_cache', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pagejaunes_company_id')->unique();
            $table->string('pagejaunes_code', 20)->unique();
            $table->string('company_name', 191)->nullable();
            $table->string('abv_company_name', 191)->nullable();
            $table->string('slug', 191)->nullable();
            $table->string('legal_form_label', 150)->nullable();
            $table->string('country_label', 150)->nullable();
            $table->string('wilaya_label', 150)->nullable();
            $table->string('locality_label', 150)->nullable();
            $table->text('address')->nullable();
            $table->text('about')->nullable();
            $table->boolean('is_distributor')->default(false);
            $table->boolean('is_exporter')->default(false);
            $table->boolean('is_importer')->default(false);
            $table->boolean('is_producer')->default(false);
            $table->boolean('is_customer')->default(false);
            $table->string('nb_employee', 20)->nullable();
            $table->string('website', 191)->nullable();
            $table->string('main_picture_url', 255)->nullable();
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_deleted_at')->nullable();
            $table->json('raw_source_data')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestamps();

            $table->index('wilaya_label');
            $table->fullText(['company_name', 'abv_company_name', 'address', 'about'], 'pj_company_cache_search_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagejaunes_company_cache');
    }
};
