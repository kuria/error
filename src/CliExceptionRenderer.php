<?php

namespace Kuria\Error;

/**
 * CLI exception renderer
 *
 * @author ShiraNai7 <shira.cz>
 */
class CliExceptionRenderer implements ExceptionRendererInterface
{
    public function render($debug, \Exception $exception, $outputBuffer = null)
    {
        fwrite(STDERR, 'An error has occurred');

        if ($debug) {
            fwrite(STDERR, "\n\n");
            fwrite(STDERR, DebugUtils::printException($exception, true, true));
        }
    }
}
