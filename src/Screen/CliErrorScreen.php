<?php

namespace Kuria\Error\Screen;

use Kuria\Debug\Error;
use Kuria\Error\ExceptionHandlerInterface;
use Kuria\Event\EventEmitter;

/**
 * CLI error screen
 *
 * @emits render(array &$view, \Throwable $exception, string|null $outputBuffer, CliErrorScreen $screen)
 * @emits render.debug(array &$view, \Throwable $exception, string|null $outputBuffer, CliErrorScreen $screen)
 * 
 * @author ShiraNai7 <shira.cz>
 */
class CliErrorScreen extends EventEmitter implements ExceptionHandlerInterface
{
    /** @var resource */
    protected $outputStream;

    public function handle($exception, $errorType, $debug, $outputBuffer = null)
    {
        $outputStream = $this->getOutputStream();

        list($title, $output) = $debug
            ? $this->doRenderDebug($exception, $outputBuffer)
            : $this->doRender($exception, $outputBuffer);

        if ($title) {
            fwrite($outputStream, $title);
        }

        if ($output) {
            if ($title) {
                fwrite($outputStream, "\n\n");
            }

            fwrite($outputStream, $output);
        }
    }

    /**
     * Render the exception (non-debug)
     *
     * @param \Throwable|\Exception $exception
     * @param string|null $          outputBuffer
     * @return array title, output
     */
    protected function doRender($exception, $outputBuffer = null)
    {
        $title = 'An error has occured';
        $output = 'Enable debug mode for more details.';

        $this->emit('render', array(
            'title' => &$title,
            'output' => &$output,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
        ));

        return array($title, $output);
    }

    /**
     * Render the exception (debug)
     *
     * @param \Throwable|\Exception $exception
     * @param string|null           $outputBuffer
     * @return array title, output
     */
    protected function doRenderDebug($exception, $outputBuffer = null)
    {
        $title = 'An error has occured';
        $output = Error::renderException($exception, true, true);

        $this->emit('render.debug', array(
            'title' => &$title,
            'output' => &$output,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
        ));

        return array($title, $output);
    }

    /**
     * Get output stream used for rendering
     *
     * @return resource
     */
    public function getOutputStream()
    {
        if (null !== $this->outputStream) {
            $stream = $this->outputStream;
        } elseif (defined('STDERR')) {
            $stream = STDERR;
        } else {
            $stream = fopen('php://output', 'a');
        }

        return $stream;
    }

    /**
     * Set output stream used for rendering
     *
     * @param resource $outputStream
     */
    public function setOutputStream($outputStream)
    {
        $this->outputStream = $outputStream;
    }
}
