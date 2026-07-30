<?php

namespace App\Imports\Academic;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Academic\GradeLevel;
use App\Infrastructure\Persistence\Eloquent\Academic\Major;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClassroomsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $gradeLevelsCreated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(
        protected AcademicYear $academicYear
    ) {}

    public function collection(Collection $rows): void
    {
        $majors = Major::all()->keyBy(fn ($major) => strtolower($major->code));

        // Pre-scan: auto-create missing grade levels for easier UX
        $this->autoCreateMissingGradeLevels($rows);

        // Refresh grade levels after auto-creation
        $gradeLevels = GradeLevel::all()->keyBy(fn ($level) => strtolower($level->code));

        foreach ($rows as $index => $row) {
            // +2: heading row + 1-based index
            $rowNumber = $index + 2;

            $code = trim((string) ($row['kode'] ?? ''));
            $name = trim((string) ($row['nama'] ?? ''));
            $gradeCode = trim((string) ($row['tingkat'] ?? ''));
            $majorCode = trim((string) ($row['jurusan'] ?? ''));

            if ($code === '' && $name === '') {
                continue; // skip empty rows
            }

            if ($code === '' || $name === '' || $gradeCode === '') {
                $this->errors[] = "Baris {$rowNumber}: kolom 'kode', 'nama', dan 'tingkat' wajib diisi.";
                continue;
            }

            $gradeLevel = $gradeLevels->get(strtolower($gradeCode));
            if (! $gradeLevel) {
                // Should not happen after auto-creation, but keep as fallback
                $this->errors[] = "Baris {$rowNumber}: tingkat '{$gradeCode}' tidak dapat dibuat.";
                continue;
            }

            $major = null;
            if ($majorCode !== '') {
                $major = $majors->get(strtolower($majorCode));
                if (! $major) {
                    $this->errors[] = "Baris {$rowNumber}: jurusan '{$majorCode}' tidak ditemukan.";
                    continue;
                }
            }

            $classroom = Classroom::withTrashed()
                ->where('academic_year_id', $this->academicYear->id)
                ->firstOrNew(['code' => $code]);
            $isNew = ! $classroom->exists;

            $capacity = (int) ($row['kapasitas'] ?? 30);

            $classroom->fill([
                'academic_year_id' => $this->academicYear->id,
                'grade_level_id' => $gradeLevel->id,
                'major_id' => $major?->id,
                'name' => $name,
                'code' => $code,
                'room' => trim((string) ($row['ruangan'] ?? '')) ?: null,
                'capacity' => $capacity > 0 ? $capacity : 30,
                'is_active' => MajorsImport::parseBoolean($row['aktif'] ?? true),
            ]);

            if ($classroom->trashed()) {
                $classroom->restore();
            }

            $classroom->save();

            $isNew ? $this->created++ : $this->updated++;
        }
    }

    /**
     * Auto-create missing grade levels from import data.
     * This simplifies UX - users don't need to manually create grade levels first.
     */
    protected function autoCreateMissingGradeLevels(Collection $rows): void
    {
        $existingCodes = GradeLevel::pluck('code')
            ->map(fn ($code) => strtolower($code))
            ->toArray();

        $newCodes = [];

        foreach ($rows as $row) {
            $gradeCode = trim((string) ($row['tingkat'] ?? ''));
            if ($gradeCode === '') {
                continue;
            }

            $lowerCode = strtolower($gradeCode);
            if (in_array($lowerCode, $existingCodes) || in_array($lowerCode, $newCodes)) {
                continue;
            }

            $newCodes[] = $lowerCode;

            // Generate a readable name based on code
            $name = $this->generateGradeLevelName($gradeCode);
            $order = $this->guessGradeLevelOrder($gradeCode);

            GradeLevel::create([
                'code' => $gradeCode,
                'name' => $name,
                'order' => $order,
                'is_active' => true,
            ]);

            $this->gradeLevelsCreated++;
        }
    }

    /**
     * Generate a readable name for grade level based on code.
     */
    protected function generateGradeLevelName(string $code): string
    {
        // Numeric codes (SD/MI: 1-6, SMP: 7-9)
        if (is_numeric($code)) {
            return 'Kelas ' . $code;
        }

        // Roman numerals for SMP (VII, VIII, IX)
        $romanMap = [
            'vii' => 'Kelas 7',
            'viii' => 'Kelas 8',
            'ix' => 'Kelas 9',
        ];
        if (isset($romanMap[strtolower($code)])) {
            return $romanMap[strtolower($code)];
        }

        // SMA/SMK Roman numerals (X, XI, XII)
        $smaMap = [
            'x' => 'Kelas 10',
            'xi' => 'Kelas 11',
            'xii' => 'Kelas 12',
        ];
        if (isset($smaMap[strtolower($code)])) {
            return $smaMap[strtolower($code)];
        }

        // Default: use code as name
        return 'Kelas ' . strtoupper($code);
    }

    /**
     * Guess sort order for grade level based on code.
     */
    protected function guessGradeLevelOrder(string $code): int
    {
        // Numeric codes
        if (is_numeric($code)) {
            return (int) $code;
        }

        // Roman numerals mapping
        $orderMap = [
            'vii' => 7,
            'viii' => 8,
            'ix' => 9,
            'x' => 10,
            'xi' => 11,
            'xii' => 12,
        ];

        return $orderMap[strtolower($code)] ?? 0;
    }
}
