<?php

namespace Kuria\Error\Screen;

use Kuria\Error\FatalErrorHandlerInterface;
use Kuria\Error\Util\Debug;
use Kuria\Event\EventEmitter;

/**
 * CLI error screen
 *
 * @emits render(array &$view, object $exception, string|null $outputBuffer, CliErrorScreen $screen)
 * @emits render.debug(array &$view, object $exception, string|null $outputBuffer, CliErrorScreen $screen)
 * 
 * @author ShiraNai7 <shira.cz>
 */
class CliErrorScreen extends EventEmitter implements FatalErrorHandlerInterface
{
    /** @var resource */
    protected $outputStream;

    public function handle($exception, $debug, $outputBuffer = null)
    {
        $outputStream = $this->getOutputStream();

        list($title, $output) = $debug
            ? $this->doRenderDebug($exception, $outputBuffer)
            : $this->doRender($exception, $outputBuffer)
        ;

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
     * @param object      $exception
     * @param string|null $outputBuffer
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
            'screen' => $this,
        ));

        return array($title, $output);
    }

    /**
     * Render the exception (debug)
     *
     * @param object      $exception
     * @param string|null $outputBuffer
     * @return array title, output
     */
    protected function doRenderDebug($exception, $outputBuffer = null)
    {
        $title = 'An error has occured';
        $output = Debug::renderException($exception, true, true);

        $this->emit('render.debug', array(
            'title' => &$title,
            'output' => &$output,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
            'screen' => $this,
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
