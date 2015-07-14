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

        if (!empty($title)) {
            fwrite($outputStream, $title);
        }

        if (!empty($output)) {
            if (!empty($title)) {
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
        $view = array(
            'title' => 'An error has occured',
            'output' => 'Enable debug mode for more details.',
        );

        $this->emitArray('render', array(&$view, $exception, $outputBuffer, $this));

        return array($view['title'], $view['output']);
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
        $view = array(
            'title' => 'An error has occured',
            'output' => Debug::renderException($exception, true, true),
        );

        $this->emitArray('render.debug', array(&$view, $exception, $outputBuffer, $this));

        return array($view['title'], $view['output']);
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
