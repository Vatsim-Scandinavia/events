<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A controller, named only as the public event page names them.
 *
 * @mixin User
 */
class ControllerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** The controller's VATSIM CID. */
            'cid' => $this->vatsim_cid,
            /** The name the controller chose to show: full name, first name, or their CID. */
            'name' => $this->displayName(),
        ];
    }
}
