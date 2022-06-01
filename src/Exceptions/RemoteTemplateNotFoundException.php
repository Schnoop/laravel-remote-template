<?php

namespace Schnoop\RemoteTemplate\Exceptions;

use Exception;

/**
 * Class RemoteTemplateNotFoundException.
 */
class RemoteTemplateNotFoundException extends Exception
{

    /**
     * @var string
     */
    protected $response;

    /**
     * @param string $message
     * @param int $code
     * @param string $response
     * @param $previous
     */
    public function __construct(
        string $message,
        int    $code,
        string $response,
               $previous
    )
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * This function narrows the return type from the parent class and does not allow it to be nullable.
     */
    public function getResponse()
    {
        return $this->response;
    }
}
