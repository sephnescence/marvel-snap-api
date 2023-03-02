<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SnapFanCardsRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'page' => [
                'required',
                'integer',
                'gt:0',
            ],
            'date' => [
                'sometimes',
                'date_format:Y-m-d'
            ],
        ];
    }

    // Ensure that validation errors are thrown instead of redirecting
    protected function failedValidation(Validator $validator)
    {
        return;
    }
}
