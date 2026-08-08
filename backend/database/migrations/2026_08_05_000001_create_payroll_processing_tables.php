<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode Penggajian
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 100)->comment('Gaji Bulan Januari 2026');
            $table->integer('year');
            $table->integer('month');
            $table->date('start_date')->comment('Awal periode kerja');
            $table->date('end_date')->comment('Akhir periode kerja');
            $table->date('payment_date')->nullable()->comment('Tanggal pembayaran gaji');
            $table->enum('status', ['draft', 'processing', 'pending_approval', 'approved', 'paid', 'finalized'])
                ->default('draft');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_gross', 18, 2)->default(0)->comment('Total pendapatan bruto');
            $table->decimal('total_deductions', 18, 2)->default(0)->comment('Total potongan');
            $table->decimal('total_net', 18, 2)->default(0)->comment('Total gaji bersih');
            $table->integer('employee_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('finalized_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['tenant_id', 'year', 'month']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'year']);
        });

        // Slip Gaji per Karyawan
        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_period_id');
            $table->uuid('employee_salary_id');
            $table->enum('employee_type', ['teacher', 'staff']);
            $table->uuid('employee_id')->comment('FK ke teachers atau staff');
            $table->string('employee_name', 200)->comment('Snapshot nama saat generate');
            $table->string('employee_identifier', 50)->nullable()->comment('NIP/Employee ID snapshot');
            $table->string('salary_grade_code', 20)->nullable()->comment('Snapshot golongan');
            $table->string('ptkp_status', 10)->comment('Snapshot status PTKP');

            // Pendapatan
            $table->decimal('base_salary', 15, 2)->comment('Gaji pokok');
            $table->decimal('total_allowances', 15, 2)->default(0)->comment('Total tunjangan');
            $table->decimal('total_overtime', 15, 2)->default(0)->comment('Total lembur');
            $table->decimal('total_other_income', 15, 2)->default(0)->comment('Honor, dll');
            $table->decimal('gross_salary', 15, 2)->comment('Total pendapatan bruto');

            // Potongan
            $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
            $table->decimal('bpjs_jht', 15, 2)->default(0);
            $table->decimal('bpjs_jp', 15, 2)->default(0);
            $table->decimal('pph21', 15, 2)->default(0)->comment('Pajak PPh 21');
            $table->decimal('total_other_deductions', 15, 2)->default(0)->comment('Potongan lainnya');
            $table->decimal('total_deductions', 15, 2)->comment('Total potongan');

            // Hasil
            $table->decimal('net_salary', 15, 2)->comment('Gaji bersih (take home pay)');

            // Kehadiran (dari modul attendance)
            $table->integer('working_days')->default(0)->comment('Total hari kerja');
            $table->integer('days_present')->default(0);
            $table->integer('days_absent')->default(0);
            $table->integer('days_late')->default(0);
            $table->integer('days_leave')->default(0);
            $table->decimal('attendance_deduction', 15, 2)->default(0)->comment('Potongan absensi');

            $table->enum('status', ['draft', 'calculated', 'approved', 'paid'])->default('draft');
            $table->uuid('calculated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('payroll_period_id')->references('id')->on('payroll_periods')->onDelete('cascade');
            $table->foreign('employee_salary_id')->references('id')->on('employee_salaries')->onDelete('cascade');
            $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['payroll_period_id', 'employee_salary_id']);
            $table->index(['payroll_period_id', 'employee_type']);
            $table->index(['payroll_period_id', 'status']);
        });

        // Detail Item Slip Gaji
        Schema::create('payroll_slip_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_slip_id');
            $table->uuid('salary_component_id')->nullable()->comment('NULL jika item manual');
            $table->string('component_code', 30);
            $table->string('component_name', 100);
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('category', ['fixed', 'variable', 'attendance', 'tax', 'bpjs', 'other'])
                ->default('fixed');
            $table->decimal('amount', 15, 2);
            $table->decimal('quantity', 8, 2)->default(1)->comment('Untuk per_day, per_hour');
            $table->decimal('rate', 15, 2)->nullable()->comment('Rate per unit');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_auto_calculated')->default(true)->comment('false = input manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('payroll_slip_id')->references('id')->on('payroll_slips')->onDelete('cascade');
            $table->foreign('salary_component_id')->references('id')->on('salary_components')->onDelete('set null');
            $table->index(['payroll_slip_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slip_items');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_periods');
    }
};
