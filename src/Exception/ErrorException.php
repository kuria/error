<?php declare(strict_types=1);

namespace Kuria\Error\Exception;

/**
 * Thrown in place of PHP errors, warnings, notices, etc. by the error handler
 */
class ErrorException extends \ErrorException implements ExceptionInterface
{
    /** @var bool */
    private $suppressed;

    function __construct(
        string $message = '',
        int $severity = E_ERROR,
        bool $suppressed = false,
        string $filename = __FILE__,
        int $line = __LINE__,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $severity, $filename, $line, $previous);

        $this->suppressed = $suppressed;
    }

    /**
     * See if the error is suppressed
     */
    function isSuppressed(): bool
    {
        return $this->suppressed;
    }

    /**
     * Make the error suppressed
     */
    function suppress(): void
    {
        $this->suppressed = true;
    }

    /**
     * Make the error unsuppressed
     */
    function force()
    {
        $this->suppressed = false;
    }
}
