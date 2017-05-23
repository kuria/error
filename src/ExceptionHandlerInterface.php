<?php

namespace Kuria\Error;

/**
 * Exception handler interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface ExceptionHandlerInterface
{
    /**
     * Handle an exception
     *
     * @param \Throwable|\Exception $exception    an exception instance
     * @param int                   $errorType    error type, {@see ErrorHandler} constants
     * @param bool                  $debug        debug mode 1/0
     * @param string|null           $outputBuffer captured output buffer, if available
     */
    public function handle($exception, $errorType, $debug, $outputBuffer = null);
}
