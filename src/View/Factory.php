<?php

namespace Schnoop\RemoteTemplate\View;

use Illuminate\Support\Str;

/**
 * Class Factory.
 */
class Factory extends \Illuminate\View\Factory
{
    /**
     * Normalize a view name.
     *
     * @param  string  $name
     * @return string
     */
    protected function normalizeName($name): string
    {
        $delimiter = $this->container->get('config')->get('remote-view.remote-delimiter');

        if (Str::startsWith($name, $delimiter) === false) {
            return parent::normalizeName($name);
        }

        return $name;
    }
}
