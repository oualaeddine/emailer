<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot M — FKs of the two campaign columns added by slot B. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign('unsubscribe_page_id')->references('id')->on('unsubscribe_pages')->nullOnDelete();
            $table->foreign('update_profile_form_id')->references('id')->on('update_profile_forms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['unsubscribe_page_id']);
            $table->dropForeign(['update_profile_form_id']);
        });
    }
};
