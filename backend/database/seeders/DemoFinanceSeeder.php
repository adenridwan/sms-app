<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemoFinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->first();
        if (!$tenant) return;

        $academicYear = DB::table('academic_years')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();
        if (!$academicYear) return;

        $this->createFeeStructures($tenant->id, $academicYear->id);
        $this->generateStudentFees($tenant->id, $academicYear->id);
        $this->generatePayments($tenant->id);

        $this->command->info("Created demo finance data.");
    }

    protected function createFeeStructures(string $tenantId, string $academicYearId): void
    {
        $feeTypes = DB::table('fee_types')
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('code');

        $gradeLevels = DB::table('grade_levels')
            ->where('tenant_id', $tenantId)
            ->get();

        // Fee amounts per type and grade
        $feeAmounts = [
            'SPP' => ['X' => 500000, 'XI' => 550000, 'XII' => 600000],
            'UK' => ['X' => 750000, 'XI' => 750000, 'XII' => 750000],
            'UPRAK' => ['X' => 200000, 'XI' => 250000, 'XII' => 300000],
        ];

        foreach ($gradeLevels as $gradeLevel) {
            foreach ($feeAmounts as $feeCode => $amounts) {
                if (!isset($feeTypes[$feeCode])) continue;

                $amount = $amounts[$gradeLevel->code] ?? $amounts['X'];

                DB::table('fee_structures')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenantId,
                    'academic_year_id' => $academicYearId,
                    'fee_type_id' => $feeTypes[$feeCode]->id,
                    'grade_level_id' => $gradeLevel->id,
                    'amount' => $amount,
                    'due_day' => 10, // 10th of each month
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function generateStudentFees(string $tenantId, string $academicYearId): void
    {
        $students = DB::table('students')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();

        $enrollments = DB::table('student_enrollments')
            ->where('tenant_id', $tenantId)
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->keyBy('student_id');

        $classrooms = DB::table('classrooms')
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('id');

        $feeStructures = DB::table('fee_structures as fs')
            ->join('fee_types as ft', 'ft.id', '=', 'fs.fee_type_id')
            ->where('fs.tenant_id', $tenantId)
            ->where('fs.academic_year_id', $academicYearId)
            ->where('ft.code', 'SPP')
            ->select('fs.*')
            ->get()
            ->keyBy('grade_level_id');

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        // Generate SPP for last 6 months
        $feeCount = 0;
        foreach ($students as $student) {
            if (!isset($enrollments[$student->id])) continue;

            $enrollment = $enrollments[$student->id];
            $classroom = $classrooms[$enrollment->classroom_id] ?? null;
            if (!$classroom) continue;

            $feeStructure = $feeStructures[$classroom->grade_level_id] ?? null;
            if (!$feeStructure) continue;

            for ($i = 5; $i >= 0; $i--) {
                $feeDate = Carbon::create($currentYear, $currentMonth)->subMonths($i);
                $month = $feeDate->month;
                $year = $feeDate->year;

                // Random discount (10% of students get 20% discount)
                $discount = (rand(1, 10) === 1) ? $feeStructure->amount * 0.2 : 0;
                $totalAmount = $feeStructure->amount - $discount;

                // Determine paid status (older months more likely paid)
                $isPaid = $i > 2 ? (rand(1, 10) <= 9) : (rand(1, 10) <= 6);
                $paidAmount = $isPaid ? $totalAmount : (rand(0, 1) ? $totalAmount * 0.5 : 0);
                $remainingAmount = $totalAmount - $paidAmount;

                $status = match (true) {
                    $remainingAmount <= 0 => 'paid',
                    $paidAmount > 0 => 'partial',
                    $feeDate->day(10)->isPast() => 'overdue',
                    default => 'unpaid',
                };

                DB::table('student_fees')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'fee_structure_id' => $feeStructure->id,
                    'academic_year_id' => $academicYearId,
                    'month' => $month,
                    'year' => $year,
                    'amount' => $feeStructure->amount,
                    'discount' => $discount,
                    'fine' => $status === 'overdue' ? 25000 : 0,
                    'total_amount' => $totalAmount + ($status === 'overdue' ? 25000 : 0),
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount + ($status === 'overdue' ? 25000 : 0),
                    'due_date' => $feeDate->day(10)->format('Y-m-d'),
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $feeCount++;
            }
        }

        $this->command->info("Generated {$feeCount} student fee records.");
    }

    protected function generatePayments(string $tenantId): void
    {
        // Get paid/partial fees that don't already have payments
        $paidFees = DB::table('student_fees')
            ->where('tenant_id', $tenantId)
            ->where('paid_amount', '>', 0)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payment_items')
                    ->whereColumn('payment_items.student_fee_id', 'student_fees.id');
            })
            ->get();

        if ($paidFees->isEmpty()) {
            $this->command->info("No new payments to generate.");
            return;
        }

        $paymentMethods = DB::table('payment_methods')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        if (empty($paymentMethods)) {
            $this->command->warn("No active payment methods found. Skipping payment generation.");
            return;
        }

        $bendahara = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('username', 'bendahara')
            ->first();

        $paymentCount = 0;
        foreach ($paidFees as $fee) {
            $paymentId = Str::uuid()->toString();
            $invoiceNumber = 'INV-' . date('Ymd', strtotime($fee->due_date)) . '-' . strtoupper(Str::random(6));

            // Use transaction to ensure payment and payment_item are created together
            DB::transaction(function () use ($tenantId, $paymentId, $invoiceNumber, $fee, $paymentMethods, $bendahara) {
                DB::table('payments')->insert([
                    'id' => $paymentId,
                    'tenant_id' => $tenantId,
                    'invoice_number' => $invoiceNumber,
                    'student_id' => $fee->student_id,
                    'payment_method_id' => $paymentMethods[array_rand($paymentMethods)],
                    'total_amount' => $fee->paid_amount,
                    'admin_fee' => 0,
                    'grand_total' => $fee->paid_amount,
                    'status' => 'completed',
                    'paid_at' => Carbon::parse($fee->due_date)->addDays(rand(0, 15)),
                    'verified_by' => $bendahara?->id,
                    'verified_at' => Carbon::parse($fee->due_date)->addDays(rand(0, 15)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('payment_items')->insert([
                    'id' => Str::uuid()->toString(),
                    'payment_id' => $paymentId,
                    'student_fee_id' => $fee->id,
                    'amount' => $fee->paid_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $paymentCount++;
        }

        $this->command->info("Generated {$paymentCount} payment records.");
    }
}
