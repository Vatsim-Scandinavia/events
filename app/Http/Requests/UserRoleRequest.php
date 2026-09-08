<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\PermissionName;
use App\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ManageRoles) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RoleName::class)->except([RoleName::Pilot])],
            'team_id' => [
                Rule::requiredIf($this->input('role') !== RoleName::Administrator->value),
                Rule::prohibitedIf($this->input('role') === RoleName::Administrator->value),
                'nullable', 'integer', Rule::exists(Team::class, 'id'),
            ],
            'source' => ['prohibited'],
        ];
    }
}
