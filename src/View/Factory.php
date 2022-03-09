<?php
declare(strict_types=1);

namespace Schnoop\RemoteTemplate\View;

use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class Factory.
 */
class Factory extends \Illuminate\View\Factory
{
    /**
     * Normalize a view name.
     *
     * @param string $name
     *
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
