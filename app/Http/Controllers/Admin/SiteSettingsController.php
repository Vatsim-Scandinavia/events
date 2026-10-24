<?php

namespace App\Http\Controllers\Admin;

use App\Auth\ControllerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettingsRequest;
use App\Services\SiteLogo;
use App\Services\VaccIdentity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Lets admins set who the installation belongs to: the vACC's details and its logo.
 *
 * Secrets and keys for other services never appear here; they stay in the environment.
 */
class SiteSettingsController extends Controller
{
    public function edit(VaccIdentity $identity, ControllerRole $controllerRole): Response
    {
        $effective = $identity->effective();

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => [
                'name' => $effective['name'],
                'division' => $effective['division'],
                'subdivision' => $effective['subdivision'],
                'website' => $effective['website'],
                'logo' => $effective['logo'],
                'heroGradient' => $effective['heroGradient'],
                'autoController' => $identity->controllerRoleIsAutomatic(),
            ],
            // The Integration feature handing out the Controller role instead of the hub's rule.
            'controllerSource' => $controllerRole->source()?->controllerSourceName(),
            // Each locked detail and the environment variable it is set by.
            'locked' => collect($identity->locked())->mapWithKeys(fn (string $field) => [$field => VaccIdentity::FIELDS[$field]]),
        ]);
    }

    public function update(SiteSettingsRequest $request, VaccIdentity $identity, SiteLogo $logos, ControllerRole $controllerRole): RedirectResponse
    {
        $previous = $identity->logo();
        $controllerRule = [$identity->effective()['subdivision'], $identity->controllerRoleIsAutomatic()];
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

        $identity->saveHeroGradient($request->validated('hero_gradient'));
        $identity->setControllerRoleAutomatic($request->boolean('auto_controller'));

        // Who holds the automatic Controller role follows the subdivision and the switch.
        if ([$identity->effective()['subdivision'], $identity->controllerRoleIsAutomatic()] !== $controllerRule) {
            $controllerRole->syncEveryone();
        }

        Inertia::flash('success', 'The settings are saved.');

        return redirect('/admin/settings');
    }
}
