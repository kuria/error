<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

use Kuria\Debug\Exception;
use Kuria\Error\ErrorScreenInterface;
use Kuria\Event\Observable;

/**
 * @see CliErrorScreenEvents
 */
class CliErrorScreen extends Observable implements ErrorScreenInterface
{
    /** @var resource|null */
    private $outputStream;

    function render(\Throwable $exception, bool $debug, ?string $outputBuffer = null): void
    {
        $outputStream = $this->getOutputStream();

        [$title, $output] = $debug
            ? $this->doRenderDebug($exception)
            : $this->doRender($exception);

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
    private function doRender(\Throwable $exception): array
    {
        return ['An error has occured', Exception::render($exception, false)];
    }

    /**
     * Render the exception (debug)
     *
     * Returns a [title, output] tuple.
     */
    private function doRenderDebug(\Throwable $exception): array
    {
        return ['An error has occured', Exception::render($exception, true, true)];
    }

    /**
     * @return resource
     */
    function getOutputStream()
    {
        return $this->outputStream
            ?? (defined('STDERR') ? STDERR : null)
            ?? fopen('php://output', 'a');
    }

    /**
     * @param resource|null $outputStream
     */
    function setOutputStream($outputStream): void
    {
        $this->outputStream = $outputStream;
    }
}
