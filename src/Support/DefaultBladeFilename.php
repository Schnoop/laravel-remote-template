<?php

namespace Schnoop\RemoteTemplate\Support;

use Illuminate\Support\Str;

class DefaultBladeFilename
{
    public function determine(string $url): string
    {
        return Str::slug($url).'.blade.php';
    }
}
