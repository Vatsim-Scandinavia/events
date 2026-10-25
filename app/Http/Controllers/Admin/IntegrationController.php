<?php

namespace App\Http\Controllers\Admin;

use App\Auth\ControllerRole;
use App\Auth\ControllerSource;
use App\Enums\IntegrationRunStatus;
use App\Http\Controllers\Controller;
use App\Integrations\ConnectionFailed;
use App\Integrations\Feature;
use App\Integrations\Integration;
use App\Integrations\Integrations;
use App\Integrations\RunSync;
use App\Integrations\Setting;
use App\Integrations\Syncable;
use App\Models\IntegrationRun;
use App\Services\DisplayTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets admins switch Integrations and their features on, set them up, and watch their syncs.
 *
 * An Integration can only be switched on once its connection test passes. Secrets are never
 * sent back to the page: it only learns whether one is set.
 */
class IntegrationController extends Controller
{
    /** How many recent runs the page shows. */
    private const int RECENT_RUNS = 20;

    public function index(Integrations $integrations, DisplayTime $time): Response
    {
        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => $integrations->all()->values()->map(function (Integration $integration) use ($integrations, $time) {
                $lastRun = IntegrationRun::query()->where('integration', $integration->key())->latest('started_at')->latest('id')->first();

                return [
                    'key' => $integration->key(),
                    'name' => $integration->name(),
                    'description' => $integration->description(),
                    'enabled' => $integrations->isEnabled($integration),
                    'features' => collect($integration->features())
                        ->filter(fn (Feature $feature) => $integrations->isOn($integration, $feature))
                        ->map(fn (Feature $feature) => $feature->name())
                        ->values(),
                    'lastRun' => $lastRun ? ['status' => $lastRun->status, 'startedAt' => $time->moment($lastRun->started_at)] : null,
                    'held' => IntegrationRun::query()->where('integration', $integration->key())->where('status', IntegrationRunStatus::Held)->count(),
                ];
            }),
        ]);
    }

    public function edit(string $integration, Integrations $integrations, DisplayTime $time): Response
    {
        $integration = $this->find($integration, $integrations);
        $locked = $integrations->locked($integration);
        $configuration = $integrations->configuration($integration);
        $features = collect($integration->features())->keyBy(fn (Feature $feature) => $feature->key());

        return Inertia::render('Admin/Integrations/Edit', [
            'integration' => [
                'key' => $integration->key(),
                'name' => $integration->name(),
                'description' => $integration->description(),
                'enabled' => $integrations->isEnabled($integration),
                'syncs' => $integration->syncs(),
                'removalLimit' => $integrations->removalLimit($integration),
                'settings' => collect($integration->settings())->map(fn (Setting $setting) => [
                    'key' => $setting->key,
                    'label' => $setting->label,
                    'description' => $setting->description,
                    'secret' => $setting->secret,
                    'required' => $setting->required,
                    'multiline' => $setting->multiline,
                    'value' => $setting->secret ? null : $configuration->get($setting->key),
                    'isSet' => filled($configuration->get($setting->key)),
                    'lockedBy' => $locked[$setting->key] ?? null,
                ]),
                'features' => $features->values()->map(fn (Feature $feature) => [
                    'key' => $feature->key(),
                    'name' => $feature->name(),
                    'description' => $feature->description(),
                    'enabled' => in_array($feature->key(), $integrations->savedFeatures($integration), true),
                    'on' => $integrations->isOn($integration, $feature),
                    'syncs' => $feature instanceof Syncable,
                    'controllerSource' => $feature instanceof ControllerSource,
                    'needsAttention' => $integrations->isOn($integration, $feature) ? $feature->needsAttention() : [],
                ]),
            ],
            'runs' => IntegrationRun::query()
                ->with('approver')
                ->where('integration', $integration->key())
                ->latest('started_at')
                ->latest('id')
                ->limit(self::RECENT_RUNS)
                ->get()
                ->map(fn (IntegrationRun $run) => [
                    'id' => $run->id,
                    'feature' => $features->get($run->feature)?->name() ?? $run->feature,
                    'status' => $run->status,
                    'added' => $run->added,
                    'updated' => $run->updated,
                    'removed' => $run->removed,
                    'message' => $run->message,
                    'warnings' => $run->warnings ?? [],
                    'approvedBy' => $run->approver?->name,
                    'startedAt' => $time->moment($run->started_at),
                    'canApprove' => $run->status === IntegrationRunStatus::Held && $features->has($run->feature)
                        && $integrations->isOn($integration, $features->get($run->feature)),
                ]),
        ]);
    }

    public function update(Request $request, string $integration, Integrations $integrations, ControllerRole $controllerRole): RedirectResponse
    {
        $integration = $this->find($integration, $integrations);
        $validated = $this->validated($request, $integration, $integrations);
        $wasOn = $this->featuresOn($integration, $integrations);
        $previousSource = $controllerRole->source();

        $integrations->save($integration, $validated['settings'], $validated['enabled'], $validated['features'], $validated['removal_limit']);

        foreach ($integration->features() as $feature) {
            if (in_array($feature->key(), $wasOn, true) && ! $integrations->isOn($integration, $feature)) {
                $feature->switchedOff();
            }
        }

        // A Controller source switched off hands its roles to admins; the hub's rule then takes over again.
        $source = $controllerRole->source();
        if ($previousSource !== null && ($source === null || $source::class !== $previousSource::class)) {
            $controllerRole->handOver();
            $controllerRole->syncEveryone();
        }

        // Sync what was just switched on straight away, rather than at its next scheduled run.
        foreach ($integration->features() as $feature) {
            if ($feature instanceof Syncable && $integrations->isOn($integration, $feature) && ! in_array($feature->key(), $wasOn, true)) {
                RunSync::dispatch($integration->key(), $feature->key());
            }
        }

        Inertia::flash('success', "{$integration->name()} is saved.");

        return redirect("/admin/integrations/{$integration->key()}");
    }

    public function test(string $integration, Integrations $integrations): RedirectResponse
    {
        $integration = $this->find($integration, $integrations);

        try {
            $integration->testConnection($integrations->configuration($integration));
        } catch (ConnectionFailed $exception) {
            throw ValidationException::withMessages(['connection' => "Couldn’t connect: {$exception->getMessage()}"]);
        }

        Inertia::flash('success', "Connected to {$integration->name()}.");

        return back();
    }

    public function sync(string $integration, string $feature, Integrations $integrations): RedirectResponse
    {
        $integration = $this->find($integration, $integrations);
        $syncing = $integration->feature($feature);

        abort_unless($syncing instanceof Syncable, 404);

        if (! $integrations->isOn($integration, $syncing)) {
            throw ValidationException::withMessages(['sync' => "Switch {$syncing->name()} on first."]);
        }

        RunSync::dispatch($integration->key(), $syncing->key());

        Inertia::flash('success', "{$syncing->name()} will sync in a moment.");

        return back();
    }

    /**
     * Let a held run's changes through, by running its sync again with the removal limit lifted
     * once. The new run records who approved it.
     */
    public function approve(Request $request, string $integration, IntegrationRun $run, Integrations $integrations): RedirectResponse
    {
        $integration = $this->find($integration, $integrations);
        $feature = $integration->feature($run->feature);

        abort_unless($run->integration === $integration->key() && $feature instanceof Syncable, 404);

        if ($run->status !== IntegrationRunStatus::Held) {
            throw ValidationException::withMessages(['run' => 'Only held runs can be approved.']);
        }

        if (! $integrations->isOn($integration, $feature)) {
            throw ValidationException::withMessages(['run' => "Switch {$feature->name()} on first."]);
        }

        $run->update(['status' => IntegrationRunStatus::Approved, 'approved_by' => $request->user()->id]);
        RunSync::dispatch($integration->key(), $feature->key(), $request->user()->id);

        Inertia::flash('success', "{$feature->name()} will sync in a moment, with your approval.");

        return back();
    }

    /**
     * @return list<string>
     */
    private function featuresOn(Integration $integration, Integrations $integrations): array
    {
        return collect($integration->features())
            ->filter(fn (Feature $feature) => $integrations->isOn($integration, $feature))
            ->map(fn (Feature $feature) => $feature->key())
            ->values()
            ->all();
    }

    private function find(string $key, Integrations $integrations): Integration
    {
        return $integrations->find($key) ?? abort(404);
    }

    /**
     * The setup to save. Settings set in the environment aren't validated or saved, and a secret
     * left empty keeps the one already saved. Switching on also needs every required setting,
     * no clashing features and a connection that works.
     *
     * @return array{settings: array<string, mixed>, enabled: bool, features: list<string>, removal_limit: int}
     */
    private function validated(Request $request, Integration $integration, Integrations $integrations): array
    {
        $locked = $integrations->locked($integration);
        $settings = collect($integration->settings())->reject(fn (Setting $setting) => array_key_exists($setting->key, $locked));

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'features' => ['present', 'array'],
            'features.*' => ['string', 'distinct', Rule::in(collect($integration->features())->map(fn (Feature $feature) => $feature->key()))],
            'removal_limit' => $integration->syncs() ? ['required', 'integer', 'between:1,100'] : ['prohibited'],
            'settings' => ['array'],
            ...$settings->mapWithKeys(fn (Setting $setting) => ["settings.{$setting->key}" => ['nullable', ...$setting->rules]])->all(),
        ], [
            'removal_limit.between' => 'Choose a share between 1 and 100%.',
        ]);

        $enabled = (bool) $data['enabled'];
        $features = array_values($data['features']);
        $removalLimit = (int) ($data['removal_limit'] ?? $integrations->removalLimit($integration));
        $proposed = $integrations->proposed($integration, $data['settings'] ?? [], $enabled, $features, $removalLimit);

        if (! $enabled) {
            return ['settings' => $proposed['settings'], 'enabled' => false, 'features' => $features, 'removal_limit' => $removalLimit];
        }

        $validator = Validator::make([], []);

        foreach ($integration->settings() as $setting) {
            if ($setting->required && blank($proposed['configuration']->get($setting->key))) {
                $validator->errors()->add("settings.{$setting->key}", "Enter the {$setting->label} before switching {$integration->name()} on.");
            }
        }

        foreach ($integrations->conflicts($integration, $features) as $conflict) {
            $validator->errors()->add('features', $conflict);
        }

        if ($validator->errors()->isEmpty()) {
            try {
                $integration->testConnection($proposed['configuration']);
            } catch (ConnectionFailed $exception) {
                $validator->errors()->add('enabled', "Couldn’t connect, so it stays off: {$exception->getMessage()}");
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        return ['settings' => $proposed['settings'], 'enabled' => true, 'features' => $features, 'removal_limit' => $removalLimit];
    }
}
