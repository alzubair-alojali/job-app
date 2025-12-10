<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class applyJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resume_option' => 'required|string',
            'resume_file' => 'required_if:resume_option,new_resume|file|mimes:pdf|max:5120', // Max 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'resume_option.required' => 'Please select a resume option.',
            'resume_option.string' => 'The resume option must be a valid string.',
            'resume_file.required' => 'Please upload your resume.',
            'resume_file.file' => 'The resume must be a valid file.',
            'resume_file.mimes' => 'The resume must be a file of type: pdf.',
            'resume_file.max' => 'The resume may not be greater than 5MB.',
        ];
    }
}
