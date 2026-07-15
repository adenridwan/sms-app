<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fee Types (Jenis Biaya)
        Schema::create('fee_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // SPP, Uang Pangkal, Seragam, dll
            $table->string('code');
            $table->text('description')->nullable();
            $table->enum('frequency', ['once', 'monthly', 'semester', 'yearly'])->default('monthly');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Fee Structures (Struktur Biaya per Grade/Major)
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->uuid('fee_type_id');
            $table->uuid('grade_level_id')->nullable();
            $table->uuid('major_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->integer('due_day')->nullable()->comment('Day of month for recurring');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('fee_type_id')->references('id')->on('fee_types')->onDelete('cascade');
            $table->foreign('grade_level_id')->references('id')->on('grade_levels')->onDelete('cascade');
            $table->foreign('major_id')->references('id')->on('majors')->onDelete('cascade');
        });

        // Student Fees (Tagihan Siswa)
        Schema::create('student_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('student_id');
            $table->uuid('fee_structure_id');
            $table->uuid('academic_year_id');
            $table->tinyInteger('month')->nullable();
            $table->smallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('fine', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue', 'waived'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');

            $table->index(['student_id', 'status']);
            $table->index(['tenant_id', 'due_date', 'status']);
        });

        // Discounts/Scholarships
        Schema::create('discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 15, 2);
            $table->uuid('fee_type_id')->nullable(); // null = all fee types
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('fee_type_id')->references('id')->on('fee_types')->onDelete('cascade');
        });

        // Student Discounts
        Schema::create('student_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('discount_id');
            $table->uuid('academic_year_id');
            $table->text('reason')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('discount_id')->references('id')->on('discounts')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // Payment Methods
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code');
            $table->enum('type', ['cash', 'bank_transfer', 'virtual_account', 'e_wallet', 'credit_card', 'other']);
            $table->string('provider')->nullable();
            $table->json('configuration')->nullable();
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('invoice_number')->unique();
            $table->uuid('student_id');
            $table->uuid('payment_method_id')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('payment_proof')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('received_by')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        // Payment Items (Detail Pembayaran)
        Schema::create('payment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('student_fee_id');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('student_fee_id')->references('id')->on('student_fees')->onDelete('cascade');
        });

        // Financial Reports
        Schema::create('financial_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->decimal('total_billed', 15, 2)->default(0);
            $table->decimal('total_collected', 15, 2)->default(0);
            $table->decimal('total_outstanding', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('total_fine', 15, 2)->default(0);
            $table->integer('student_count')->default(0);
            $table->integer('paid_student_count')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('cascade');

            $table->unique(['tenant_id', 'academic_year_id', 'month', 'year'], 'financial_report_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
        Schema::dropIfExists('payment_items');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('student_discounts');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};
