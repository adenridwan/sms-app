<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('teachers.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teacher = $this->route('teacher');
        $userId = $teacher->user_id;

        return [
            // Akun & Pribadi (Tab 1) — password TIDAK diubah di sini, lihat Reset Password
            // `filled`: string kosong lolos aturan `string` — tanpa ini nama
            // guru bisa dikosongkan diam-diam lewat form Edit.
            'first_name' => ['sometimes', 'filled', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // Alamat surat (OTP/notifikasi). SENGAJA tanpa aturan unique —
            // lihat docs/EMAIL-OTOMATIS-AKUN.md.
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['sometimes', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'id_number' => ['nullable', 'string', 'max:20'],

            // Kepegawaian (Tab 2)
            'nip' => [
                'nullable', 'string', 'max:30',
                Rule::unique('teachers', 'nip')->where(fn ($q) => $q->where('tenant_id', $teacher->tenant_id))->ignore($teacher->id),
            ],
            'nuptk' => [
                'nullable', 'string', 'max:30',
                Rule::unique('teachers', 'nuptk')->where(fn ($q) => $q->where('tenant_id', $teacher->tenant_id))->ignore($teacher->id),
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
            'first_name.filled' => 'Nama depan wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'nip.unique' => 'NIP sudah digunakan guru lain.',
            'nuptk.unique' => 'NUPTK sudah digunakan guru lain.',
        ];
    }
}
