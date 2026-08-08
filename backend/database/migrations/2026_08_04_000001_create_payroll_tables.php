<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Golongan/Grade Gaji
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 20)->comment('I-A, II-B, III-C');
            $table->string('name', 100)->comment('Golongan I-A');
            $table->decimal('base_salary', 15, 2)->comment('Gaji pokok');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Komponen Gaji (Tunjangan & Potongan)
        Schema::create('salary_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 30)->comment('TJ-JABATAN, POT-BPJS');
            $table->string('name', 100)->comment('Tunjangan Jabatan');
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('calculation_type', ['fixed', 'percentage', 'per_day', 'per_hour', 'formula'])
                ->default('fixed');
            $table->decimal('default_value', 15, 2)->default(0);
            $table->string('percentage_of', 50)->nullable()
                ->comment('base_salary, gross_salary, nett_salary');
            $table->string('formula')->nullable()
                ->comment('Custom formula for calculation');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'is_active']);
        });

        // Tarif BPJS
        Schema::create('bpjs_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->enum('type', ['kesehatan', 'jht', 'jkk', 'jkm', 'jp']);
            $table->string('name', 100);
            $table->decimal('employee_rate', 5, 2)->default(0)->comment('Ditanggung karyawan (%)');
            $table->decimal('employer_rate', 5, 2)->default(0)->comment('Ditanggung perusahaan (%)');
            $table->decimal('min_salary', 15, 2)->nullable()->comment('Batas bawah (UMR)');
            $table->decimal('max_salary', 15, 2)->nullable()->comment('Batas atas (ceiling)');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'type', 'is_active']);
            $table->index(['tenant_id', 'effective_from', 'effective_until']);
        });

        // Bracket Tarif PPh 21 (Progresif)
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->decimal('min_amount', 15, 2)->comment('Batas bawah PKP');
            $table->decimal('max_amount', 15, 2)->nullable()->comment('Batas atas (NULL = tidak terbatas)');
            $table->decimal('rate', 5, 2)->comment('Persentase pajak');
            $table->integer('effective_year')->comment('Tahun pajak');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'effective_year', 'is_active']);
        });

        // Pengaturan Pajak (PTKP, dll)
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('setting_key', 50)->comment('ptkp_tk0, ptkp_k0, biaya_jabatan_rate');
            $table->string('setting_name', 100)->comment('PTKP TK/0 (Tidak Kawin)');
            $table->decimal('setting_value', 15, 2)->comment('Nilai nominal/persentase');
            $table->string('category', 50)->comment('ptkp, biaya_jabatan, ter, other');
            $table->text('description')->nullable();
            $table->integer('effective_year');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'setting_key', 'effective_year'], 'tax_settings_unique');
            $table->index(['tenant_id', 'category', 'is_active']);
        });

        // Setup Gaji per Karyawan
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->enum('employee_type', ['teacher', 'staff']);
            $table->uuid('employee_id')->comment('FK ke teachers atau staff');
            $table->uuid('salary_grade_id')->nullable();
            $table->decimal('base_salary', 15, 2)->comment('Override dari grade');
            $table->string('ptkp_status', 10)->default('TK/0')
                ->comment('TK/0, K/0, K/1, K/2, K/3');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('salary_grade_id')->references('id')->on('salary_grades')->onDelete('set null');
            $table->index(['tenant_id', 'employee_type', 'employee_id']);
            $table->index(['tenant_id', 'is_current']);
        });

        // Komponen Gaji per Karyawan
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_salary_id');
            $table->uuid('salary_component_id');
            $table->decimal('value', 15, 2)->comment('Override dari default');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('employee_salary_id')->references('id')->on('employee_salaries')->onDelete('cascade');
            $table->foreign('salary_component_id')->references('id')->on('salary_components')->onDelete('cascade');
            $table->unique(['employee_salary_id', 'salary_component_id'], 'emp_salary_component_unique');
        });

        // Riwayat Perubahan Gaji
        Schema::create('salary_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_salary_id');
            $table->uuid('changed_by');
            $table->enum('change_type', ['initial', 'promotion', 'adjustment', 'demotion']);
            $table->uuid('old_grade_id')->nullable();
            $table->uuid('new_grade_id')->nullable();
            $table->decimal('old_base_salary', 15, 2)->nullable();
            $table->decimal('new_base_salary', 15, 2);
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('employee_salary_id')->references('id')->on('employee_salaries')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('old_grade_id')->references('id')->on('salary_grades')->onDelete('set null');
            $table->foreign('new_grade_id')->references('id')->on('salary_grades')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_histories');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('tax_settings');
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('bpjs_rates');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('salary_grades');
    }
};
