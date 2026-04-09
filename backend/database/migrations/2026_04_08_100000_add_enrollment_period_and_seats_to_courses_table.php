<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->date('enrollment_starts_at')->nullable()->after('active');
            $table->date('enrollment_ends_at')->nullable()->after('enrollment_starts_at');
            $table->unsignedInteger('max_seats')->nullable()->after('enrollment_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['enrollment_starts_at', 'enrollment_ends_at', 'max_seats']);
        });
    }
};
