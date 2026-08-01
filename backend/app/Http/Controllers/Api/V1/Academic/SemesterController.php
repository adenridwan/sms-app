<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Concerns\ChecksReferentialUsage;
use App\Http\Resources\SemesterResource;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CATATAN kolom: di database kolomnya bernama `number`, sementara kontrak API
 * (dan tipe TS di frontend) memakai `semester_number`. Controller ini yang
 * memetakan keduanya. Sebelumnya `semester_number` dikirim apa adanya ke
 * Eloquent — tidak ada di $fillable sehingga dibuang, lalu insert gagal karena
 * kolom `number` NOT NULL, dan SemesterResource selalu mengembalikan null.
 */
class SemesterController extends ApiController
{
    use ChecksReferentialUsage;

    /** Kolom yang boleh dipakai mengurutkan (jangan pernah orderBy dari input mentah). */
    private const SORTABLE = ['name', 'number', 'start_date', 'end_date', 'is_active', 'created_at'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Semester::with('academicYear')
            ->when($request->academic_year_id, fn ($q, $yearId) => $q->where('academic_year_id', $yearId))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'start_date');
        $sortField = in_array($sortField, self::SORTABLE, true) ? $sortField : 'start_date';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $semesters = $query->paginate($perPage);

        return $this->success(SemesterResource::collection($semesters)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:50'],
            'semester_number' => ['required', 'integer', 'in:1,2'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        // Dicek lewat model (bukan rule `exists:`) supaya ikut global scope
        // tenant — tahun ajaran milik sekolah lain tidak boleh dirujuk.
        if (! AcademicYear::where('id', $data['academic_year_id'])->exists()) {
            return $this->validationError(['academic_year_id' => ['Tahun ajaran tidak ditemukan.']]);
        }

        $number = $data['semester_number'];

        $exists = Semester::where('academic_year_id', $data['academic_year_id'])
            ->where('number', $number)
            ->exists();

        if ($exists) {
            return $this->error('Semester ' . $number . ' sudah ada untuk tahun ajaran ini', 422);
        }

        $isActive = (bool) ($data['is_active'] ?? false);

        $semester = DB::transaction(function () use ($data, $number, $isActive) {
            if ($isActive) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }

            return Semester::create([
                'academic_year_id' => $data['academic_year_id'],
                'name' => $data['name'],
                'number' => $number,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $isActive,
            ]);
        });

        $semester->load('academicYear');

        return $this->success(
            new SemesterResource($semester),
            'Semester berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Semester $semester): JsonResponse
    {
        $semester->load('academicYear');

        return $this->success(new SemesterResource($semester));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Semester $semester): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        // Lihat catatan yang sama di AcademicYearController::update — tanpa ini
        // update parsial (hanya salah satu tanggal) selalu gagal `after:`.
        $request->merge([
            'start_date' => $request->input('start_date', $semester->start_date?->toDateString()),
            'end_date' => $request->input('end_date', $semester->end_date?->toDateString()),
        ]);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'semester_number' => ['sometimes', 'integer', 'in:1,2'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        $number = $data['semester_number'] ?? $semester->number;
        unset($data['semester_number']);

        if ($number !== $semester->number) {
            $exists = Semester::where('academic_year_id', $semester->academic_year_id)
                ->where('number', $number)
                ->where('id', '!=', $semester->id)
                ->exists();

            if ($exists) {
                return $this->error('Semester ' . $number . ' sudah ada untuk tahun ajaran ini', 422);
            }
        }

        $data['number'] = $number;

        DB::transaction(function () use ($data, $semester) {
            if (($data['is_active'] ?? false) && ! $semester->is_active) {
                Semester::where('is_active', true)
                    ->where('id', '!=', $semester->id)
                    ->update(['is_active' => false]);
            }

            $semester->update($data);
        });

        $semester->load('academicYear');

        return $this->success(new SemesterResource($semester), 'Semester berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Semester $semester): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        if ($semester->is_active) {
            return $this->error('Semester aktif tidak dapat dihapus', 422);
        }

        $usedBy = $this->usedBy(self::SEMESTER_DEPENDENTS, 'semester_id', $semester->id);

        if ($usedBy !== []) {
            return $this->error(
                'Semester tidak dapat dihapus karena masih digunakan oleh data '
                    . implode(', ', $usedBy) . '.',
                422
            );
        }

        // Hapus permanen dengan alasan yang sama seperti tahun ajaran: tidak ada
        // data yang merujuk, sementara baris soft-deleted tetap memegang unique
        // index [academic_year_id, number] sehingga nomor semesternya tidak bisa
        // dipakai ulang.
        $semester->forceDelete();

        return $this->success(null, 'Semester berhasil dihapus');
    }

    /**
     * Set semester as active.
     *
     * Dulu bernama `setActive` dan tidak punya route sama sekali; frontend
     * memanggil /semesters/{id}/set-active yang tidak pernah ada.
     */
    public function activate(Request $request, Semester $semester): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        DB::transaction(function () use ($semester) {
            Semester::where('is_active', true)->update(['is_active' => false]);
            $semester->update(['is_active' => true]);

            // Tahun ajaran induknya ikut diaktifkan — semester aktif yang
            // induknya nonaktif membuat jalur absensi kehilangan konteks.
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $semester->academicYear->update(['is_active' => true]);
        });

        $semester->load('academicYear');

        return $this->success(new SemesterResource($semester), 'Semester berhasil diaktifkan');
    }
}
