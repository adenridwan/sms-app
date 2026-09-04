<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('staff.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? $this->header('X-Tenant-ID');

        return [
            // Akun & Pribadi
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'id_number' => ['nullable', 'string', 'max:20'],

            // Kepegawaian
            'employee_id' => [
                'nullable', 'string', 'max:30',
                Rule::unique('staff', 'employee_id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'position_id' => ['nullable', 'uuid', 'exists:positions,id'],
            'join_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', Rule::in(['permanent', 'contract', 'honorary', 'part_time'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'on_leave', 'retired', 'terminated'])],
            'education_level' => ['nullable', 'string', 'max:50'],
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
            'first_name.required' => 'Nama depan wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'contact_email.email' => 'Format email kontak tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'gender.required' => 'Jenis kelamin wajib diisi.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.required' => 'Tanggal lahir wajib diisi (dipakai sebagai password awal).',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'employee_id.unique' => 'ID Pegawai sudah digunakan staf lain.',
            'department_id.exists' => 'Bidang tidak ditemukan.',
            'position_id.exists' => 'Jabatan tidak ditemukan.',
        ];
    }
}
