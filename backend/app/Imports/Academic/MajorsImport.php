<?php

namespace App\Imports\Academic;

use App\Infrastructure\Persistence\Eloquent\Academic\Major;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MajorsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // +2: heading row + 1-based index
            $rowNumber = $index + 2;

            $code = trim((string) ($row['kode'] ?? ''));
            $name = trim((string) ($row['nama'] ?? ''));

            if ($code === '' && $name === '') {
                continue; // skip empty rows
            }

            if ($code === '' || $name === '') {
                $this->errors[] = "Baris {$rowNumber}: kolom 'kode' dan 'nama' wajib diisi.";
                continue;
            }

            $major = Major::withTrashed()->firstOrNew(['code' => $code]);
            $isNew = ! $major->exists;

            $major->fill([
                'name' => $name,
                'description' => trim((string) ($row['deskripsi'] ?? '')) ?: null,
                'is_active' => self::parseBoolean($row['aktif'] ?? true),
            ]);

            if ($major->trashed()) {
                $major->restore();
            }

            $major->save();

            $isNew ? $this->created++ : $this->updated++;
        }
    }

    public static function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return ! in_array($value, ['tidak', 'no', 'false', '0', 'nonaktif'], true);
    }
}
