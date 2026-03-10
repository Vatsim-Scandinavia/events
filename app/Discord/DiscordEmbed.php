<?php

namespace App\Discord;

class DiscordEmbed
{
    protected array $embed = [];

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $this->embed['title'] = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->embed['description'] = $description;
        return $this;
    }

    public function color(int|string $color): self
    {
        $colors = [
            'green' => 0x41826e,
            'orange' => 0xff9800,
            'red' => 0xb63f3f,
            'blue' => 0x3498db,
        ];

        $this->embed['color'] = is_string($color)
            ? ($colors[$color] ?? $colors['blue'])
            : $color;

        return $this;
    }

    public function url(string $url): self
    {
        $this->embed['url'] = $url;
        return $this;
    }

    public function image(string $url): self
    {
        $this->embed['image'] = ['url' => $url];
        return $this;
    }

    public function footer(string $text): self
    {
        $this->embed['footer'] = [
            'text' => $text,
        ];

        return $this;
    }

    public function timestamp(\DateTimeInterface $time): self
    {
        $this->embed['timestamp'] = $time->format(\DateTimeInterface::ATOM);

        return $this;
    }

    public function toArray(): array
    {
        return $this->embed;
    }
}
