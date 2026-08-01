<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Concerns\ChecksReferentialUsage;
use App\Http\Resources\AcademicYearResource;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CATATAN parameter route: apiResource('years') menghasilkan segmen `{year}`,
 * jadi type-hint di bawah HARUS bernama `$year` agar route-model binding jalan.
 * Sebelumnya bernama `$academicYear` sehingga binding tidak pernah cocok dan
 * Laravel menyuntikkan model kosong — show/update/destroy diam-diam salah.
 */
class AcademicYearController extends ApiController
{
    use ChecksReferentialUsage;

    /** Kolom yang boleh dipakai mengurutkan (jangan pernah orderBy dari input mentah). */
    private const SORTABLE = ['name', 'start_date', 'end_date', 'is_active', 'created_at'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AcademicYear::withCount('classRooms')
            ->with(['semesters' => fn ($q) => $q->orderBy('number')])
            ->when($request->search, fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'start_date');
        $sortField = in_array($sortField, self::SORTABLE, true) ? $sortField : 'start_date';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $academicYears = $query->paginate($perPage);

        return $this->success(AcademicYearResource::collection($academicYears)->response()->getData(true));
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
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            // Bila true, langsung dibuatkan Semester Ganjil & Genap (lihat
            // createDefaultSemesters) supaya admin tidak perlu dua langkah.
            'create_semesters' => ['boolean'],
        ]);

        // Unique HARUS dibatasi per tenant: unique index DB-nya [tenant_id, name],
        // sementara rule `unique:academic_years,name` polos memblokir sekolah lain
        // yang kebetulan memakai nama tahun ajaran sama.
        if (AcademicYear::where('name', $data['name'])->exists()) {
            return $this->error('Tahun ajaran dengan nama ini sudah ada.', 422, [
                'name' => ['Tahun ajaran dengan nama ini sudah ada.'],
            ]);
        }

        // Baris yang di-soft delete tetap memegang slot unique index, sehingga
        // nama tahun ajaran yang pernah dihapus tidak akan pernah bisa dipakai
        // lagi (insert-nya gagal sebagai 500). Karena penghapusan hanya diizinkan
        // saat tidak ada satu pun data pemakai, arsipnya tidak menyimpan apa pun
        // yang berharga — bersihkan permanen supaya namanya bebas lagi.
        $this->purgeTrashedByName($data['name']);

        $createSemesters = (bool) ($data['create_semesters'] ?? false);
        unset($data['create_semesters']);
        $data['is_active'] = $data['is_active'] ?? false;

        $academicYear = DB::transaction(function () use ($data, $createSemesters) {
            if ($data['is_active']) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
            }

            $academicYear = AcademicYear::create($data);

            if ($createSemesters) {
                $this->createDefaultSemesters($academicYear);
            }

            return $academicYear;
        });

        $academicYear->load(['semesters' => fn ($q) => $q->orderBy('number')]);

        return $this->success(
            new AcademicYearResource($academicYear),
            'Tahun ajaran berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicYear $year): JsonResponse
    {
        $year->load(['semesters' => fn ($q) => $q->orderBy('number')]);
        $year->loadCount('classRooms');

        return $this->success(new AcademicYearResource($year));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AcademicYear $year): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        // Isi nilai saat ini untuk field tanggal yang tidak dikirim: rule
        // `after:start_date` tidak bisa dievaluasi kalau start_date absen,
        // sehingga update parsial (hanya end_date) selalu gagal validasi.
        $request->merge([
            'start_date' => $request->input('start_date', $year->start_date?->toDateString()),
            'end_date' => $request->input('end_date', $year->end_date?->toDateString()),
        ]);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['name'])) {
            if (AcademicYear::where('name', $data['name'])->where('id', '!=', $year->id)->exists()) {
                return $this->error('Tahun ajaran dengan nama ini sudah ada.', 422, [
                    'name' => ['Tahun ajaran dengan nama ini sudah ada.'],
                ]);
            }

            $this->purgeTrashedByName($data['name'], $year->id);
        }

        DB::transaction(function () use ($data, $year) {
            if (($data['is_active'] ?? false) && ! $year->is_active) {
                AcademicYear::where('is_active', true)
                    ->where('id', '!=', $year->id)
                    ->update(['is_active' => false]);
            }

            $year->update($data);
        });

        $year->load(['semesters' => fn ($q) => $q->orderBy('number')]);

        return $this->success(new AcademicYearResource($year), 'Tahun ajaran berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, AcademicYear $year): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        if ($year->is_active) {
            return $this->error('Tahun ajaran aktif tidak dapat dihapus', 422);
        }

        // Semester ikut terhapus bersama tahun ajaran, jadi data yang menempel
        // ke semester (mis. nilai — yang tidak punya kolom academic_year_id)
        // harus ikut menahan penghapusan.
        $usedBy = array_values(array_unique(array_merge(
            $this->usedBy(self::ACADEMIC_YEAR_DEPENDENTS, 'academic_year_id', $year->id),
            $this->usedBy(
                self::SEMESTER_DEPENDENTS,
                'semester_id',
                $year->semesters()->pluck('id')->all()
            ),
        )));

        if ($usedBy !== []) {
            return $this->error(
                'Tahun ajaran tidak dapat dihapus karena masih digunakan oleh data '
                    . implode(', ', $usedBy) . '.',
                422
            );
        }

        // Hapus permanen: baris ini sudah dipastikan tidak dirujuk data mana pun,
        // jadi tidak ada yang perlu diarsipkan — sementara baris soft-deleted
        // tetap memegang slot unique index [tenant_id, name] dan membuat nama
        // tahun ajaran tidak bisa dipakai ulang.
        DB::transaction(function () use ($year) {
            $year->semesters()->forceDelete();
            $year->forceDelete();
        });

        return $this->success(null, 'Tahun ajaran berhasil dihapus');
    }

    /**
     * Set academic year as active.
     *
     * Nama method dulu `setActive` sementara route menunjuk `activate`,
     * sehingga endpoint ini selalu 500 saat dipanggil.
     */
    public function activate(Request $request, AcademicYear $year): JsonResponse
    {
        abort_unless($request->user()->can('academic-years.manage'), 403);

        DB::transaction(function () use ($year) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $year->update(['is_active' => true]);

            // Jalur absensi menstempel semester_id dari `activeSemester` tahun
            // aktif (AttendanceScanService, LeaveApprovalService). Kalau tahun
            // baru diaktifkan tanpa semester aktif, semester_id jadi null —
            // jadi semester terkecil tahun ini ikut diaktifkan. Semester aktif
            // milik tahun lama selalu dilepas supaya tidak ada semester aktif
            // yang menggantung di luar tahun ajaran aktif.
            Semester::where('is_active', true)->update(['is_active' => false]);

            $year->semesters()->orderBy('number')->first()?->update(['is_active' => true]);
        });

        $year->load(['semesters' => fn ($q) => $q->orderBy('number')]);

        return $this->success(new AcademicYearResource($year), 'Tahun ajaran berhasil diaktifkan');
    }

    /**
     * Buang permanen tahun ajaran ber-nama sama yang tinggal arsip (soft deleted).
     *
     * Hanya menyentuh baris yang sudah dihapus, dan hanya bila benar-benar tidak
     * dirujuk data apa pun — arsip peninggalan versi lama yang masih memegang
     * data tetap dibiarkan (create-nya nanti gagal di unique index, bukan diam-diam
     * menghapus sesuatu yang masih terpakai).
     */
    private function purgeTrashedByName(string $name, ?string $exceptId = null): void
    {
        $trashed = AcademicYear::onlyTrashed()
            ->where('name', $name)
            ->when($exceptId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get();

        foreach ($trashed as $year) {
            $semesterIds = $year->semesters()->withTrashed()->pluck('id')->all();

            $usedBy = array_merge(
                $this->usedBy(self::ACADEMIC_YEAR_DEPENDENTS, 'academic_year_id', $year->id),
                $this->usedBy(self::SEMESTER_DEPENDENTS, 'semester_id', $semesterIds),
            );

            if ($usedBy === []) {
                $year->semesters()->withTrashed()->forceDelete();
                $year->forceDelete();
            }
        }
    }

    /**
     * Buat Semester Ganjil & Genap untuk satu tahun ajaran.
     *
     * Pembagian mengikuti konvensi Indonesia (Ganjil berakhir 31 Desember).
     * Kalau tanggal itu di luar rentang tahun ajaran — sekolah dengan kalender
     * tidak standar — periode dibagi dua sama panjang.
     */
    private function createDefaultSemesters(AcademicYear $academicYear): void
    {
        $start = CarbonImmutable::parse($academicYear->start_date);
        $end = CarbonImmutable::parse($academicYear->end_date);

        $ganjilEnd = CarbonImmutable::create($start->year, 12, 31);

        if ($ganjilEnd <= $start || $ganjilEnd >= $end) {
            $ganjilEnd = $start->addDays(intdiv($start->diffInDays($end), 2));
        }

        // Semester aktif bersifat tunggal per tenant; kosongkan dulu bila tahun
        // ini dibuat langsung sebagai tahun aktif.
        if ($academicYear->is_active) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester Ganjil',
            'number' => 1,
            'start_date' => $start->toDateString(),
            'end_date' => $ganjilEnd->toDateString(),
            'is_active' => $academicYear->is_active,
        ]);

        Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester Genap',
            'number' => 2,
            'start_date' => $ganjilEnd->addDay()->toDateString(),
            'end_date' => $end->toDateString(),
            'is_active' => false,
        ]);
    }
}
