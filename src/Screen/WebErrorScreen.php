<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

use Kuria\Debug\Dumper;
use Kuria\Debug\Exception;
use Kuria\Error\ErrorScreenInterface;
use Kuria\Error\Exception\OutOfMemoryException;
use Kuria\Event\Observable;
use Kuria\PhpHighlighter\PhpHighlighter;

/**
 * @see WebErrorScreenEvents
 */
class WebErrorScreen extends Observable implements ErrorScreenInterface
{
    /** @var string */
    private $encoding = 'utf-8';

    /** @var string */
    private $htmlCharset = 'utf-8';

    /** @var int */
    private $maxOutputBufferLength = 102400; // 100kB

    /** @var int */
    private $maxCodePreviewFileSize = 524288; // 512kB

    /** @var int */
    private $uidSeq = 0;

    /** @var bool set in handle() based on error type */
    private $codePreviewEnabled;

    /**
     * Set encoding used to escape and dump values
     *
     * It must be supported by htmlentities() and the mb_*() functions.
     */
    function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }

    /**
     * Set charset of the HTML page
     */
    function setHtmlCharset(string $htmlCharset): void
    {
        $this->htmlCharset = $htmlCharset;
    }

    /**
     * Do not display output buffer larger than the specified number of bytes
     */
    function setMaxOutputBufferLength(int $maxOutputBufferLength): void
    {
        $this->maxOutputBufferLength = $maxOutputBufferLength;
    }

    /**
     * Do not render code preview for PHP scripts larger than the specified number of bytes
     */
    function setMaxCodePreviewFileSize(int $maxCodePreviewFileSize): void
    {
        $this->maxCodePreviewFileSize = $maxCodePreviewFileSize;
    }

    function render(\Throwable $exception, bool $debug, ?string $outputBuffer = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=' . $this->htmlCharset);
        }

        $this->codePreviewEnabled = !$exception instanceof OutOfMemoryException;

        [$title, $html] = $debug
            ? $this->doRenderDebug($exception, $outputBuffer)
            : $this->doRender($exception, $outputBuffer);

        $this->renderLayout($debug, $title, $html);
    }

    /**
     * Render the exception (non-debug)
     *
     * Returns a [title, html] tuple.
     */
    protected function doRender(\Throwable $exception, ?string $outputBuffer = null): array
    {
        $title = 'Internal server error';
        $heading = 'Internal server error';
        $text = 'Something went wrong while processing your request. Please try again later.';
        $extras = '';

        $this->emit(WebErrorScreenEvents::RENDER, [
            'title' => &$title,
            'heading' => &$heading,
            'text' => &$text,
            'extras' => &$extras,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
        ]);

        $output = <<<HTML
<div class="group">
    <i class="icon icon-error"></i>
    <div class="section major">
        <h1>{$heading}</h1>
        <p class="message">{$text}</p>
    </div>
</div>\n
HTML;

        if ($extras !== '') {
            $output .= "\n" . $extras;
        }

        return [$title, $output];
    }

    /**
     * Render the exception (debug)
     *
     * Returns a [title, html] tuple.
     */
    protected function doRenderDebug(\Throwable $exception, ?string $outputBuffer = null): array
    {
        $title = Exception::getName($exception);
        $extras = '';

        $this->emit(WebErrorScreenEvents::RENDER_DEBUG, [
            'title' => &$title,
            'extras' => &$extras,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
        ]);

        $output = '';
        $chain = Exception::getChain($exception);
        $totalExceptions = count($chain);

        for ($i = 0; $i < $totalExceptions; ++$i) {
            $output .= $this->renderException($chain[$i], $i, $totalExceptions);

            if ($i === 0) {
                // render extras after the first exception
                $output .= $extras;
                $output .= $this->renderOutputBuffer($outputBuffer);
                $output .= $this->renderPlaintextTrace($exception);
            }
        }

        return [$title, $output];
    }

    /**
     * Render the HTML layout
     */
    protected function renderLayout(bool $debug, string $title, string $content): void
    {
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="<?= $this->escape(strtolower($this->htmlCharset)) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($debug) : ?>
<link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4gELDAY3msRFdQAAAUZJREFUWMPllztSwzAQhj9pmIE2OQO5QLgHDEtNzpG7uMlAlQLzuEe4AJzBaUnlFBEMD1lvYWbYxg9ZK+2/+3lt+O+mUie2h8OVubyTMXbfQtdCl+NDZyy+BCbAxJz/TgqM9MfA27ehE2AntRUwCzSWoUZqK2CiPwVeBh6ZAa9SSwHjeO14ZC21FDDRnwOPQG+Z+37vAniSSinoTOVz2fdfxu7Vh6utwLR4Cj5h57MoLFUqdg4ForDUGdj5LAhLlYpdgAJBWOpM7HzmxfIoALt5xgbm7cHHIJbaE/2qQNNcJaUgArssLFVEt0spQi+WaiD6G+Da4cz1Kh6yW4GFU4GAbpezASuWthRsQio/IQUAzwJnVgwLYReNpa6AXRSWugJ2UViqUOwKFeEPLHVit1MFvrAbMQqEYFfLZhp4GPHXcMy1/4jtATk5XgJfpXWMAAAAAElFTkSuQmCC">
<?php endif ?>
<style>
<?php $this->renderCss($debug) ?>
</style>
<title><?= $this->escape($title) ?></title>
</head>

<body>

    <div id="wrapper">

        <div id="content">

<?= $content ?>

        </div>

    </div>

<script>
<?php $this->renderJs($debug) ?>
</script>
</body>
</html><?php
    }

    protected function renderCss(bool $debug): void
    {
        readfile(__DIR__ . '/Resources/web_error_screen.css');

        if ($debug) {
            readfile(__DIR__ . '/Resources/web_error_screen_debug.css');
        }

        $this->emit(WebErrorScreenEvents::CSS, $debug);
    }

    protected function renderJs(bool $debug): void
    {
        if ($debug) {
            readfile(__DIR__ . '/Resources/web_error_screen_debug.js');
        }

        $this->emit(WebErrorScreenEvents::JS, $debug);
    }

    /**
     * Escape the given string string for HTML
     */
    protected function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_IGNORE, $this->encoding);
    }

    protected function renderException(\Throwable $exception, int $index, int $total): string
    {
        $trace = $exception->getTrace();
        $isMain = $index === 0;
        $number = $index + 1;

        $title = Exception::getName($exception);
        $titleTag = $isMain ? 'h1' : 'h2';

        $message = '<p class="message">' . nl2br($this->escape($exception->getMessage()), false) . '</p>';
        $info = "\n<p>in <em>" . $this->renderFilePath($exception->getFile()) . "</em> on line <em>" . $exception->getLine() . "</em></p>";

        // title, message, file
        $html = <<<HTML
<div class="group exception text-break">
<i class="icon icon-warning"></i>
<div class="section major">
    <{$titleTag}><em>{$number}/{$total}</em> {$title}</{$titleTag}>
    {$message}{$info}
</div>\n
HTML;

        // code preview
        $codePreview = $this->renderCodePreview($exception->getFile(), $exception->getLine(), $isMain ? 5 : 3);
        if ($codePreview !== null) {
            $html .= "\n<div class=\"section\">{$codePreview}</div>\n";
            $codePreview = null;
        }

        // trace
        if ($trace) {
            $html .= $this->renderTrace($trace);
        }

        $html .= "</div>\n";

        return $html;
    }

    protected function renderTrace(array $trace): string
    {
        $traceCounter = count($trace) - 1;
        $html = <<<HTML
<div class="section">
    <h3>Stack trace</h3>
    <table class="trace">
        <tbody>\n
HTML;

        foreach ($trace as $frame) {
            $frameUid = $this->generateUid();
            $frameArgNum = isset($frame['args']) ? count($frame['args']) : 0;
            $renderExtras = true;

            // call
            if (isset($frame['type'], $frame['class'])) {
                $call = "{$frame['class']}{$frame['type']}";
                if ($frame['function'] === 'onError' && is_a($frame['class'], 'Kuria\Error\ErrorHandler', true)) {
                    $renderExtras = false;
                }
            } else {
                $call = '';
            }

            // file and line
            if (isset($frame['file'])) {
                $file = $this->renderFilePath($frame['file']);
                if (isset($frame['line']) && $frame['line'] > 0) {
                    $file .= " <em>({$frame['line']})</em>";
                }
            } else {
                $file = '-';
            }

            // do not render extras if there is nothing to be displayed
            if ($renderExtras && empty($frame['args']) && !isset($frame['file'], $frame['line'])) {
                $renderExtras = false;
            }

            // row attributes
            $rowAttrs = '';
            $rowClasses = 'trace';

            if ($renderExtras) {
                $rowAttrs .= " id=\"trace-{$frameUid}\" onclick=\"Kuria.Error.WebErrorScreen.toggleTrace({$frameUid})\"";
                $rowClasses .= ' expandable closed';
            }

            // row
            $html .= <<<HTML
<tr class="{$rowClasses}"{$rowAttrs}>
    <th>{$traceCounter}</th>
    <td><em>{$call}</em>{$frame['function']}<em>(#{$frameArgNum})</em></td>
    <td>{$file}</td>
</tr>\n
HTML;

            // extras
            if ($renderExtras) {
                $html .= <<<HTML
<tr class="trace-extra" id="trace-extra-{$frameUid}">
<td></td>
<td colspan="2">\n
HTML;

                // code preview
                if (isset($frame['file'], $frame['line'])) {
                    $html .= $this->renderCodePreview($frame['file'], $frame['line'], 3);
                }

                // arguments
                if (!empty($frame['args'])) {
                    $html .= $this->renderArguments($frame['args']);
                }

                $html .= "</td>\n</tr>\n";
            }

            --$traceCounter;
        }

        $html .= "</tbody>\n</table>\n</div>\n";

        return $html;
    }

    protected function renderArguments(array $args): string
    {
        $html = "<h4>Arguments</h4>\n<table class=\"argument-list\"><tbody>\n";
        for ($i = 0, $argCount = count($args); $i < $argCount; ++$i) {
            $html .= "<tr><th>{$i}</th><td><pre>" . $this->escape(Dumper::dump($args[$i], 2, 64, $this->encoding)) . "</pre></td></tr>\n";
        }
        $html .= "</tbody></table>\n";

        return $html;
    }

    protected function renderOutputBuffer(?string $outputBuffer): string
    {
        if ($outputBuffer === null || $outputBuffer === '') {
            return '';
        }

        // analyse value
        $message = null;
        $show = true;
        $enableShowAsHtml = true;
        if (strlen($outputBuffer) > $this->maxOutputBufferLength) {
            $message = 'The output buffer is too big to display.';
            $show = false;
        } elseif (preg_match('{[\\x00-\\x09\\x0B\\x0C\\x0E-\\x1F]}', $outputBuffer) !== 0) {
            $message = 'The output buffer contains unprintable characters. See HEX dump below.';
            $outputBuffer = Dumper::dumpStringAsHex($outputBuffer);
            $enableShowAsHtml = false;
        }

        // render
        $textareaId = null;
        $wrapperId = sprintf('output-buffer-wrapper-%d', $this->generateUid());

        if ($show) {
            $textareaId = sprintf('output-buffer-%d', $this->generateUid());
        }

        return "<div class=\"group\">
    <div class=\"section\">
        <h2 class=\"toggle-control closed\" onclick=\"Kuria.Error.WebErrorScreen.toggle('{$wrapperId}', this)\">Output buffer <em>(" . strlen($outputBuffer) . ")</em></h2>
        <div id=\"{$wrapperId}\" class=\"hidden\">"
        . ($message === null ? '' : "<p>{$message}</p>\n")
        . ($show ? "<textarea readonly id=\"{$textareaId}\" rows=\"10\" cols=\"80\">" . $this->escape($outputBuffer) . "</textarea>\n" : '')
        . ($show && $enableShowAsHtml ? "<p><a href=\"#\" onclick=\"Kuria.Error.WebErrorScreen.showTextareaAsHtml('{$textareaId}', this); return false;\">Show as HTML</a></p>\n" : '')
        . "</div>\n</div>\n</div>\n";
    }

    protected function renderPlaintextTrace(\Throwable $exception): string
    {
        $trace = Exception::render($exception, true, true);

        return <<<HTML
<div class="group">
    <div class="section">
        <h2 class="toggle-control closed" onclick="Kuria.Error.WebErrorScreen.toggle('plaintext-trace', this)">Plaintext trace</h2>
        <div id="plaintext-trace" class="hidden">
            <textarea readonly rows="10" cols="80" onclick="Kuria.Error.WebErrorScreen.selectTextareaContent(this)">{$this->escape($trace)}</textarea>
        </div>
    </div>
</div>\n
HTML;
    }

    protected function renderCodePreview(string $file, int $line, int $lineRange = 5): ?string
    {
        if (
            $this->codePreviewEnabled
            && is_file($file)
            && is_readable($file)
            && filesize($file) < $this->maxCodePreviewFileSize
        ) {
            return PhpHighlighter::file($file, $line, [-$lineRange, $lineRange], 'code-preview');
        }

        return null;
    }

    protected function renderFilePath(string $file): string
    {
        return $this->escape(str_replace('\\', '/', $file));
    }

    protected function generateUid(): int
    {
        return ++$this->uidSeq;
    }
}
