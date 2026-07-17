<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard');
    }

    /**
     * Display students list.
     */
    public function students(): Response
    {
        $filters = request()->only(['search', 'status', 'gender']);

        $students = Student::with(['user.profile', 'currentClass'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nis', 'ilike', "%{$search}%")
                        ->orWhere('nisn', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('username', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%"))
                        ->orWhereHas('user.profile', fn ($p) => $p
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['gender'] ?? null, fn ($q, $gender) => $q->whereHas(
                'user.profile',
                fn ($p) => $p->where('gender', $gender)
            ))
            ->orderBy('nis')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('students/Index', [
            'students' => [
                'data' => StudentResource::collection($students->items())->resolve(),
                'meta' => [
                    'current_page' => $students->currentPage(),
                    'from' => $students->firstItem() ?? 0,
                    'last_page' => $students->lastPage(),
                    'per_page' => $students->perPage(),
                    'to' => $students->lastItem() ?? 0,
                    'total' => $students->total(),
                ],
                'links' => [
                    'prev' => $students->previousPageUrl(),
                    'next' => $students->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Display create student form.
     */
    public function createStudent(): Response
    {
        return Inertia::render('students/Create');
    }

    /**
     * Display teachers list.
     */
    public function teachers(): Response
    {
        return Inertia::render('teachers/Index', [
            'teachers' => [
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ],
        ]);
    }

    /**
     * Display login page.
     */
    public function login(): Response
    {
        return Inertia::render('auth/Login');
    }

    /**
     * Display register page.
     */
    public function register(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Display academic years list.
     */
    public function academicYears(): Response
    {
        return Inertia::render('academic/Years');
    }

    /**
     * Display class rooms list.
     */
    public function classRooms(): Response
    {
        return Inertia::render('master/ClassRooms');
    }

    /**
     * Display subjects list.
     */
    public function subjects(): Response
    {
        return Inertia::render('master/Subjects');
    }

    /**
     * Display payments list.
     */
    public function payments(): Response
    {
        return Inertia::render('finance/Payments');
    }

    /**
     * Display attendance page.
     */
    public function attendance(): Response
    {
        return Inertia::render('attendance/Index');
    }

    /**
     * Display schedules page.
     */
    public function schedules(): Response
    {
        return Inertia::render('academic/Schedules');
    }

    /**
     * Display users list.
     */
    public function users(): Response
    {
        return Inertia::render('settings/Users');
    }

    /**
     * Display settings page.
     */
    public function settings(): Response
    {
        return Inertia::render('settings/General');
    }

    /**
     * Display class rooms settings page.
     */
    public function settingsClassRooms(): Response
    {
        return Inertia::render('settings/ClassRooms');
    }

    /**
     * Display majors settings page.
     */
    public function settingsMajors(): Response
    {
        return Inertia::render('settings/Majors');
    }
}
