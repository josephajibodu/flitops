<?php

namespace App\Http\Requests;

use App\Enums\PhpVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallPhpVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePhp', $this->route('server'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', Rule::in(PhpVersion::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Please select a PHP version to install.',
            'version.in' => 'The selected PHP version is not available.',
        ];
    }
}
