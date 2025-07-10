<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\Support;

class DefaultResponseHandler
{
    public function determine(string $url): string
    {
        return $url;
    }
}
