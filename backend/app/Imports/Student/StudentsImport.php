<?php

namespace App\Imports\Student;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use App\Services\EmailGenerator;
use App\Services\StudentRegistrar;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import siswa dari file CSV/Excel (upsert berdasarkan NIS dalam satu tenant).
 *
 * Siswa baru dibuat lewat StudentRegistrar sehingga akun loginnya mengikuti
 * aturan yang sama dengan guru: password awal dari tanggal lahir (ddmmyyyy)
 * dan wajib diganti saat login pertama. Karena itu tanggal_lahir termasuk
 * kolom wajib.
 *
 * Email login SELALU dibuat otomatis di sini (nama depan + NIS + domain
 * sekolah) — tidak ada kolom untuk memasukkannya dari file, karena mengisi
 * email satu per satu untuk ratusan siswa adalah masalah yang justru sedang
 * diselesaikan. Lihat docs/EMAIL-OTOMATIS-AKUN.md.
 *
 * Kolom `email` pada file hasil export SENGAJA diabaikan: isinya email login
 * (informasi untuk dibagikan ke siswa). Kalau ia dibaca sebagai `email_kontak`,
 * setiap re-import file export akan mengisi contact_email seluruh siswa dengan
 * alamat sintetis. Alamat surat sungguhan diisi lewat kolom `email_kontak`.
 *
 * Kolom `kelas` opsional: bila diisi kode kelas yang ada pada tahun ajaran
 * aktif, siswa sekaligus ditempatkan (student_enrollments). Dikosongkan pun
 * tidak apa — data siswanya tetap masuk.
 */
class StudentsImport implements ToCollection, WithHeadingRow
{
    private const REQUIRED_COLUMNS = [
        'nis' => 'nis',
        'nama_depan' => 'nama_depan',
        'jenis_kelamin' => 'jenis_kelamin',
        'tanggal_lahir' => 'tanggal_lahir',
    ];

    public int $created = 0;

    public int $updated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    private ?AcademicYear $activeYear = null;

    private bool $activeYearResolved = false;

    public function __construct(
        private string $tenantId,
        private StudentRegistrar $registrar,
        private EmailGenerator $emailGenerator,
    ) {}

