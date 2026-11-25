<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'month']);
        });
        Schema::create('monthly_schedule_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_schedule_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['monthly_schedule_id', 'revision']);
        });
        Schema::create('monthly_schedule_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->ulid('operation_id');
            $table->char('payload_hash', 64);
            $table->string('month', 7);
            $table->unsignedInteger('revision');
            $table->timestamp('created_at');
            $table->unique(['company_id', 'operation_id'], 'monthly_operation_unique');
        });
        Schema::create('monthly_schedule_revision_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monthly_schedule_revision_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('monthly_schedule_revision_id', 'msre_revision_fk')->references('id')->on('monthly_schedule_revisions')->restrictOnDelete();
            $table->foreign(['company_id', 'employee_id'], 'msre_employee_company_fk')->references(['company_id', 'id'])->on('employees')->restrictOnDelete();
            $table->unique(['monthly_schedule_revision_id', 'employee_id'], 'msre_revision_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_schedule_revision_employees');
        Schema::dropIfExists('monthly_schedule_operations');
        Schema::dropIfExists('monthly_schedule_revisions');
        Schema::dropIfExists('monthly_schedules');
    }
};
