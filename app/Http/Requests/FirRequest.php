<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\PermissionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FirRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ManageFirs) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'max:16', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', Rule::unique(Team::class)->ignore($this->route('fir'))],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, and single hyphens between them for the FIR code.',
            'code.unique' => 'A FIR with this code already exists.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['code' => 'FIR code', 'name' => 'FIR name'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }
}
