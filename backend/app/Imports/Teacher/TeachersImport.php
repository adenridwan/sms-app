<?php

namespace App\Imports\Teacher;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use App\Services\EmailGenerator;
use App\Services\TeacherRegistrar;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import guru dari file CSV/Excel (upsert berdasarkan NIP dalam satu tenant).
 *
 * Guru baru dibuat lewat TeacherRegistrar — sama persis dengan form Tambah
 * Guru — sehingga akun login, password awal dari tanggal lahir, dan kewajiban
 * ganti password saat login pertama tetap berlaku untuk data hasil import.
 *
 * Kolom wajib (nip & no_hp termasuk) hanya berlaku di jalur import ini;
 * struktur tabel tidak berubah.
 *
 * Kolom `email` kini OPSIONAL: dikosongkan berarti email login dibuatkan
 * otomatis dari username + domain sekolah (docs/EMAIL-OTOMATIS-AKUN.md).
 * Berbeda dari siswa yang kolomnya dihapus sama sekali — guru sering punya
 * alamat asli dan wajar ingin login dengannya.
 */
class TeachersImport implements ToCollection, WithHeadingRow
{
    private const REQUIRED_COLUMNS = [
        'nama_depan' => 'nama_depan',
        'no_hp' => 'no_hp',
        'nip' => 'nip',
        'jenis_kelamin' => 'jenis_kelamin',
        'tanggal_lahir' => 'tanggal_lahir',
    ];

    public int $created = 0;

    public int $updated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(
        private string $tenantId,
        private TeacherRegistrar $registrar,
        private EmailGenerator $emailGenerator,
    ) {}

