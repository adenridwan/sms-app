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
        Schema::create('payroll_slip_item_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_slip_item_id');
            $table->uuid('payroll_slip_id');
            $table->uuid('changed_by');
            $table->string('field_name', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign('payroll_slip_item_id')
                ->references('id')
                ->on('payroll_slip_items')
                ->onDelete('cascade');

            $table->foreign('payroll_slip_id')
                ->references('id')
                ->on('payroll_slips')
                ->onDelete('cascade');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['payroll_slip_id', 'created_at']);
            $table->index(['payroll_slip_item_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_slip_item_audits');
    }
};
