<?php

namespace Kuria\Error;

/**
 * Fatal error handler interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface FatalErrorHandlerInterface
{
    /**
     * Handle fatal script shutdown
     *
     * @param object      $exception    an exception instance
     * @param bool        $debug        debug mode 1/0
     * @param string|null $outputBuffer captured output buffer, if available
     */
    public function handle($exception, $debug, $outputBuffer = null);
}
