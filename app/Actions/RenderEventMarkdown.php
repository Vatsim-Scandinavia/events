<?php

namespace App\Actions;

use Illuminate\Support\Str;

class RenderEventMarkdown
{
    public function handle(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);
    }
}
