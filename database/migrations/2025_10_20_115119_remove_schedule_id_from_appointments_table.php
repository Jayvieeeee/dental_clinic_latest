<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'schedule_id')) {
                // Drop foreign key constraint first (if exists)
                $table->dropForeign(['schedule_id']);
                // Then drop the column
                $table->dropColumn('schedule_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'schedule_id')) {
                $table->unsignedBigInteger('schedule_id')->nullable()->after('service_id');
                $table->foreign('schedule_id')->references('schedule_id')->on('schedules')->onDelete('set null');
            }
        });
    }
};
