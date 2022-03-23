<?php

namespace Schnoop\RemoteTemplate\Support;

class DefaultResponseHandler
{
    public function determine(string $url): string
    {
        return $url;
    }
}
