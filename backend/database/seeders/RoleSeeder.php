<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Role definitions with their permissions.
     */
    protected array $roles = [
        'super_admin' => [
            'description' => 'Super Administrator - Full system access',
            'permissions' => '*', // All permissions
        ],

        'admin' => [
            'description' => 'School Administrator - Manage school data',
            'permissions' => [
                // Dashboard
                'dashboard.view', 'dashboard.analytics',
                // Users
                'users.view', 'users.create', 'users.update', 'users.delete', 'users.manage', 'users.reset-password',
                // Roles
                'roles.view', 'roles.assign',
                // Academic
                'academic.view', 'academic.manage',
                'academic-years.view', 'academic-years.manage',
                'curricula.view', 'curricula.manage',
                'subjects.view', 'subjects.manage',
                'classrooms.view', 'classrooms.manage',
                'majors.view', 'majors.manage',
                'grade-levels.view', 'grade-levels.manage',
                'schedules.view', 'schedules.manage',
                // Students
                'students.view', 'students.create', 'students.update', 'students.delete', 'students.manage',
                'students.enroll', 'students.export', 'students.import',
                // Teachers
                'teachers.view', 'teachers.create', 'teachers.update', 'teachers.delete', 'teachers.manage',
                'teachers.assign-subjects',
                // Staff
                'staff.view', 'staff.create', 'staff.update', 'staff.delete', 'staff.manage',
                // Attendance
                'attendance.view', 'attendance.record', 'attendance.manage', 'attendance.report',
                // Exams & Grades
                'exams.view', 'exams.create', 'exams.update', 'exams.delete', 'exams.manage',
                'grades.view', 'grades.input', 'grades.update', 'grades.manage', 'grades.finalize', 'grades.export',
                // Finance
                'finance.view', 'finance.manage',
                'fees.view', 'fees.manage', 'fees.generate',
                'payments.view', 'payments.create', 'payments.verify', 'payments.manage',
                'discounts.view', 'discounts.manage',
                'finance.report',
                // Library
                'library.view', 'library.manage',
                'books.view', 'books.create', 'books.update', 'books.delete',
                'loans.view', 'loans.create', 'loans.return', 'loans.manage',
                // Reports
                'reports.view', 'reports.generate', 'reports.export',
                'report-cards.view', 'report-cards.generate', 'report-cards.approve', 'report-cards.print',
                // Notifications
                'notifications.view', 'notifications.manage',
                'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete', 'announcements.publish',
                // Settings
                'settings.view', 'settings.manage',
                'settings.school', 'settings.academic', 'settings.finance', 'settings.attendance',
                // Audit
                'audit.view', 'audit.export', 'activity.view',
            ],
        ],

        'kepala_sekolah' => [
            'description' => 'Kepala Sekolah - View all, approve reports',
            'permissions' => [
                'dashboard.view', 'dashboard.analytics',
                // Academic
                'academic.view', 'academic-years.view', 'curricula.view', 'subjects.view', 'classrooms.view', 'majors.view', 'grade-levels.view', 'schedules.view',
                // Students & Teachers
                'students.view', 'teachers.view', 'staff.view',
                // Attendance
                'attendance.view', 'attendance.report',
                // Grades
                'grades.view', 'grades.finalize',
                // Finance
                'finance.view', 'fees.view', 'payments.view', 'finance.report',
                // Reports
                'reports.view', 'reports.generate', 'reports.export',
                'report-cards.view', 'report-cards.approve', 'report-cards.print',
                // Notifications
                'notifications.view', 'announcements.view', 'announcements.create', 'announcements.publish',
                // Settings
                'settings.view',
            ],
        ],

        'wakil_kepala_sekolah' => [
            'description' => 'Wakil Kepala Sekolah',
            'permissions' => [
                'dashboard.view', 'dashboard.analytics',
                // Academic
                'academic.view', 'academic.manage',
                'academic-years.view', 'curricula.view', 'curricula.manage',
                'subjects.view', 'subjects.manage', 'classrooms.view', 'classrooms.manage',
                'majors.view', 'majors.manage',
                'grade-levels.view', 'grade-levels.manage',
                'schedules.view', 'schedules.manage',
                // Students & Teachers
                'students.view', 'students.update', 'teachers.view', 'teachers.update',
                // Attendance
                'attendance.view', 'attendance.manage', 'attendance.report',
                // Grades
                'grades.view', 'grades.manage',
                // Reports
                'reports.view', 'reports.generate',
                'report-cards.view', 'report-cards.generate',
                // Notifications
                'notifications.view', 'announcements.view', 'announcements.create',
            ],
        ],

        'guru' => [
            'description' => 'Guru - Teaching staff',
            'permissions' => [
                'dashboard.view',
                // Academic
                'academic.view', 'subjects.view', 'classrooms.view', 'grade-levels.view', 'schedules.view',
                // Students
                'students.view',
                // Own data
                'teachers.view-own',
                // Attendance
                'attendance.view', 'attendance.record', 'attendance.check-in', 'attendance.check-out',
                // Exams & Grades
                'exams.view', 'exams.create', 'exams.update',
                'grades.view', 'grades.input', 'grades.update',
                // Reports
                'report-cards.view',
                // Notifications
                'notifications.view', 'announcements.view',
                // Library
                'library.view', 'books.view', 'loans.view',
            ],
        ],

        'wali_kelas' => [
            'description' => 'Wali Kelas - Homeroom teacher',
            'permissions' => [
                'dashboard.view',
                // Academic
                'academic.view', 'subjects.view', 'classrooms.view', 'grade-levels.view', 'schedules.view',
                // Students
                'students.view', 'students.update',
                // Attendance
                'attendance.view', 'attendance.record', 'attendance.report',
                // Grades
                'grades.view', 'grades.input', 'grades.update',
                // Reports
                'reports.view', 'report-cards.view', 'report-cards.generate',
                // Notifications
                'notifications.view', 'announcements.view', 'announcements.create',
            ],
        ],

        'tata_usaha' => [
            'description' => 'Tata Usaha - Administrative staff',
            'permissions' => [
                'dashboard.view',
                // Students
                'students.view', 'students.create', 'students.update', 'students.enroll',
                'students.export', 'students.import',
                // Teachers
                'teachers.view', 'teachers.create', 'teachers.update',
                // Staff
                'staff.view', 'staff.create', 'staff.update',
                // Attendance
                'attendance.view', 'attendance.check-in', 'attendance.check-out',
                // Attendance settings (TU boleh atur jam absen & notifikasi)
                'settings.attendance',
                // Reports
                'reports.view', 'reports.generate',
                // Notifications
                'notifications.view', 'announcements.view',
            ],
        ],

        'bendahara' => [
            'description' => 'Bendahara - Finance staff',
            'permissions' => [
                'dashboard.view',
                // Students (view only for payment)
                'students.view',
                // Finance
                'finance.view', 'finance.manage',
                'fees.view', 'fees.manage', 'fees.generate',
                'payments.view', 'payments.create', 'payments.verify', 'payments.manage',
                'discounts.view', 'discounts.manage',
                'finance.report',
                // Reports
                'reports.view', 'reports.generate', 'reports.export',
                // Notifications
                'notifications.view',
            ],
        ],

        'pustakawan' => [
            'description' => 'Pustakawan - Library staff',
            'permissions' => [
                'dashboard.view',
                // Library
                'library.view', 'library.manage',
                'books.view', 'books.create', 'books.update', 'books.delete',
                'loans.view', 'loans.create', 'loans.return', 'loans.manage',
                // Students (for member lookup)
                'students.view',
                // Notifications
                'notifications.view',
            ],
        ],

        'siswa' => [
            'description' => 'Siswa - Student',
            'permissions' => [
                'dashboard.view',
                // Own data
                'students.view-own',
                // Academic
                'schedules.view',
                // Attendance
                'attendance.view-own',
                // Grades
                'grades.view-own',
                // Finance
                'finance.view-own',
                // Library
                'library.view', 'books.view',
                // Reports
                'report-cards.view',
                // Notifications
                'notifications.view', 'announcements.view',
            ],
        ],

        'orang_tua' => [
            'description' => 'Orang Tua - Parent/Guardian',
            'permissions' => [
                'dashboard.view',
                // View child data
                'students.view-own',
                'attendance.view-own',
                'grades.view-own',
                'finance.view-own',
                // Reports
                'report-cards.view',
                // Notifications
                'notifications.view', 'announcements.view',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = Permission::all();

        foreach ($this->roles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'description' => $roleData['description'],
                    'is_system' => true,
                ]
            );

            // Assign permissions
            if ($roleData['permissions'] === '*') {
                // All permissions
                $role->syncPermissions($allPermissions);
            } else {
                $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
                $role->syncPermissions($permissions);
            }
        }

        $this->command->info('Created ' . Role::count() . ' roles with permissions.');
    }
}
