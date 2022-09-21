<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\Exceptions;

use Exception;
use Throwable;

class RemoteTemplateNotFoundException extends Exception
{

    /**
     * @var string
     */
    protected string $response;

    /**
     * @param string $message
     * @param int $code
     * @param string $response
     * @param Throwable|null $previous
     */
    public function __construct(
        string     $message,
        int        $code,
        string     $response,
        ?Throwable $previous
    )
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * This function narrows the return type from the parent class and does not allow it to be nullable.
     */
    public function getResponse(): string
    {
        return $this->response;
    }
}
