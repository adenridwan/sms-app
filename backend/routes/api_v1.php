<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

// ===========================================
// Public Routes (No Auth Required)
// ===========================================
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('login');

    Route::post('/register', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'register'])
        ->middleware('throttle:auth')
        ->name('register');

    Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'forgot'])
        ->middleware('throttle:auth')
        ->name('password.forgot');

    Route::post('/reset-password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'reset'])
        ->middleware('throttle:auth')
        ->name('password.reset');
});

// ===========================================
// Protected Routes (Auth Required)
// ===========================================
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me'])->name('me');
        Route::put('/profile', [\App\Http\Controllers\Api\V1\Auth\ProfileController::class, 'update'])->name('profile.update');
        // Nama method controller adalah `change` — route dulu salah rujuk ke `update` (selalu 500)
        Route::put('/password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'change'])->name('password.update');
    });

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\V1\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/class/{classroom}', [\App\Http\Controllers\Api\V1\DashboardController::class, 'classStats'])
        ->whereUuid('classroom')
        ->name('dashboard.class');

    // ===========================================
    // Academic Module
    // ===========================================
    Route::prefix('academic')->name('academic.')->group(function () {
        // Academic Years
        Route::apiResource('years', \App\Http\Controllers\Api\V1\Academic\AcademicYearController::class);
        Route::post('years/{year}/activate', [\App\Http\Controllers\Api\V1\Academic\AcademicYearController::class, 'activate'])->name('years.activate');

        // Semesters
        Route::apiResource('semesters', \App\Http\Controllers\Api\V1\Academic\SemesterController::class);
        Route::post('semesters/{semester}/activate', [\App\Http\Controllers\Api\V1\Academic\SemesterController::class, 'activate'])->name('semesters.activate');

        // Curricula
        Route::apiResource('curricula', \App\Http\Controllers\Api\V1\Academic\CurriculumController::class);

        // Grade Levels
        Route::apiResource('grade-levels', \App\Http\Controllers\Api\V1\Academic\GradeLevelController::class);

        // Majors (static routes must be registered before apiResource)
        Route::get('majors/template', [\App\Http\Controllers\Api\V1\Academic\MajorController::class, 'template'])->name('majors.template');
        Route::get('majors/export', [\App\Http\Controllers\Api\V1\Academic\MajorController::class, 'export'])->name('majors.export');
        Route::post('majors/import', [\App\Http\Controllers\Api\V1\Academic\MajorController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('majors.import');
        Route::apiResource('majors', \App\Http\Controllers\Api\V1\Academic\MajorController::class);

        // Classrooms (static routes must be registered before apiResource)
        Route::get('classrooms/template', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'template'])->name('classrooms.template');
        Route::get('classrooms/export', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'export'])->name('classrooms.export');
        Route::post('classrooms/import', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('classrooms.import');
        Route::apiResource('classrooms', \App\Http\Controllers\Api\V1\Academic\ClassroomController::class);
        Route::get('classrooms/{classroom}/students', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'students'])->name('classrooms.students');
        Route::get('classrooms/{classroom}/schedule', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'schedule'])->name('classrooms.schedule');
        // Guru pengampu (Fase G3, TEACHER-MODULE-PLAN.md) — penempatan manual per tahun ajaran
        Route::get('classrooms/{classroom}/teachers', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'teachers'])->name('classrooms.teachers');
        Route::put('classrooms/{classroom}/teachers', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'syncTeachers'])->name('classrooms.teachers.sync');

        // Subjects
        Route::apiResource('subjects', \App\Http\Controllers\Api\V1\Academic\SubjectController::class);

        // Schedules
        Route::apiResource('schedules', \App\Http\Controllers\Api\V1\Academic\ScheduleController::class);
        Route::post('schedules/generate', [\App\Http\Controllers\Api\V1\Academic\ScheduleController::class, 'generate'])->name('schedules.generate');

        // Time Slots
        Route::apiResource('time-slots', \App\Http\Controllers\Api\V1\Academic\TimeSlotController::class);
    });

    // ===========================================
    // Student Module
    // ===========================================
    Route::prefix('students')->name('students.')->group(function () {
        // Rute statis wajib didaftarkan sebelum apiResource, kalau tidak
        // "export"/"template"/"import" akan tertangkap sebagai {student}.
        Route::get('export', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'export'])->name('export');
        Route::get('template', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'template'])->name('template');
        Route::post('import', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('import');
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Student\StudentController::class)->parameter('', 'student');
        Route::get('{student}/guardians', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'guardians'])->name('guardians');
        Route::get('{student}/enrollments', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'enrollments'])->name('enrollments');
        Route::get('{student}/grades', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'grades'])->name('grades');
        Route::get('{student}/attendance', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'attendance'])->name('attendance');
        Route::get('{student}/fees', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'fees'])->name('fees');
        Route::get('{student}/achievements', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'achievements'])->name('achievements');
        Route::post('{student}/enroll', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'store'])->name('enroll');
        Route::post('{student}/photo', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'uploadPhoto'])->name('photo.upload');
        Route::delete('{student}/photo', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'deletePhoto'])->name('photo.delete');

        // Guardians
        Route::apiResource('guardians', \App\Http\Controllers\Api\V1\Student\GuardianController::class);
    });

    // ===========================================
    // Teacher Module
    // ===========================================
    Route::prefix('teachers')->name('teachers.')->group(function () {
        // Rute statis wajib didaftarkan sebelum apiResource, kalau tidak
        // "export"/"template"/"import" akan tertangkap sebagai {teacher}.
        Route::get('export', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'export'])->name('export');
        Route::get('template', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'template'])->name('template');
        Route::post('import', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('import');
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Teacher\TeacherController::class)->parameter('', 'teacher');
        // Penempatan kelas (menu Kelas) & kompetensi mapel (menu Mata Pelajaran)
        // sengaja tidak dikelola dari sini — lihat TEACHER-MODULE-PLAN.md §2.
        // Ringkasan penugasan (read-only) untuk halaman Detail Guru — Fase G2.
        Route::get('{teacher}/assignment', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'assignment'])->name('assignment');
        Route::post('{teacher}/photo', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'uploadPhoto'])->name('photo.upload');
        Route::delete('{teacher}/photo', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'deletePhoto'])->name('photo.delete');
        Route::post('{teacher}/documents', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'uploadDocument'])->name('documents.upload');
        Route::delete('{teacher}/documents/{media}', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'deleteDocument'])->name('documents.delete');
    });

    // ===========================================
    // Staff Module
    // ===========================================
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Staff\StaffController::class)->parameter('', 'staff');
        Route::apiResource('departments', \App\Http\Controllers\Api\V1\Staff\DepartmentController::class);
        Route::apiResource('positions', \App\Http\Controllers\Api\V1\Staff\PositionController::class);
        Route::apiResource('leave-requests', \App\Http\Controllers\Api\V1\Staff\LeaveRequestController::class);
        Route::post('leave-requests/{leaveRequest}/approve', [\App\Http\Controllers\Api\V1\Staff\LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('leave-requests/{leaveRequest}/reject', [\App\Http\Controllers\Api\V1\Staff\LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    });

    // ===========================================
    // Attendance Module
    // ===========================================
    Route::prefix('attendance')->name('attendance.')->group(function () {
        // Student Attendance
        Route::get('students', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'index'])->name('students.index');
        Route::get('students/daily', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'daily'])->name('students.daily');
        Route::post('students/bulk', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'storeBulk'])->name('students.bulk');
        Route::post('students/notify-daily', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'notifyDaily'])->name('students.notify-daily');
        Route::put('students/{attendance}', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'update'])->name('students.update');
        Route::get('students/summary', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'summary'])->name('students.summary');
        Route::get('students/consecutive-absences', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'consecutiveAbsences'])->name('students.consecutive-absences');
        Route::get('students/top-late', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'topLate'])->name('students.top-late');

        // Teacher/Employee Attendance
        Route::get('teachers', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'index'])->name('teachers.index');
        Route::get('teachers/daily', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'daily'])->name('teachers.daily');
        Route::put('teachers/{attendance}', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'update'])->name('teachers.update');
        Route::get('teachers/summary', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'summary'])->name('teachers.summary');

        // Leave Permissions
        Route::apiResource('permissions', \App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class);
        Route::post('permissions/{permission}/approve', [\App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class, 'approve'])->name('permissions.approve');
        Route::post('permissions/{permission}/reject', [\App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class, 'reject'])->name('permissions.reject');
        Route::get('permissions-pending-count', [\App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class, 'pendingCount'])->name('permissions.pending-count');

        // Holidays
        Route::apiResource('holidays', \App\Http\Controllers\Api\V1\Attendance\HolidayController::class);
        Route::post('holidays/generate-weekends', [\App\Http\Controllers\Api\V1\Attendance\HolidayController::class, 'generateWeekends'])->name('holidays.generate-weekends');
        Route::post('holidays/bulk-delete', [\App\Http\Controllers\Api\V1\Attendance\HolidayController::class, 'bulkDelete'])->name('holidays.bulk-delete');
        Route::post('holidays/check', [\App\Http\Controllers\Api\V1\Attendance\HolidayController::class, 'check'])->name('holidays.check');

        // QR Codes
        Route::get('qr/students/{student}', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'student'])->name('qr.student');
        Route::post('qr/students/{student}/regenerate', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'regenerateStudent'])->name('qr.student.regenerate');
        Route::get('qr/students/bulk', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'bulkStudents'])->name('qr.students.bulk');
        Route::get('qr/students/{student}/download', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'downloadStudent'])->name('qr.student.download');
        Route::get('qr/teachers/{teacher}', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'teacher'])->name('qr.teacher');
        Route::post('qr/teachers/{teacher}/regenerate', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'regenerateTeacher'])->name('qr.teacher.regenerate');
        Route::get('qr/teachers/bulk', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'bulkTeachers'])->name('qr.teachers.bulk');
        Route::get('qr/teachers/{teacher}/download', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'downloadTeacher'])->name('qr.teacher.download');

        // Export QR/RFID untuk pembuatan kartu (Excel / ZIP gambar / PDF)
        Route::get('qr/export', [\App\Http\Controllers\Api\V1\Attendance\QrExportController::class, 'export'])->name('qr.export');
        Route::post('qr/generate-rfid', [\App\Http\Controllers\Api\V1\Attendance\QrExportController::class, 'generateRfid'])->name('qr.generate-rfid');

        // RFID
        Route::put('rfid/students/{student}', [\App\Http\Controllers\Api\V1\Attendance\RfidController::class, 'updateStudent'])->name('rfid.students.update');
        Route::put('rfid/teachers/{teacher}', [\App\Http\Controllers\Api\V1\Attendance\RfidController::class, 'updateTeacher'])->name('rfid.teachers.update');
        // Kode RFID terbitan sistem (kartu writable) — lihat RfidCodeGenerator
        Route::post('rfid/teachers/{teacher}/generate', [\App\Http\Controllers\Api\V1\Attendance\RfidController::class, 'generateTeacher'])->name('rfid.teachers.generate');

        // Card Templates (Fase 5 — editor kartu ID drag-and-drop)
        Route::get('card-templates/{type}', [\App\Http\Controllers\Api\V1\Attendance\CardTemplateController::class, 'show'])->name('card-templates.show');
        Route::put('card-templates/{type}', [\App\Http\Controllers\Api\V1\Attendance\CardTemplateController::class, 'update'])->name('card-templates.update');
        Route::delete('card-templates/{type}', [\App\Http\Controllers\Api\V1\Attendance\CardTemplateController::class, 'reset'])->name('card-templates.reset');

        // Reports
        Route::get('reports/monthly', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('reports/pdf', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'downloadPdf'])->name('reports.pdf');
        Route::get('reports/excel', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'downloadExcel'])->name('reports.excel');
        Route::get('reports/weekly-trend', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'weeklyTrend'])->name('reports.weekly-trend');

        // Settings — hanya admin/TU/super admin (permission settings.attendance).
        // Sebelumnya hanya di-guard auth:sanctum sehingga guru/siswa bisa ubah
        // jam absen & kredensial WA/Telegram lewat URL langsung (GAP-1).
        Route::middleware('permission:settings.attendance')->group(function () {
            Route::get('settings', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'show'])->name('settings.show');
            Route::put('settings', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'update'])->name('settings.update');
            Route::post('settings/test-whatsapp', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'testWhatsApp'])->name('settings.test-whatsapp');
            Route::post('settings/test-telegram', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'testTelegram'])->name('settings.test-telegram');
            Route::post('settings/test-email', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'testEmail'])->name('settings.test-email');
            Route::get('settings/telegram-bot-info', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'telegramBotInfo'])->name('settings.telegram-bot-info');
        });
    });

    // ===========================================
    // Scanner Module (for QR/RFID scanning)
    // ===========================================
    Route::prefix('scan')->name('scan.')->group(function () {
        Route::get('bootstrap', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'bootstrap'])->name('bootstrap');
        Route::post('/', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'scan'])->name('process');
        Route::post('sync-offline', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'syncOffline'])->name('sync-offline');
        Route::post('lookup', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'lookup'])->name('lookup');
    });

    // ===========================================
    // Exam & Grade Module
    // ===========================================
    Route::prefix('exams')->name('exams.')->group(function () {
        Route::apiResource('types', \App\Http\Controllers\Api\V1\Exam\ExamTypeController::class);
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Exam\ExamController::class)->parameter('', 'exam');
        Route::get('{exam}/scores', [\App\Http\Controllers\Api\V1\Exam\ExamController::class, 'scores'])->name('scores');
        Route::post('{exam}/scores', [\App\Http\Controllers\Api\V1\Exam\ScoreController::class, 'store'])->name('scores.store');
        Route::post('{exam}/scores/bulk', [\App\Http\Controllers\Api\V1\Exam\ScoreController::class, 'storeBulk'])->name('scores.bulk');
    });

    Route::prefix('grades')->name('grades.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'index'])->name('index');
        Route::get('student/{student}', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'byStudent'])->name('by-student');
        Route::get('classroom/{classroom}', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'byClassroom'])->name('by-classroom');
        Route::post('finalize', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'finalize'])->name('finalize');
    });

    // ===========================================
    // Finance Module
    // ===========================================
    Route::prefix('finance')->name('finance.')->group(function () {
        // Fee Types
        Route::apiResource('fee-types', \App\Http\Controllers\Api\V1\Finance\FeeTypeController::class);

        // Fee Structures
        Route::apiResource('fee-structures', \App\Http\Controllers\Api\V1\Finance\FeeStructureController::class);

        // Student Fees
        Route::get('fees', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'index'])->name('fees.index');
        Route::post('fees/generate', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'generate'])->name('fees.generate');
        Route::get('fees/{fee}', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'show'])->name('fees.show');

        // Payments
        Route::apiResource('payments', \App\Http\Controllers\Api\V1\Finance\PaymentController::class);
        Route::post('payments/{payment}/verify', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'verify'])->name('payments.verify');
        Route::get('payments/{payment}/receipt', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'receipt'])->name('payments.receipt');

        // Payment Methods
        Route::apiResource('payment-methods', \App\Http\Controllers\Api\V1\Finance\PaymentMethodController::class);

        // Discounts
        Route::apiResource('discounts', \App\Http\Controllers\Api\V1\Finance\DiscountController::class);

        // Reports
        Route::get('reports/summary', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'summary'])->name('reports.summary');
        Route::get('reports/monthly', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('reports/outstanding', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'outstanding'])->name('reports.outstanding');
    });

    // ===========================================
    // Library Module
    // ===========================================
    Route::prefix('library')->name('library.')->group(function () {
        Route::apiResource('categories', \App\Http\Controllers\Api\V1\Library\BookCategoryController::class);
        Route::apiResource('books', \App\Http\Controllers\Api\V1\Library\BookController::class);
        Route::get('books/{book}/copies', [\App\Http\Controllers\Api\V1\Library\BookController::class, 'copies'])->name('books.copies');

        Route::apiResource('members', \App\Http\Controllers\Api\V1\Library\MemberController::class);

        Route::apiResource('loans', \App\Http\Controllers\Api\V1\Library\LoanController::class);
        Route::post('loans/{loan}/return', [\App\Http\Controllers\Api\V1\Library\LoanController::class, 'returnBook'])->name('loans.return');
        Route::post('loans/{loan}/extend', [\App\Http\Controllers\Api\V1\Library\LoanController::class, 'extend'])->name('loans.extend');

        Route::apiResource('reservations', \App\Http\Controllers\Api\V1\Library\ReservationController::class);

        Route::get('settings', [\App\Http\Controllers\Api\V1\Library\SettingController::class, 'show'])->name('settings.show');
        Route::put('settings', [\App\Http\Controllers\Api\V1\Library\SettingController::class, 'update'])->name('settings.update');
    });

    // ===========================================
    // Report Module
    // ===========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        // Report Cards
        Route::get('report-cards', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'index'])->name('report-cards.index');
        Route::get('report-cards/{reportCard}', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'show'])->name('report-cards.show');
        Route::post('report-cards/generate', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'generate'])->name('report-cards.generate');
        Route::post('report-cards/{reportCard}/approve', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'approve'])->name('report-cards.approve');
        Route::get('report-cards/{reportCard}/pdf', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'pdf'])->name('report-cards.pdf');

        // Generated Reports
        Route::get('generated', [\App\Http\Controllers\Api\V1\Report\GeneratedReportController::class, 'index'])->name('generated.index');
        Route::post('generate', [\App\Http\Controllers\Api\V1\Report\GeneratedReportController::class, 'generate'])->name('generate');
        Route::get('download/{report}', [\App\Http\Controllers\Api\V1\Report\GeneratedReportController::class, 'download'])->name('download');
    });

    // ===========================================
    // Notification Module
    // ===========================================
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('{notification}/read', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('{notification}', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'destroy'])->name('destroy');

        // Announcements
        Route::apiResource('announcements', \App\Http\Controllers\Api\V1\Notification\AnnouncementController::class);
        Route::post('announcements/{announcement}/publish', [\App\Http\Controllers\Api\V1\Notification\AnnouncementController::class, 'publish'])->name('announcements.publish');
    });

    // ===========================================
    // Setting Module
    // ===========================================
    Route::prefix('settings')->name('settings.')->group(function () {
        // Catatan: SettingController generik (index/show/update {group}) dulu
        // dirujuk di sini tapi FILE-nya tidak pernah ada — menyebabkan
        // `php artisan route:list` crash & endpoint /settings 500. Rute mati itu
        // dihapus; yang tersisa hanya Pengaturan Menu di bawah.

        // Pengaturan Menu (visibilitas per-role) — admin only.
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('menu', [\App\Http\Controllers\Api\V1\Setting\MenuSettingController::class, 'show'])->name('menu.show');
            Route::put('menu', [\App\Http\Controllers\Api\V1\Setting\MenuSettingController::class, 'update'])->name('menu.update');
        });

        // Profil Sekolah (nama, NPSN, jenjang, kontak, logo). Update memakai POST
        // karena membawa berkas (multipart), samakan pola dengan upload avatar.
        Route::middleware('permission:settings.school')->group(function () {
            Route::get('school', [\App\Http\Controllers\Api\V1\Setting\SchoolProfileController::class, 'show'])->name('school.show');
            Route::post('school', [\App\Http\Controllers\Api\V1\Setting\SchoolProfileController::class, 'update'])->name('school.update');
        });

    });

    // ===========================================
    // Admin Only Routes
    // ===========================================
    Route::middleware(['role:super_admin|admin'])->prefix('admin')->name('admin.')->group(function () {
        // Users Management (super admin only)
        Route::middleware(['role:super_admin'])->group(function () {
            Route::get('users/roles', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'roles'])->name('users.roles');
            Route::get('users/student-options', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'studentOptions'])->name('users.student-options');
            Route::apiResource('users', \App\Http\Controllers\Api\V1\Admin\UserController::class);
            Route::post('users/{user}/activate', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'activate'])->name('users.activate');
            Route::post('users/{user}/deactivate', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/reset-password', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

            // Manajemen sekolah (tenant) tingkat sistem.
            Route::get('schools', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'index'])->name('schools.index');
            Route::post('schools', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'store'])->name('schools.store');
            Route::post('schools/{id}/activate', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'activate'])->name('schools.activate');
            Route::post('schools/{id}/deactivate', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'deactivate'])->name('schools.deactivate');
            Route::delete('schools/{id}', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'destroy'])->name('schools.destroy');
        });

        // Roles & Permissions
        Route::apiResource('roles', \App\Http\Controllers\Api\V1\Admin\RoleController::class);
        Route::get('permissions', [\App\Http\Controllers\Api\V1\Admin\PermissionController::class, 'index'])->name('permissions.index');

        // Audit Logs
        Route::get('audit-logs', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'show'])->name('audit-logs.show');

        // Activity Logs
        Route::get('activity-logs', [\App\Http\Controllers\Api\V1\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    // ===========================================
    // Super Admin Only Routes
    // ===========================================
    Route::middleware(['role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
        // Tenant Management
        Route::apiResource('tenants', \App\Http\Controllers\Api\V1\Tenant\TenantController::class);
        Route::post('tenants/{tenant}/activate', [\App\Http\Controllers\Api\V1\Tenant\TenantController::class, 'activate'])->name('tenants.activate');
        Route::post('tenants/{tenant}/suspend', [\App\Http\Controllers\Api\V1\Tenant\TenantController::class, 'suspend'])->name('tenants.suspend');

        // System Health
        Route::get('health', [\App\Http\Controllers\Api\V1\Admin\SystemController::class, 'health'])->name('health');
        Route::get('metrics', [\App\Http\Controllers\Api\V1\Admin\SystemController::class, 'metrics'])->name('metrics');
    });
});

// ===========================================
// Public Routes (No Auth Required) - Throttled
// ===========================================
Route::prefix('public')->name('public.')->middleware('throttle:20,1')->group(function () {
    // Leave Permission Portal
    Route::post('izin/lookup', [\App\Http\Controllers\Api\V1\Attendance\PublicLeaveController::class, 'lookup'])->name('izin.lookup');
    Route::post('izin/submit', [\App\Http\Controllers\Api\V1\Attendance\PublicLeaveController::class, 'submit'])->name('izin.submit');
    Route::post('izin/status', [\App\Http\Controllers\Api\V1\Attendance\PublicLeaveController::class, 'status'])->name('izin.status');

    // Attendance Check Portal
    Route::post('cek-kehadiran', [\App\Http\Controllers\Api\V1\Attendance\PublicAttendanceController::class, 'check'])->name('cek-kehadiran');
    Route::post('riwayat-kehadiran', [\App\Http\Controllers\Api\V1\Attendance\PublicAttendanceController::class, 'history'])->name('riwayat-kehadiran');
});
