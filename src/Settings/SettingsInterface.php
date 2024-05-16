<?php

declare(strict_types=1);

namespace Bouncer\Settings;

interface SettingsInterface
{
    public function get(string $key = '', mixed $default = null): mixed;

    public function has(string $key = ''): bool;

    public function if(string $key): bool;

    public function set(string $key, mixed $value): self;
}
