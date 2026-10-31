<?php

namespace App\Events;

/**
 * One field an Integration feature adds to the Event form, such as a Discord webhook. The core
 * shows it and hands back what was entered, without knowing what it's for.
 */
final readonly class EventField
{
    /**
     * @param  string  $key  Unique across features, in snake_case, e.g. "discord_roster_webhook".
     * @param  list<mixed>  $rules  Validation rules for a value that is entered.
     * @param  ?string  $value  What it's set to now; never sent for a secret.
     * @param  ?string  $status  For a secret that is set, what staff see in its place, e.g. which
     *                           channel it posts to.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $rules = ['string', 'max:255'],
        public bool $secret = false,
        public ?string $description = null,
        public ?string $value = null,
        public ?string $status = null,
    ) {}

    /**
     * @return array{key: string, label: string, description: ?string, secret: bool, value: ?string, status: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'secret' => $this->secret,
            'value' => $this->secret ? null : $this->value,
            'status' => $this->status,
        ];
    }
}
