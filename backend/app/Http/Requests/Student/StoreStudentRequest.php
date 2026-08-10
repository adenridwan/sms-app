<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('students.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // User data
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'unique:users,username', 'alpha_dash'],
            // Dikosongkan = dibuatkan otomatis dari nama depan + NIS
            // (docs/EMAIL-OTOMATIS-AKUN.md). Ini identitas login, bukan alamat
            // surat — alamat surat ada di contact_email dan sengaja TIDAK unik
            // supaya satu orang tua bisa memakainya untuk beberapa anak.
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            // Student data
            'nis' => ['required', 'string', 'max:20', 'unique:students,nis'],
            'nisn' => ['nullable', 'string', 'max:20', 'unique:students,nisn'],
            'nik' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'previous_school' => ['nullable', 'string', 'max:200'],
            'entry_year' => ['required', 'integer', 'min:2000', 'max:' . (date('Y') + 1)],
            'entry_class' => ['nullable', 'string', 'max:50'],
            'entry_semester' => ['nullable', 'integer', 'in:1,2'],
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
            'email.email' => 'Format email tidak valid.',
            'contact_email.email' => 'Format email kontak tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'first_name.required' => 'Nama depan wajib diisi.',
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS sudah terdaftar.',
            'nisn.unique' => 'NISN sudah terdaftar.',
            'gender.required' => 'Jenis kelamin wajib diisi.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'entry_year.required' => 'Tahun masuk wajib diisi.',
        ];
    }
}
