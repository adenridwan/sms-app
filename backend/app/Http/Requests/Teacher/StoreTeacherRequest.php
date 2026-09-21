<?php

namespace App\Http\Requests\Teacher;

use App\Rules\LinkableUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('teachers.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Tenant guru baru: user aktif (tenant sendiri) atau super admin (X-Tenant-ID)
        $tenantId = $this->user()?->tenant_id ?? $this->header('X-Tenant-ID');

        return [
            // Tautkan ke akun yang SUDAH ada, alih-alih membuat akun baru.
            // Dipakai untuk akun bertipe `teacher` yang muncul di menu Pengguna
            // tapi belum punya baris di `teachers` sehingga tidak pernah tampil
            // di Daftar Guru. Bila diisi, seluruh field akun & data pribadi di
            // bawah tidak lagi wajib — datanya sudah ada pada akun itu.
            'user_id' => ['nullable', 'uuid', new LinkableUser($tenantId, 'teacher', 'teachers', 'guru')],

            // Akun & Pribadi (Tab 1)
            'first_name' => ['required_without:user_id', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            // Dikosongkan = dibuatkan otomatis dari username
            // (docs/EMAIL-OTOMATIS-AKUN.md). Guru yang punya alamat asli
            // sebaiknya tetap mengisinya: notifikasi kehadiran guru dikirim ke
            // contact_email, yang otomatis diisi dari sini bila dibiarkan kosong.
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['required_without:user_id', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            // Wajib: sumber password awal (format ddmmyyyy)
            'birth_date' => ['required_without:user_id', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'id_number' => ['nullable', 'string', 'max:20'],

            // Kepegawaian (Tab 2)
            'nip' => [
                'nullable', 'string', 'max:30',
                Rule::unique('teachers', 'nip')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'nuptk' => [
                'nullable', 'string', 'max:30',
                Rule::unique('teachers', 'nuptk')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'join_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', Rule::in(['permanent', 'contract', 'honorary', 'part_time'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'on_leave', 'retired', 'terminated'])],
            'certification_status' => ['nullable', Rule::in(['certified', 'not_certified', 'in_progress'])],
            'certification_number' => ['nullable', 'string', 'max:50'],
            // Bukan `in:` lagi: jenjang di luar daftar dropdown (mis.
            // PESANTREN) disimpan apa adanya di kolom string yang sama —
            // lihat opsi "Lainnya" pada form guru.
            'education_level' => ['nullable', 'string', 'max:50'],
            'education_major' => ['nullable', 'string', 'max:100'],
            'university' => ['nullable', 'string', 'max:150'],
            'teaching_experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Kunci `required_without`, bukan `required`: field ini hanya wajib
            // saat akun baru dibuat. Kalau `user_id` dikirim (menautkan ke akun
            // yang sudah ada), datanya diambil dari akun tersebut.
            'first_name.required_without' => 'Nama depan wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'contact_email.email' => 'Format email kontak tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'gender.required_without' => 'Jenis kelamin wajib diisi.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.required_without' => 'Tanggal lahir wajib diisi (dipakai sebagai password awal).',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'nip.unique' => 'NIP sudah digunakan guru lain.',
            'nuptk.unique' => 'NUPTK sudah digunakan guru lain.',
        ];
    }
}
