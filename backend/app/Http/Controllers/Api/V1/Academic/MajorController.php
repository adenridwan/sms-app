<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Exports\Academic\MajorsExport;
use App\Exports\Academic\MajorsTemplateExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\MajorResource;
use App\Imports\Academic\MajorsImport;
use App\Infrastructure\Persistence\Eloquent\Academic\Major;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MajorController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Major::withCount('classrooms')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = in_array($request->get('sort'), ['name', 'code', 'created_at'], true)
            ? $request->get('sort')
            : 'code';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $majors = $query->paginate($perPage);

        return $this->success(MajorResource::collection($majors)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if (Major::where('code', $data['code'])->exists()) {
            return $this->validationError(['code' => ['Kode jurusan sudah digunakan.']]);
        }

        $major = Major::create($data);

        return $this->success(new MajorResource($major), 'Jurusan berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Major $major): JsonResponse
    {
        $major->loadCount('classrooms');

        return $this->success(new MajorResource($major));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Major $major): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['code']) && Major::where('code', $data['code'])->where('id', '!=', $major->id)->exists()) {
            return $this->validationError(['code' => ['Kode jurusan sudah digunakan.']]);
        }

        $major->update($data);

        return $this->success(new MajorResource($major), 'Jurusan berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Major $major): JsonResponse
    {
        if ($major->classrooms()->exists()) {
            return $this->error('Jurusan tidak dapat dihapus karena masih digunakan oleh kelas', 422);
        }

        $major->delete();

        return $this->success(null, 'Jurusan berhasil dihapus');
    }

    /**
     * Export majors to an Excel file.
     */
    public function export(): BinaryFileResponse
    {
        return Excel::download(new MajorsExport(), 'jurusan.xlsx');
    }

    /**
     * Download the default import template.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new MajorsTemplateExport(), 'template-import-jurusan.xlsx');
    }

    /**
     * Import majors from an uploaded file (upsert by code).
     */
    public function import(Request $request): JsonResponse
    {
        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new MajorsImport();
        Excel::import($import, $request->file('file'));

        return $this->success([
            'created' => $import->created,
            'updated' => $import->updated,
            'errors' => $import->errors,
        ], 'Import jurusan selesai.');
    }
}
