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
            // User data
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            // Teacher data
            'nip' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('teachers', 'nip')->ignore($teacher->id),
            ],
            'nuptk' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('teachers', 'nuptk')->ignore($teacher->id),
            ],
            'gender' => ['sometimes', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'education_level' => ['nullable', 'string', 'in:s1,s2,s3,d3,d4'],
            'education_major' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['sometimes', 'in:active,inactive,retired,resigned'],
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
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'nuptk.unique' => 'NUPTK sudah terdaftar.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'employment_status.in' => 'Status kepegawaian tidak valid.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.max' => 'Ukuran foto maksimal 2MB.',
        ];
    }
}
