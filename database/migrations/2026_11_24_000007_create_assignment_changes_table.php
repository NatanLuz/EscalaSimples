<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unique(['company_id', 'id'], 'users_company_id_id_unique'));
        Schema::table('employee_work_patterns', fn (Blueprint $table) => $table->unique(['company_id', 'id'], 'ewp_company_id_id_unique'));
        Schema::create('assignment_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('employee_work_pattern_id');
            $table->date('previous_starts_on');
            $table->date('previous_ends_on')->nullable();
            $table->date('new_starts_on');
            $table->date('new_ends_on')->nullable();
            $table->string('reason');
            $table->timestamp('created_at');
            $table->foreign(['company_id', 'user_id'], 'ac_user_fk')->references(['company_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['company_id', 'employee_work_pattern_id'], 'ac_assignment_fk')->references(['company_id', 'id'])->on('employee_work_patterns')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_changes');
        Schema::table('employee_work_patterns', fn (Blueprint $table) => $table->dropUnique('ewp_company_id_id_unique'));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_company_id_id_unique'));
    }
};
