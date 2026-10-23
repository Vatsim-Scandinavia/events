<?php

namespace App\Http\Requests;

use App\Services\VaccIdentity;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The vACC's details and logo as admins enter them on the settings page.
 */
class SiteSettingsRequest extends FormRequest
{
    /**
     * Subdivision codes are entered in any case, like sca.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('subdivision')) {
            $this->merge(['subdivision' => strtoupper(trim((string) $this->input('subdivision')))]);
        }
    }

    /**
     * The automatic Controller role needs a subdivision to check people against.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $subdivision = config('vacc.subdivision') ?? $this->input('subdivision');

                if ($this->boolean('auto_controller') && blank($subdivision)) {
                    $validator->errors()->add('auto_controller', 'Set the subdivision first.');
                }
            },
        ];
    }

    /**
     * Details set in the environment can't be changed here, so they aren't validated or saved.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'division' => ['nullable', 'string', 'max:80'],
            'subdivision' => ['nullable', 'string', 'regex:/^[A-Z]{2,10}$/'],
            'auto_controller' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'string', 'max:255', 'url:https'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'hero_gradient' => ['nullable', 'array:from,to'],
            'hero_gradient.from' => ['required_with:hero_gradient', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
            'hero_gradient.to' => ['required_with:hero_gradient', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
        ];

        foreach (app(VaccIdentity::class)->locked() as $field) {
            unset($rules[$field]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the vACC’s name.',
            'website.url' => 'Enter a full link starting with https://.',
            'subdivision.regex' => 'Use the subdivision’s code from VATSIM, like SCA.',
            'logo.mimes' => 'Upload a PNG, JPG, WebP or SVG file.',
            'logo.max' => 'The logo can be at most 2 MB.',
            'hero_gradient.*.regex' => 'Pick a color like #1e3a8a.',
            'logo.uploaded' => 'The logo couldn’t be uploaded. Try a smaller file (2 MB at most).',
        ];
    }

    /**
     * The details to save, without the ones set in the environment.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->safe()->only(array_keys(VaccIdentity::FIELDS));
    }
}
