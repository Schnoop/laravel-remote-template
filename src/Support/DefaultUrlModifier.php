<?php

namespace Schnoop\RemoteTemplate\Support;

class DefaultUrlModifier
{
    public function determine(string $url): string
    {
        return $url;
    }
}
