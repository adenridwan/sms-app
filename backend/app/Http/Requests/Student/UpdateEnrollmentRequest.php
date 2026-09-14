<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('students.enroll');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classroom_id' => ['sometimes', 'uuid', 'exists:classrooms,id'],
            'student_number_in_class' => ['nullable', 'string', 'max:10'],
            'status' => ['sometimes', Rule::in(['active', 'promoted', 'retained', 'transferred', 'dropped'])],
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
            'classroom_id.exists' => 'Kelas tidak ditemukan.',
            'status.in' => 'Status tidak valid.',
        ];
    }
}
