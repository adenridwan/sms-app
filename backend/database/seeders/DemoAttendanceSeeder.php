<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemoAttendanceSeeder extends Seeder
{
    protected array $statuses = ['present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'late', 'sick', 'permitted', 'absent'];

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

        $semester = DB::table('semesters')
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->first();
        if (!$semester) return;

        // Get all enrollments with students and classrooms
        $enrollments = DB::table('student_enrollments as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('classrooms as c', 'c.id', '=', 'e.classroom_id')
            ->where('e.tenant_id', $tenant->id)
            ->where('e.academic_year_id', $academicYear->id)
            ->select('e.*', 's.id as student_id')
            ->get();

        // Generate attendance for last 30 school days
        $attendanceCount = 0;
        $today = Carbon::now();
        $startDate = $today->copy()->subDays(45);

        for ($date = $startDate; $date <= $today; $date->addDay()) {
            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($enrollments as $enrollment) {
                $status = $this->statuses[array_rand($this->statuses)];

                $checkInTime = null;
                $checkOutTime = null;

                if (in_array($status, ['present', 'late'])) {
                    $checkInHour = $status === 'late' ? rand(7, 8) : 6;
                    $checkInMinute = $status === 'late' ? rand(35, 59) : rand(30, 55);
                    $checkInTime = sprintf('%02d:%02d:00', $checkInHour, $checkInMinute);
                    $checkOutTime = sprintf('%02d:%02d:00', rand(14, 15), rand(0, 30));
                }

                DB::table('student_attendances')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenant->id,
                    'student_id' => $enrollment->student_id,
                    'classroom_id' => $enrollment->classroom_id,
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semester->id,
                    'attendance_date' => $date->format('Y-m-d'),
                    'status' => $status,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $attendanceCount++;
            }
        }

        $this->command->info("Created {$attendanceCount} attendance records.");
    }
}
