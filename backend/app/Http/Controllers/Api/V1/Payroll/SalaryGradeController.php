<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Payroll\SalaryGradeResource;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryGradeController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalaryGrade::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            })
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'order');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $grades = $query->paginate($perPage);

        return $this->success(SalaryGradeResource::collection($grades)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:salary_grades,code'],
            'name' => ['required', 'string', 'max:100'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['order'] = $data['order'] ?? SalaryGrade::max('order') + 1;

        $grade = SalaryGrade::create($data);

        return $this->success(
            new SalaryGradeResource($grade),
            'Golongan gaji berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(SalaryGrade $salaryGrade): JsonResponse
    {
        return $this->success(new SalaryGradeResource($salaryGrade));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalaryGrade $salaryGrade): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:salary_grades,code,' . $salaryGrade->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $salaryGrade->update($data);

        return $this->success(new SalaryGradeResource($salaryGrade), 'Golongan gaji berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalaryGrade $salaryGrade): JsonResponse
    {
        if ($salaryGrade->employeeSalaries()->exists()) {
            return $this->error('Golongan gaji tidak dapat dihapus karena sudah digunakan oleh karyawan', 422);
        }

        $salaryGrade->delete();

        return $this->success(null, 'Golongan gaji berhasil dihapus');
    }
}
