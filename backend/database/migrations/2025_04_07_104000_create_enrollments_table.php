<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('payment_status', 32)->default('pending');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 8)->default('brl');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->timestamps();

            $table->index(['payment_status', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
