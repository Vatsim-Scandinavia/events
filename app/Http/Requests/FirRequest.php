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
            'code' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{4}\z/', Rule::unique(Team::class)->ignore($this->route('fir'))],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'The FIR code must be exactly 4 letters (A-Z).',
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
