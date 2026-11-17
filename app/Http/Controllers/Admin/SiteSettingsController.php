<?php

namespace App\Http\Controllers\Admin;

use App\Auth\Controllers;
use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettingsRequest;
use App\Services\SiteLogo;
use App\Services\VaccIdentity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Lets admins set who the installation belongs to: the vACC's details, its logo and its theme.
 *
 * Secrets and keys for other services never appear here; they stay in the environment.
 */
class SiteSettingsController extends Controller
{
    public function edit(VaccIdentity $identity, Controllers $controllers): Response
    {
        $effective = $identity->effective();

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => [
                'name' => $effective['name'],
                'division' => $effective['division'],
                'subdivision' => $effective['subdivision'],
                'website' => $effective['website'],
                'logo' => $effective['logo'],
                'theme' => $identity->theme()->value,
                'autoController' => $identity->controllerRuleIsOn(),
            ],
            'themes' => collect(Theme::cases())->map(fn (Theme $theme) => ['value' => $theme->value, 'label' => $theme->label()]),
            // The Integration feature deciding who is a Controller instead of the Controller Rule.
            'controllerSource' => $controllers->source()?->controllerSourceName(),
            // Who would no longer be able to staff events if the Controller Rule were switched off.
            'controllersOnlyByRule' => $controllers->onlyByRuleCount(),
            // Each locked detail and the environment variable it is set by.
            'locked' => collect($identity->locked())->mapWithKeys(fn (string $field) => [$field => VaccIdentity::FIELDS[$field]]),
        ]);
    }

    public function update(SiteSettingsRequest $request, VaccIdentity $identity, SiteLogo $logos): RedirectResponse
    {
        $previous = $identity->logo();
        $uploaded = $request->hasFile('logo') ? $logos->store($request->file('logo')) : null;

        try {
            $identity->save($request->details());

            if ($uploaded !== null || $request->boolean('remove_logo')) {
                $identity->saveLogo($uploaded);
            }
        } catch (Throwable $exception) {
            // Don't leave a just-uploaded logo behind if the settings couldn't be saved.
            $logos->delete($uploaded);

            throw $exception;
        }

        if ($identity->logo() !== $previous) {
            $logos->delete($previous);
        }

        if ($request->has('theme')) {
            $identity->saveTheme($request->enum('theme', Theme::class));
        }

        // The Controller Rule is worked out live, so switching it takes effect at once.
        $identity->switchControllerRule($request->boolean('auto_controller'));

        Inertia::flash('success', 'The settings are saved.');

        return redirect('/admin/settings');
    }
}
