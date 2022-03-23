<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\View;

use Illuminate\Support\Str;
use Illuminate\View\Factory as LaravelFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Factory extends LaravelFactory
{
    /**
     * Normalize a view name.
     *
     * @param string $name
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function normalizeName($name): string
    {
        $delimiter = $this->container->get('config')->get('remote-view.remote-delimiter');

        if (! Str::startsWith($name, $delimiter)) {
            return parent::normalizeName($name);
        }

        return $name;
    }
}
