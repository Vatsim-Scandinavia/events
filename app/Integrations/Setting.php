<?php

namespace App\Integrations;

/**
 * One value an admin enters for an Integration, such as its URL or API token.
 *
 * Setting it in the environment locks it: add it to config/integrations.php under the
 * Integration's key, read from $envVariable. Secrets are never sent back to the page.
 */
final readonly class Setting
{
    /**
     * @param  list<mixed>  $rules  Validation rules for a value that is entered.
     * @param  bool  $multiline  Entered in a text box, one entry per line.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $envVariable,
        public array $rules = ['string', 'max:255'],
        public bool $secret = false,
        public bool $required = true,
        public ?string $description = null,
        public bool $multiline = false,
    ) {}
}
