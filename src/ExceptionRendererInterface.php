<?php

namespace Kuria\Error;

/**
 * Exception renderer interface
 *
 * @author ShiraNai7 <shira.cz>
 */
interface ExceptionRendererInterface
{
    /**
     * Render the exception
     *
     * @param bool        $debug        debug mode 1/0
     * @param \Exception  $exception    an exception instance
     * @param string|null $outputBuffer captured output buffer, if available
     */
    public function render($debug, \Exception $exception, $outputBuffer = null);
}
