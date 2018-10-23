<?php

namespace Antwerpes\RemoteView\View;

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
     */
    protected function normalizeName($name)
    {
        $delimiter = FileViewFinder::REMOTE_PATH_DELIMITER;

        if (strpos($name, $delimiter) === false) {
            return parent::normalizeName($name);
        }

        return $name;
    }
}
