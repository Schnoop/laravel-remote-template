<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\Support;

class DefaultUrlModifier
{
    public function determine(string $url): string
    {
        return $url;
    }
}
