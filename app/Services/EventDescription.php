<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Turns the Markdown staff write for an event into HTML that is safe to put on the page.
 *
 * Raw HTML and unsafe links are dropped. Images show as their alt text, since the content
 * security policy only lets the site load its own images.
 */
class EventDescription implements ExtensionInterface, NodeRendererInterface
{
    public static function toHtml(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Descriptions written before Markdown keep their line breaks.
            'renderer' => ['soft_break' => "<br>\n"],
        ], [new self]);
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(Image::class, $this, 10);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        return $childRenderer->renderNodes($node->children());
    }
}
