<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
        return Inertia::render('students/Index', [
            'students' => [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => 15,
                    'to' => 0,
                    'total' => 0,
                ],
            ],
            'filters' => request()->only(['search', 'status', 'gender']),
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
}
