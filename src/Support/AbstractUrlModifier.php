<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\Support;

abstract class AbstractUrlModifier
{
    protected bool $breakTheCycle = false;

    abstract public function applicable(): bool;

    public function breakTheCycle(): bool
    {
        return $this->breakTheCycle;
    }

    abstract public function getQueryString(): bool;
}
