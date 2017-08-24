<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

use Kuria\Debug\Error;
use Kuria\Error\ErrorScreenInterface;
use Kuria\Event\Observable;

/**
 * @see CliErrorScreenEvents
 */
class CliErrorScreen extends Observable implements ErrorScreenInterface
{
    /** @var resource|null */
    protected $outputStream;

    function render(\Throwable $exception, bool $debug, ?string $outputBuffer = null): void
    {
        $outputStream = $this->getOutputStream();

        [$title, $output] = $debug
            ? $this->doRenderDebug($exception, $outputBuffer)
            : $this->doRender($exception, $outputBuffer);

        $this->emit($debug ? CliErrorScreenEvents::RENDER_DEBUG : CliErrorScreenEvents::RENDER, [
            'title' => &$title,
            'output' => &$output,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
        ]);

        if ($title !== '') {
            fwrite($outputStream, $title);
        }

        if ($output !== '') {
            if ($title !== '') {
                fwrite($outputStream, "\n\n");
            }

            fwrite($outputStream, $output);
        }

        if ($title !== '' || $output !== '') {
            fwrite($outputStream, "\n");
        }
    }

    /**
     * Render the exception (non-debug)
     *
     * Returns a [title, output] tuple.
     */
    protected function doRender(\Throwable $exception, ?string $outputBuffer = null): array
    {
        return ['An error has occured', 'Enable debug mode for more details.'];
    }

    /**
     * Render the exception (debug)
     *
     * Returns a [title, output] tuple.
     */
    protected function doRenderDebug(\Throwable $exception, ?string $outputBuffer = null): array
    {
        return ['An error has occured', Error::renderException($exception, true, true)];
    }

    /**
     * @return resource
     */
    function getOutputStream()
    {
        if ($this->outputStream !== null) {
            $stream = $this->outputStream;
        } elseif (defined('STDERR')) {
            $stream = STDERR;
        } else {
            $stream = fopen('php://output', 'a');
        }

        return $stream;
    }

    /**
     * @param resource $outputStream
     */
    function setOutputStream($outputStream): void
    {
        $this->outputStream = $outputStream;
    }
}
