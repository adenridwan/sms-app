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

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(
        protected AcademicYear $academicYear
    ) {}

    public function collection(Collection $rows): void
    {
        $gradeLevels = GradeLevel::all()->keyBy(fn ($level) => strtolower($level->code));
        $majors = Major::all()->keyBy(fn ($major) => strtolower($major->code));

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
                $this->errors[] = "Baris {$rowNumber}: tingkat '{$gradeCode}' tidak ditemukan.";
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
}
