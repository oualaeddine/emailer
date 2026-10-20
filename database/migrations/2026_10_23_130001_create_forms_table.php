<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot Q — `forms`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('status', 16)->default('draft')->index();
            $table->json('list_ids');
            $table->json('fields');
            $table->json('gdpr');
            $table->json('design');
            $table->json('settings');
            $table->foreignId('doi_template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->unsignedInteger('submissions_count')->default(0);
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->timestamp('last_submission_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
