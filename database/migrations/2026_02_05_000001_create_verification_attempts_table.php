<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('attempt_id')->nullable()->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('method'); // otp|biometric|hybrid
            $table->string('scenario')->default('normal');
            $table->boolean('is_legitimate')->nullable();
            $table->boolean('verification_passed');
            $table->boolean('attack_succeeded')->default(false);
            $table->unsignedInteger('completion_time_ms')->nullable();
            $table->string('failure_cause')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['method', 'scenario']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_attempts');
    }
};