    public function collection(Collection $rows): void
    {
        // Preflight: tanpa domain sekolah, tidak satu pun siswa baru bisa
        // dibuatkan email. Gagalkan sekali dengan pesan yang mengarahkan admin,
        // bukan ratusan error per baris yang menutupi penyebab aslinya.
        if (! $this->emailGenerator->hasDomain($this->tenantId)) {
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
            'nis' => $this->text($row['nis'] ?? null),
            'nama_depan' => $this->text($row['nama_depan'] ?? null),
            'nama_belakang' => $this->text($row['nama_belakang'] ?? null),
            'email_kontak' => strtolower($this->text($row['email_kontak'] ?? null)),
            'jenis_kelamin' => $this->text($row['jenis_kelamin'] ?? null),
            'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
            'nisn' => $this->text($row['nisn'] ?? null),
            'nik' => $this->text($row['nik'] ?? null),
            'no_hp' => $this->text($row['no_hp'] ?? null),
            'tempat_lahir' => $this->text($row['tempat_lahir'] ?? null),
            'alamat' => $this->text($row['alamat'] ?? null),
            'sekolah_asal' => $this->text($row['sekolah_asal'] ?? null),
            'kelas' => $this->text($row['kelas'] ?? null),
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

        // Email kontak opsional — tapi kalau diisi, formatnya harus benar,
        // karena ke sanalah OTP/notifikasi dikirim.
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

        // withTrashed(): `students` unik pada (tenant_id, nis) dan index itu
        // ikut menghitung baris yang ter-soft-delete. Tanpa ini, siswa yang
        // pernah dihapus lalu diimpor ulang dianggap baru, dan insert-nya
        // ditolak "students_tenant_id_nis_unique" sebagai error SQL mentah.
        // Perlakuannya sama dengan enrollment di placeInClassroom(): baris
        // lama dipulihkan, bukan digandakan — NIS yang sama berarti orang
        // yang sama kembali terdaftar.
        $existing = Student::withTrashed()
            ->where('tenant_id', $this->tenantId)
            ->where('nis', $values['nis'])
            ->first();

        if ($existing?->trashed()) {
            $existing->restore();
            $existing->user()->withTrashed()->first()?->restore();
        }

        if ($values['nisn'] !== '') {
            $nisnTaken = Student::where('tenant_id', $this->tenantId)
                ->where('nisn', $values['nisn'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($nisnTaken) {
                $this->errors[] = "Baris {$rowNumber}: NISN '{$values['nisn']}' sudah dipakai siswa lain.";

                return;
            }
        }

        $payload = [
            'nis' => $values['nis'],
            'nisn' => $values['nisn'] ?: null,
            'first_name' => $values['nama_depan'],
            'last_name' => $values['nama_belakang'] ?: null,
            // Kosong = StudentRegistrar yang membuatkan email loginnya.
            'email' => null,
            'contact_email' => $values['email_kontak'] ?: null,
            'gender' => $gender,
            'birth_date' => $birthDate->toDateString(),
            'birth_place' => $values['tempat_lahir'] ?: null,
            'phone' => $values['no_hp'] ?: null,
            'address' => $values['alamat'] ?: null,
            'id_number' => $values['nik'] ?: null,
            'previous_school' => $values['sekolah_asal'] ?: null,
        ];

        if ($existing) {
            $this->updateExisting($existing, $payload);
            $student = $existing;
            $this->updated++;
        } else {
            $student = $this->registrar->create($payload, $this->tenantId)['student'];
            $this->created++;
        }

        if ($values['kelas'] !== '') {
            $this->placeInClassroom($student, $values['kelas'], $rowNumber);
        }
    }

    /**
     * Perbarui siswa yang sudah ada. Password TIDAK pernah disentuh dari
     * import — reset password adalah aksi terpisah.
     *
     * Email login juga tidak pernah disentuh: itu kredensial siswa, dan
     * mengubahnya dari file akan mengunci siswa yang bersangkutan tanpa ia
     * tahu. Hanya email kontak yang bisa diperbarui, dan hanya bila diisi —
     * kolom yang dikosongkan tidak menghapus alamat yang sudah ada.
     *
     * @param  array<string, mixed>  $payload
     */
    private function updateExisting(Student $student, array $payload): void
    {
        DB::transaction(function () use ($student, $payload) {
            if ($payload['contact_email'] !== null) {
                $student->user?->update(['contact_email' => $payload['contact_email']]);
            }

            UserProfile::updateOrCreate(['user_id' => $student->user_id], [
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
            $fields = [];

            if ($payload['nisn'] !== null) {
                $fields['nisn'] = $payload['nisn'];
            }

            if ($payload['previous_school'] !== null) {
                $fields['previous_school'] = $payload['previous_school'];
            }

            if ($fields !== []) {
                $student->update($fields);
            }
        });
    }

    /**
     * Tempatkan siswa pada kelas (tahun ajaran aktif). Baris enrollment yang
     * pernah dihapus dipakai ulang: pasangan (student_id, academic_year_id)
     * unik di DB termasuk untuk baris yang ter-soft-delete.
     *
     * Kolom `kelas` dicocokkan ke NAMA maupun KODE kelas. Dulu hanya kode —
     * padahal `classrooms.code` dibuat otomatis (`KLS01`, `KLS02`, lihat
     * ClassroomController::generateNextCode()) dan tidak pernah ditampilkan di
     * menu Kelas, sehingga admin mustahil menebaknya dan penempatan kelas dari
     * import tidak pernah berhasil.
     */
    private function placeInClassroom(Student $student, string $classCode, int $rowNumber): void
    {
        $year = $this->activeYear();

        if (! $year) {
            $this->errors[] = "Baris {$rowNumber}: tidak ada tahun ajaran aktif, kolom kelas dilewati.";

            return;
        }

        $needle = strtolower(trim($classCode));

        $candidates = Classroom::where('tenant_id', $this->tenantId)
            ->where('academic_year_id', $year->id)
            ->where(function ($q) use ($needle) {
                $q->whereRaw('lower(code) = ?', [$needle])
                    ->orWhereRaw('lower(name) = ?', [$needle]);
            })
            ->get();

        // Kode dijamin unik per tahun ajaran, nama tidak — kalau ada dua kelas
        // bernama sama, lebih baik berhenti dan minta kodenya daripada menebak.
        if ($candidates->count() > 1) {
            $codes = $candidates->pluck('code')->implode(', ');
            $this->errors[] = "Baris {$rowNumber}: ada lebih dari satu kelas bernama '{$classCode}'. Tulis kodenya saja ({$codes}).";

            return;
        }

        $classroom = $candidates->first();

        if (! $classroom) {
            // Sebutkan pilihan yang sah supaya admin bisa langsung membetulkan
            // filenya tanpa menebak-nebak.
            $available = Classroom::where('tenant_id', $this->tenantId)
                ->where('academic_year_id', $year->id)
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');

            $hint = $available !== '' ? " Kelas yang tersedia: {$available}." : ' Belum ada kelas pada tahun ajaran aktif.';
            $this->errors[] = "Baris {$rowNumber}: kelas '{$classCode}' tidak ditemukan pada tahun ajaran aktif.{$hint}";

            return;
        }

        $enrollment = StudentEnrollment::withTrashed()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->first();

        if ($enrollment) {
            if ($enrollment->trashed()) {
                $enrollment->restore();
            }

            $enrollment->update([
                'classroom_id' => $classroom->id,
                'status' => 'active',
            ]);

            return;
        }

        StudentEnrollment::create([
            'tenant_id' => $this->tenantId,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'classroom_id' => $classroom->id,
            'status' => 'active',
            'enrollment_date' => now()->toDateString(),
        ]);
    }

    private function activeYear(): ?AcademicYear
    {
        if (! $this->activeYearResolved) {
            $this->activeYear = AcademicYear::where('tenant_id', $this->tenantId)
                ->where('is_active', true)
                ->first();
            $this->activeYearResolved = true;
        }

        return $this->activeYear;
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

        // NIS/NISN/NIK panjang bisa terbaca sebagai float oleh pembaca Excel —
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