    public function collection(Collection $rows): void
    {
        // Baris tanpa kolom email butuh domain sekolah. Kalau belum diatur,
        // gagalkan sekali dengan pesan yang mengarahkan admin — kecuali semua
        // baris memang mengisi emailnya sendiri.
        $needsDomain = ! $this->emailGenerator->hasDomain($this->tenantId)
            && $rows->contains(fn ($row) => $this->text($row['email'] ?? null) === ''
                && $this->text($row['nama_depan'] ?? null) !== '');

        if ($needsDomain) {
            $this->errors[] = EmailGenerator::domainMissingMessage();

            return;
        }

        foreach ($rows as $index => $row) {
            // +2: baris judul + indeks mulai dari 1
            $rowNumber = $index + 2;

            try {
                $this->importRow($row->toArray(), $rowNumber);
            } catch (Throwable $e) {
                // Satu baris bermasalah tidak boleh menggagalkan seluruh file.
                $this->errors[] = "Baris {$rowNumber}: gagal diproses ({$e->getMessage()}).";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(array $row, int $rowNumber): void
    {
        $values = [
            'nama_depan' => $this->text($row['nama_depan'] ?? null),
            'nama_belakang' => $this->text($row['nama_belakang'] ?? null),
            'email' => strtolower($this->text($row['email'] ?? null)),
            'email_kontak' => strtolower($this->text($row['email_kontak'] ?? null)),
            'no_hp' => $this->text($row['no_hp'] ?? null),
            'nip' => $this->text($row['nip'] ?? null),
            'nuptk' => $this->text($row['nuptk'] ?? null),
            'jenis_kelamin' => $this->text($row['jenis_kelamin'] ?? null),
            'tempat_lahir' => $this->text($row['tempat_lahir'] ?? null),
            'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
            'nik' => $this->text($row['nik'] ?? null),
            'alamat' => $this->text($row['alamat'] ?? null),
            'tanggal_masuk' => $row['tanggal_masuk'] ?? null,
        ];

        // Baris kosong (mis. sisa baris di bawah data) dilewati diam-diam.
        if (collect($values)->filter(fn ($value) => $this->text($value) !== '')->isEmpty()) {
            return;
        }

        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $key => $label) {
            if ($this->text($values[$key]) === '') {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            $this->errors[] = "Baris {$rowNumber}: kolom " . implode(', ', $missing) . ' wajib diisi.';

            return;
        }

        // Kedua kolom email opsional, tapi kalau diisi formatnya harus benar.
        if ($values['email'] !== '' && ! filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Baris {$rowNumber}: format email '{$values['email']}' tidak valid.";

            return;
        }

        if ($values['email_kontak'] !== '' && ! filter_var($values['email_kontak'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Baris {$rowNumber}: format email_kontak '{$values['email_kontak']}' tidak valid.";

            return;
        }

        $gender = $this->gender($values['jenis_kelamin']);
        if ($gender === null) {
            $this->errors[] = "Baris {$rowNumber}: jenis_kelamin '{$values['jenis_kelamin']}' tidak dikenal (isi L atau P).";

            return;
        }

        $birthDate = $this->date($values['tanggal_lahir']);
        if ($birthDate === null) {
            $this->errors[] = "Baris {$rowNumber}: tanggal_lahir tidak terbaca (pakai YYYY-MM-DD atau DD/MM/YYYY).";

            return;
        }

        if ($birthDate->isToday() || $birthDate->isFuture()) {
            $this->errors[] = "Baris {$rowNumber}: tanggal_lahir harus sebelum hari ini.";

            return;
        }

        $joinDate = $this->date($values['tanggal_masuk']);

        // withTrashed() + restore: alasannya sama persis dengan StudentsImport —
        // `teachers` unik pada (tenant_id, nip) dan index itu ikut menghitung
        // baris yang ter-soft-delete.
        $existing = Teacher::withTrashed()
            ->where('tenant_id', $this->tenantId)
            ->where('nip', $values['nip'])
            ->first();

        if ($existing?->trashed()) {
            $existing->restore();
            $existing->user()->withTrashed()->first()?->restore();
        }

        // Email unik lintas sekolah (kolom users.email), jadi pengecekannya
        // harus tanpa scope tenant. Hanya berlaku untuk email yang diketik di
        // file — yang hasil generate sudah dijamin unik oleh EmailGenerator.
        if ($values['email'] !== '') {
            // withTrashed(): index UNIQUE ikut menghitung baris ter-soft-delete,
            // jadi alamat milik guru yang pernah dihapus tetap "sudah dipakai".
            $emailOwner = User::withoutTenant()->withTrashed()->where('email', $values['email'])->first();
            if ($emailOwner && $emailOwner->id !== $existing?->user_id) {
                $this->errors[] = "Baris {$rowNumber}: email '{$values['email']}' sudah dipakai akun lain.";

                return;
            }
        }

        if ($values['nuptk'] !== '') {
            $nuptkTaken = Teacher::where('tenant_id', $this->tenantId)
                ->where('nuptk', $values['nuptk'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($nuptkTaken) {
                $this->errors[] = "Baris {$rowNumber}: NUPTK '{$values['nuptk']}' sudah dipakai guru lain.";

                return;
            }
        }

        $payload = [
            'first_name' => $values['nama_depan'],
            'last_name' => $values['nama_belakang'] ?: null,
            // Kosong = TeacherRegistrar yang membuatkan email loginnya.
            'email' => $values['email'] ?: null,
            'contact_email' => $values['email_kontak'] ?: null,
            'phone' => $values['no_hp'],
            'gender' => $gender,
            'birth_place' => $values['tempat_lahir'] ?: null,
            'birth_date' => $birthDate->toDateString(),
            'address' => $values['alamat'] ?: null,
            'id_number' => $values['nik'] ?: null,
            'nip' => $values['nip'],
            'nuptk' => $values['nuptk'] ?: null,
            'join_date' => $joinDate?->toDateString(),
        ];

        if ($existing) {
            $this->updateExisting($existing, $payload);
            $this->updated++;

            return;
        }

        $this->registrar->create($payload, $this->tenantId);
        $this->created++;
    }

    /**
     * Perbarui guru yang sudah ada. Password TIDAK pernah disentuh dari
     * import — reset password adalah aksi terpisah.
     *
     * Kolom email yang dikosongkan di file tidak menghapus alamat yang sudah
     * ada: email login adalah kredensial, dan menimpanya dari file akan
     * mengunci guru yang bersangkutan.
     *
     * @param  array<string, mixed>  $payload
     */
    private function updateExisting(Teacher $teacher, array $payload): void
    {
        DB::transaction(function () use ($teacher, $payload) {
            $userFields = [];

            if ($payload['email'] !== null) {
                $userFields['email'] = $payload['email'];
                $userFields['email_is_generated'] = false;
            }

            if ($payload['contact_email'] !== null) {
                $userFields['contact_email'] = $payload['contact_email'];
            }

            if ($userFields !== []) {
                $teacher->user?->update($userFields);
            }

            UserProfile::updateOrCreate(['user_id' => $teacher->user_id], [
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'phone' => $payload['phone'],
                'gender' => $payload['gender'],
                'birth_place' => $payload['birth_place'],
                'birth_date' => $payload['birth_date'],
                'address' => $payload['address'],
                'id_number' => $payload['id_number'],
            ]);

            // Kolom opsional yang dikosongkan di file tidak menghapus data
            // yang sudah ada — hanya nilai terisi yang menimpa.
            $fields = ['no_hp' => $payload['phone']];

            if ($payload['nuptk'] !== null) {
                $fields['nuptk'] = $payload['nuptk'];
            }

            if ($payload['join_date'] !== null) {
                $fields['join_date'] = $payload['join_date'];
            }

            $teacher->update($fields);
        });
    }

    private function text(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }

        // Sebagian pembaca mengembalikan kolom tanggal sebagai objek.
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // NIP/NIK panjang bisa terbaca sebagai float oleh pembaca Excel —
        // %.0f mencegahnya berubah jadi notasi ilmiah (1.985E+17).
        if (is_float($value)) {
            return trim(sprintf('%.0f', $value));
        }

        return trim((string) $value);
    }

    private function gender(string $value): ?string
    {
        return match (strtolower($value)) {
            'l', 'lk', 'laki-laki', 'laki laki', 'pria', 'male', 'm' => 'male',
            'p', 'pr', 'perempuan', 'wanita', 'female', 'f' => 'female',
            default => null,
        };
    }

    /**
     * Terima serial tanggal Excel maupun teks (YYYY-MM-DD / DD/MM/YYYY).
     */
    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $this->text($value) === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }

        $text = $this->text($value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                // Carbon melempar (bukan mengembalikan false) untuk format
                // yang tidak cocok, jadi tiap percobaan perlu try sendiri.
                $parsed = Carbon::createFromFormat($format, $text);
            } catch (Throwable) {
                continue;
            }

            if ($parsed->format($format) === $text) {
                return $parsed->startOfDay();
            }
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
