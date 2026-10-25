<?php

namespace App\Integrations\ControlCenter;

use App\Integrations\Configuration;
use App\Integrations\Integration;
use App\Integrations\Setting;
use Closure;

/**
 * Control Center, the training system VATSIM Scandinavia and other vACCs run: who is an active
 * controller where, their endorsements, positions and bookings. See
 * github.com/Vatsim-Scandinavia/controlcenter.
 */
class ControlCenter extends Integration
{
    public function key(): string
    {
        return 'control-center';
    }

    public function name(): string
    {
        return 'Control Center';
    }

    public function description(): string
    {
        return 'Your vACC’s training system: who is an active controller and where, and the positions they control.';
    }

    public function settings(): array
    {
        return [
            new Setting('url', 'Control Center URL', 'CONTROL_CENTER_URL', ['string', 'max:255', 'url:https'], description: 'Its address, like https://cc.vatsim-scandinavia.org.'),
            new Setting('token', 'API key', 'CONTROL_CENTER_TOKEN', secret: true, description: 'An API key made in Control Center’s admin settings.'),
            new Setting(
                'areas',
                'Areas and FIRs',
                'CONTROL_CENTER_AREAS',
                ['string', 'max:2000', function (string $attribute, mixed $value, Closure $fail) {
                    foreach (AreaMap::problems((string) $value) as $problem) {
                        $fail($problem);
                    }
                }],
                description: 'Which FIR each Control Center area is, one per line, like “denmark = EKDK”. Controllers active in an area get the Controller role in its FIR.',
                multiline: true,
            ),
        ];
    }

    public function features(): array
    {
        return [new RosterSync, new PositionSync];
    }

    public function testConnection(Configuration $configuration): void
    {
        (new ControlCenterApi($configuration))->checkKey();
    }
}
