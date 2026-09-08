<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\PermissionName;
use App\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ManageRoles) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(RoleName::class)],
            'team_id' => ['nullable', 'integer', Rule::exists(Team::class, 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
