<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unique(['company_id', 'id']);
        });
        Schema::create('work_patterns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'is_active']);
        });
        Schema::create('work_pattern_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('work_pattern_id');
            $table->string('kind', 16);
            $table->json('sequence');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->foreign(['company_id', 'work_pattern_id'])->references(['company_id', 'id'])->on('work_patterns')->restrictOnDelete();
        });
        Schema::create('employee_work_patterns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('work_pattern_version_id');
            $table->date('reference_date');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'starts_on', 'ends_on'], 'ewp_period_idx');
            $table->foreign(['company_id', 'employee_id'])->references(['company_id', 'id'])->on('employees')->restrictOnDelete();
            $table->foreign(['company_id', 'work_pattern_version_id'], 'ewp_version_company_fk')->references(['company_id', 'id'])->on('work_pattern_versions')->restrictOnDelete();
        });
        Schema::create('schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->boolean('is_work');
            $table->string('reason');
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'date']);
            $table->foreign(['company_id', 'employee_id'])->references(['company_id', 'id'])->on('employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_overrides');
        Schema::dropIfExists('employee_work_patterns');
        Schema::dropIfExists('work_pattern_versions');
        Schema::dropIfExists('work_patterns');
        Schema::table('employees', fn (Blueprint $table) => $table->dropUnique(['company_id', 'id']));
    }
};
