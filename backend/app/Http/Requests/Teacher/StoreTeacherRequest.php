<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            // User data
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'unique:users,username', 'alpha_dash'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            // Teacher data
            'nip' => ['nullable', 'string', 'max:30', 'unique:teachers,nip'],
            'nuptk' => ['nullable', 'string', 'max:30', 'unique:teachers,nuptk'],
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'education_level' => ['nullable', 'string', 'in:s1,s2,s3,d3,d4'],
            'education_major' => ['nullable', 'string', 'max:100'],
            'join_date' => ['nullable', 'date'],
            'position' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:200'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['uuid', 'exists:subjects,id'],
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
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'first_name.required' => 'Nama depan wajib diisi.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'nuptk.unique' => 'NUPTK sudah terdaftar.',
            'gender.required' => 'Jenis kelamin wajib diisi.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.max' => 'Ukuran foto maksimal 2MB.',
        ];
    }
}
