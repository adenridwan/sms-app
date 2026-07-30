<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('students.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->route('student');
        $userId = $student->user_id;

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

            // Student data
            'nis' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('students', 'nis')->ignore($student->id),
            ],
            'nisn' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('students', 'nisn')->ignore($student->id),
            ],
            'nik' => ['nullable', 'string', 'max:20'],
            'gender' => ['sometimes', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'in:islam,kristen,katolik,hindu,buddha,konghucu'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'previous_school' => ['nullable', 'string', 'max:200'],
            'entry_year' => ['sometimes', 'integer', 'min:2000', 'max:' . (date('Y') + 1)],
            'entry_class' => ['nullable', 'string', 'max:50'],
            'entry_semester' => ['nullable', 'integer', 'in:1,2'],
            'status' => ['sometimes', 'in:active,graduated,transferred,dropped'],
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
            'nis.unique' => 'NIS sudah terdaftar.',
            'nisn.unique' => 'NISN sudah terdaftar.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'status.in' => 'Status tidak valid.',
        ];
    }
}
