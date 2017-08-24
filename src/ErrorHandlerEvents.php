<?php declare(strict_types=1);

namespace Kuria\Error;

use Kuria\Error\Exception\ErrorException;

/**
 * @see ErrorHandler
 */
abstract class ErrorHandlerEvents
{
    /**
     * Emitted when a PHP error occurs.
     *
     * @param ErrorException $e
     * @param bool $debug
     */
    const ERROR = 'error';

    /**
     * Emitted when an uncaught exception is being handled.
     *
     * @param \Throwable $exception
     * @param bool $debug
     */
    const EXCEPTION = 'exception';

    /**
     * Emitted when the error handler fails to handle an exception.
     *
     * @param \Throwable $exception
     * @param bool $debug
     */
    const FAILURE = 'failure';
}
