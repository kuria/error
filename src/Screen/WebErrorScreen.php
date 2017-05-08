<?php

namespace Kuria\Error\Screen;

use Kuria\Error\FatalErrorHandlerInterface;
use Kuria\Error\Util\Debug;
use Kuria\Error\Util\PhpCodePreview;
use Kuria\Event\EventEmitter;

/**
 * Web error screen
 *
 * @emits render(array $event) when rendering in non-debug mode, {@see doRender()}
 * @emits render.debug(array $event) when rendering in debug mode, {@see doRenderDebug()}
 * @emits layout.css(array $event) when compiling CSS styles, {@see getLayoutCss()}
 * @emits layout.js(array $event) when compiling JS code, {@see getLayoutJs()}
 * 
 * @author ShiraNai7 <shira.cz>
 */
class WebErrorScreen extends EventEmitter implements FatalErrorHandlerInterface
{
    /** @var string */
    protected $encoding = 'UTF-8';
    /** @var string */
    protected $htmlCharset = 'UTF-8';
    /** @var int */
    protected $maxOutputBufferLength = 102400; // 100kB
    /** @var int */
    protected $maxCodePreviewFileSize = 524288; // 512kB
    /** @var int */
    protected $uidSeq = 0;

    /**
     * Set encoding used to escape and dump values
     *
     * Is must be supported by htmlentities() and the mb_*() functions.
     *
     * @param string $encoding
     * @return static
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        
        return $this;
    }

    /**
     * Set charset of the HTML page
     *
     * @param string $htmlCharset
     * @return static
     */
    public function setHtmlCharset($htmlCharset)
    {
        $this->htmlCharset = $htmlCharset;

        return $this;
    }

    /**
     * @param int $maxOutputBufferLength bytes
     * @return static
     */
    public function setMaxOutputBufferLength($maxOutputBufferLength)
    {
        $this->maxOutputBufferLength = $maxOutputBufferLength;

        return $this;
    }

    /**
     * @param int $maxCodePreviewFileSize bytes
     * @return static
     */
    public function setMaxCodePreviewFileSize($maxCodePreviewFileSize)
    {
        $this->maxCodePreviewFileSize = $maxCodePreviewFileSize;

        return $this;
    }

    public function handle($exception, $debug, $outputBuffer = null)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=' . $this->htmlCharset);
        }
        
        list($title, $html) = $debug
            ? $this->doRenderDebug($exception, $outputBuffer)
            : $this->doRender($exception, $outputBuffer)
        ;

        // render layout
        $this->renderLayout($debug, $title, $html);
    }

    /**
     * Render the exception (non-debug)
     *
     * @param object      $exception
     * @param string|null $outputBuffer
     * @return array title, html
     */
    protected function doRender($exception, $outputBuffer = null)
    {
        $title = 'Internal server error';
        $heading = 'Internal server error';
        $text = 'Something went wrong while processing your request. Please try again later.';
        $extras = '';

        $this->emit('render', array(
            'title' => &$title,
            'heading' => &$heading,
            'text' => &$text,
            'extras' => &$extras,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
            'screen' => $this,
        ));

        $output = <<<HTML
<div class="group">
    <i class="icon icon-error"></i>
    <div class="section major">
        <h1>{$heading}</h1>
        <p class="message">{$text}</p>
    </div>
</div>\n
HTML;

        if ('' !== $extras) {
            $output .= "\n" . $extras;
        }

        return array($title, $output);
    }

    /**
     * Render the exception (debug)
     *
     * @param object      $exception
     * @param string|null $outputBuffer
     * @return array title, html
     */
    protected function doRenderDebug($exception, $outputBuffer = null)
    {
        $title = Debug::getExceptionName($exception);
        $extras = '';

        $this->emit('render.debug', array(
            'title' => &$title,
            'extras' => &$extras,
            'exception' => $exception,
            'output_buffer' => $outputBuffer,
            'screen' => $this,
        ));

        $output = '';
        $chain = Debug::getExceptionChain($exception);
        $totalExceptions = sizeof($chain);

        for ($i = 0; $i < $totalExceptions; ++$i) {
            $output .= $this->renderException($chain[$i], $i, $totalExceptions);

            if (0 === $i) {
                // render extras after the first exception
                $output .= $extras;
                $output .= $this->renderOutputBuffer($outputBuffer);
                $output .= $this->renderPlaintextTrace($exception);
            }
        }

        return array($title, $output);
    }

    /**
     * Render the HTML layout
     *
     * @param bool   $debug
     * @param string $title   main title
     * @param string $content html content
     */
    public function renderLayout($debug, $title, $content)
    {
        $js = $this->getLayoutJs($debug);
        $css = $this->getLayoutCss($debug);

        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="<?php echo $this->escape($this->htmlCharset) ?>">
<style type="text/css">
<?php echo $css ?>
</style>
<title><?php echo $this->escape($title) ?></title>
</head>

<body>

    <div id="wrapper">

        <div id="content">

<?php echo $content ?>

        </div>

    </div>

<?php if ('' !== $js): ?>
<script type="text/javascript">
<?php echo $js ?>
</script>
<?php endif ?>
</body>
</html><?php
    }

    /**
     * Get CSS for the HTML layout
     *
     * @param bool $debug
     * @return string
     */
    public function getLayoutCss($debug)
    {
        ob_start();

        ?>
* {margin: 0; padding: 0;}
body {background-color: #56708a; font-family: 'Trebuchet MS', 'Geneva CE', lucida, sans-serif; font-size: 13px;}
h1, h2, h3, h4 {font-weight: normal;}
h1 {font-size: 2em;}
h2 {font-size: 1.5em;}
h3 {font-size: 1.2em; margin: 0.5em 0; color: #999;}
h4 {font-size: 1.1em; margin: 0.5em 0; color: #999;}
h1 em, h2 em {font-size: 0.9em;}
p {line-height: 1.4; margin: 0.5em 0;}
p.message {font-size: 15px;}
ul, ol {margin: 0.5em 0; padding-left: 3em;}
em {color: #777;}
table {border-collapse: collapse;}
td, th {padding: 0.5em 1em; border: 1px solid #ddd;}
th {background-color: #eee;}
td {background-color: #fff;}
hr {border: 0; border-bottom: 1px solid #999;}
a {color: #28639e; text-decoration: none;}
a:hover {color: #000; text-decoration: underline;}
a:active {color: #f00;}

#wrapper {margin: 0 auto; padding: 0 1em; overflow: hidden; max-width: <?php echo $debug ? '1200' : '700' ?>px;}

div.section {position: relative;}
div.section.major h2 {font-size: 2em;}
div.group {margin: 1.5em 0; padding: 2em; background-color: #fafafa; border: 1px solid #aaa; border-radius: 10px; box-shadow: 0 0 5px rgba(0, 0, 0, 0.2); zoom: 1;}
div.group > div.section {margin-top: 1em;}
div.group > div.section:first-of-type {margin-top: 0;}

i.icon {float: right; margin: 10px 0 15px 15px;}
/* Warning icon by by Yannick from http://www.flaticon.com */
i.icon.icon-warning:after {content: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAAA2CAQAAADdG1eJAAACY0lEQVR4AcXXz2sUdxjA4Wcnu4fN+iOBEoKJbM1ViChaJeDFi0IELREShRY8tAElksXtUVmUZd1Ts5gEYu+19NJehBx6tCd7ESneBVF6bi8t2F56WJZhnfnuzOwz/8BnXnhfZgg3o+O11zpmjMGUl/79/3lpSqBImIpnFgEseqZSbMBdS/otuVtkwDH3DbrvWHEBu6oGVe0WFbDqkjiXrBYRcNgW4m05nH9AxyzizerkHXDWumHWnc0zoGxPZJjInnJ+AZtO+JgTNvMKqGtJoqWeT8C2miRqtvMIWHFZUpetZB1wUE8aPQezDWibk8acdpYBp90W52fnnfeTOLedzipgQvz2/2HVc8+teif+IkxkE7DhlDg/+hv843txTtnIImDeQ/H+AvCneA/Njx7w2AGhDng8asAVV43iqiuJAsLfIMkEwwMeOGpURz0IDTjpjizccTJBQOgeB9yRhAG3nJGVM26lDTiiLUttR9IF9BySpUN6aQKWXZO1a5aTBkzakYcdk8kCWuryUNdKErCoIS8Nix8LKKX4rp8DMJ/iv6I0PGDdOUmtmAZT1iR1zvqwgFmPJFf1QlPTb2qSe2RWn5J+T63J3w+ux0/gojVFWHMxLqBqV1F2VQcDuGdBURbcGww4rinEG2+EaDreH1CypyK9Ly1Y8IX0KvaUoAS+8kR6L3wG+NWS9L72HRFmdIV4B+CtEF0zROiaFmLZp6DucyGmdSn7xA1hJvzuKa4rC3PDNyWbvjU+jchN43QzUjNOk5FXxulV5BfjtB/Z0fDBOHyw4UmELRd07HuvKO/t67hgm/8Ag6dqX/zuTucAAAAASUVORK5CYII="); opacity: 0.2;}
/* Icon made by Google from www.flaticon.com is licensed under CC BY 3.0 */
i.icon.icon-error {margin-top: 0;}
i.icon.icon-error:after {content: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAMAAACdt4HsAAAAA3NCSVQICAjb4U/gAAAACXBIWXMAAAG9AAABvQG676d5AAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAAJNQTFRF////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAKxUt3AAAADB0Uk5TAAEGCxUYGzI1QEZNVFdYWmJjaXB/gYqYoqOksrO7vsLDys7P0Njd3uPm7PL19/3+0pdPoQAAAYxJREFUWMO1l2t3gjAMhouIiuANhDlUpk5AxWL+/6+bw21HJw1psz1fe/KeNklzEUKB7YVxkmZlmaVJHHq20KI7XUp4QC6nXap1J9hU0ED1FnQI5takACXFxGqzH+4AZTdEzXtraGXdU9sPjkDgOFDZjyWQkONm70VAJmrwpfUKGiyeFSLQYv70fj17uPi//C81BeDcf4j/EbQ5OHcCazBgdZe/YIT7E0FV/u9v53vF8fY7lhMwE4DR1/8vTAXyW30IwFQAgvp8Yy6wqetfZS5QfdbJGZgLwOx6vOIIXJPJlhwBaQsPSbX3VgHwRAicG0AoYp5ALBKeQCJSnkAqMp5AJkqeQMkXYD+B7UQsjHJfI9EwxsAiRlOZQIh+JgIe+p0Jo4KNFhRad0JK2uml5oSXNHZRZZd1fmNhtzZ2c+W3d/aAwR9x+EMWe8zjD5r8UVdYC+awLaz5hXz/efPq5J+J/vNVK0v/QIpfX700OYTytHLQvc3d4uZbt3XxHOVq83xk/f/q+wfLt976/wGiWzUN96x4SwAAAABJRU5ErkJggg=="); opacity: 0.2;}

@media (max-width: 500px) {
    i.icon {display: none;}
    div.section.has-icon {margin-right: 0;}
}
<?php if ($debug): ?>
pre {overflow-y: auto; max-height: 300px; line-height: 1.4; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; white-space: pre-wrap; word-break: break-all; word-wrap: break-word;}
textarea, iframe {display: block; box-sizing: border-box; -moz-box-sizing: border-box; -webkit-box-sizing: border-box;}
textarea {width: 100%; margin: 1em 0; padding: 0.5em; border: 1px solid #aaa;}
iframe {margin: 1em 0; border: 1px solid #aaa; width: 100%; height: 500px; background-color: #fff;}
ol.code-preview {overflow-x: auto; padding-left: 60px; border: 1px solid #ddd; line-height: 1.5; font-family: monospace; background-color: #eee;}
ol.code-preview > li {background-color: #fff; padding-left: 10px;}
ol.code-preview > li.active {background-color: #ebf4ff;}

table.trace {width: 100%;}
table.trace > tbody > tr.trace > th {width: 1%;}
table.trace > tbody > tr.trace.expandable > td {cursor: pointer;}
table.trace > tbody > tr.trace.expandable:hover > td, table.trace > tbody > tr.trace.open > td {background-color: #ebf4ff;}
table.trace > tbody > tr.trace-extra {display: none;}

table.argument-list {margin-top: 0.5em; width: 100%;}
table.argument-list:first-child {margin: 0;}
table.argument-list > tbody > tr > th,
table.argument-list > tbody > tr > th {width: 1%;}

.toggle-control {cursor: pointer; position: relative;}
.toggle-control:hover {text-decoration: underline;}
.toggle-control:after {display: inline-block; margin-left: 5px; font-size: 0.6em; color: #777; vertical-align: middle; text-decoration: none;}
.toggle-control.closed:after {content: "▼";}
.toggle-control.open:after {content: "▲";}

div.section > h2.toggle-control:before {content: ""; position: absolute; left: -26px; top: -26px; width: 100%; height: 100%; padding: 26px;}
div.section > h2.toggle-control.open:before {padding-bottom: 0;}

pre.context {padding: 10px; border: 1px solid #ddd; background-color: #fff;}
.hidden {display: none;}

@media (max-width: 768px) {
    table.trace td, table.trace th {display: block; width: auto !important;}
    table.trace > tbody > tr > th, table.trace > tbody > tr > td {border-top-width: 0;}
    table.trace > tbody > tr:first-child > th:first-child {border-top-width: 1px;}
    table.trace > tbody > tr > th:before {content: "Frame ";}
    table.argument-list th:before {content: "Argument #";}
    table.argument-list > tbody > tr > th, table.argument-list > tbody > tr > td {border-top-width: 0;}
    table.argument-list > tbody > tr:first-child > th:first-child {border-top-width: 1px;}
}
<?php endif ?>
<?php

        $css = ob_get_clean();

        $this->emit('layout.css', array(
            'css' => &$css,
            'debug' => $debug,
            'screen' => $this,
        ));

        return $css;
    }

    /**
     * Get javascript for the HTML layout
     *
     * @param bool $debug
     * @return string
     */
    public function getLayoutJs($debug)
    {
        ob_start();

        if ($debug) {
        ?>
var Kuria;
(function (Kuria) {
    (function (Error) {
        function blurSelectedTextarea()
        {
            delete this.dataset.selected;
        }

        Error.WebErrorScreen = {
            toggle: function (elementId, control) {
                var element = document.getElementById(elementId);
                
                if (element) {
                    if ('' === element.style.display || 'none' === element.style.display) {
                        element.style.display = 'block';
                        control.className = 'toggle-control open';
                    } else {
                        element.style.display = 'none';
                        control.className = 'toggle-control closed';
                    }
                }
            },

            toggleTrace: function (traceId) {
                var trace = document.getElementById('trace-' + traceId);
                var traceExtra = document.getElementById('trace-extra-' + traceId);

                if (trace && traceExtra) {
                    if ('' === traceExtra.style.display || 'none' === traceExtra.style.display) {
                        // show
                        trace.className = 'trace expandable open';
                        try {
                            traceExtra.style.display = 'table-row';
                        } catch (e) {
                            // IE7 :)
                            traceExtra.style.display = 'block';
                        }
                    } else {
                        // hide
                        trace.className = 'trace expandable closed';
                        traceExtra.style.display = 'none';
                    }
                }
            },

            showTextareaAsHtml: function (textareaId, link) {
                var textarea = document.getElementById(textareaId),
                    iframe = document.getElementById('html-preview-' + textareaId)
                ;

                if (textarea) {
                    if (iframe) {
                        iframe.parentNode.removeChild(iframe);
                        link.textContent = 'Show as HTML';
                    } else {
                        iframe = document.createElement('iframe');
                        iframe.src = 'about:blank';
                        iframe.id = 'html-preview-' + textareaId;

                        iframe = textarea.parentNode.insertBefore(iframe, textarea.nextSibling);

                        iframe.contentWindow.document.open('text/html');
                        iframe.contentWindow.document.write(textarea.value);
                        iframe.contentWindow.document.close();

                        link.textContent = 'Hide';
                    }
                }
            },

            selectTextareaContent: function (textarea) {
                if (textarea.dataset) {
                    if (!textarea.dataset.selectInitialized) {
                        textarea.addEventListener('blur', blurSelectedTextarea);

                        textarea.dataset.selectInitialized = 1;
                    }

                    if (!textarea.dataset.selected) {
                        textarea.select();
                        textarea.dataset.selected = 1;
                    }
                } else {
                    // old browsers
                    textarea.select();
                }
            }
        };

    })(Kuria.Error || (Kuria.Error = {}));
})(Kuria || (Kuria = {}));
<?php
        }

        $js = ob_get_clean();

        $this->emit('layout.js', array(
            'js' => &$js,
            'debug' => $debug, 
            'screen' => $this,
        ));

        return $js;
    }

    /**
     * Escape the given string string for HTML
     *
     * @param string $string the string to escape
     * @return string html
     */
    public function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_IGNORE, $this->encoding);
    }

    /**
     * Render an exception
     *
     * @param object $exception the exception instance
     * @param int    $index     index of the current exception
     * @param int    $total     total number of exceptions
     * @return string html
     */
    public function renderException($exception, $index, $total)
    {
        $trace = $exception->getTrace();
        $isMain = 0 === $index;
        $number = $index + 1;

        $title = Debug::getExceptionName($exception);
        $titleTag = $isMain ? 'h1' : 'h2';

        $message = '<p class="message">' . nl2br($this->escape($exception->getMessage()), false) . '</p>';
        $info = "\n<p>in <em>" . $this->renderFilePath($exception->getFile()) . "</em> on line <em>" . $exception->getLine() . "</em></p>";

        // title, message, file
        $html = <<<HTML
<div class="group exception">
<i class="icon icon-warning"></i>
<div class="section major">
    <{$titleTag}><em>{$number}/{$total}</em> {$title}</{$titleTag}>
    {$message}{$info}
</div>\n
HTML;

        // code preview
        $codePreview = $this->renderCodePreview($exception->getFile(), $exception->getLine(), $isMain ? 5 : 3);
        if (null !== $codePreview) {
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

    /**
     * Render trace
     *
     * @param array $trace the trace array
     * @return string html
     */
    public function renderTrace(array $trace)
    {
        $traceCounter = sizeof($trace) - 1;
        $html = <<<HTML
<div class="section">
    <h3>Stack trace</h3>
    <table class="trace">
        <tbody>\n
HTML;

        foreach ($trace as $frame) {
            $frameUid = $this->getUid();
            $frameArgNum = isset($frame['args']) ? sizeof($frame['args']) : 0;
            $renderExtras = true;

            // call
            if (isset($frame['type'], $frame['class'])) {
                $call = "{$frame['class']}{$frame['type']}";
                if ('onError' === $frame['function'] && is_a($frame['class'], 'Kuria\Error\ErrorHandler', true)) {
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

    /**
     * Render arguments
     *
     * @param array $args array of arguments
     * @return string html
     */
    public function renderArguments(array $args)
    {
        $html = "<h4>Arguments</h4>\n<table class=\"argument-list\"><tbody>\n";
        for ($i = 0, $argCount = sizeof($args); $i < $argCount; ++$i) {
            $html .= "<tr><th>{$i}</th><td><pre>" . $this->escape(Debug::dump($args[$i], 2, 64, $this->encoding)) . "</pre></td></tr>\n";
        }
        $html .= "</tbody></table>\n";

        return $html;
    }

    /**
     * Render output buffer
     *
     * @param string|null $outputBuffer
     * @return string html
     */
    public function renderOutputBuffer($outputBuffer)
    {
        if (null === $outputBuffer || '' === $outputBuffer) {
            return '';
        }

        // analyse value
        $message = null;
        $show = false;
        if (strlen($outputBuffer) > $this->maxOutputBufferLength) {
            $message = 'The output buffer is too big to display.';
        } elseif (0 !== preg_match('/[\\x00-\\x09\\x0B\\x0C\\x0E-\\x1F]/', $outputBuffer)) {
            $message = 'The output buffer contains unprintable characters.';
        } else {
            $rows = 1 + min(10, preg_match_all('/\r\n|\n|\r/', $outputBuffer, $matches));
            $show = true;
        }

        // render
        if ($show) {
            $textareaId = sprintf('output-buffer-%d', $this->getUid());
        }
        $wrapperId = sprintf('output-buffer-wrapper-%d', $this->getUid());

        return "<div class=\"group\">
    <div class=\"section\">
        <h2 class=\"toggle-control closed\" onclick=\"Kuria.Error.WebErrorScreen.toggle('{$wrapperId}', this)\">Output buffer <em>(" . strlen($outputBuffer) . ")</em></h2>
        <div id=\"{$wrapperId}\" class=\"hidden\">"
        . (null === $message ? '' : "<p>{$message}</p>\n")
        . ($show ? "<textarea readonly id=\"{$textareaId}\" rows=\"{$rows}\" cols=\"80\">" . $this->escape($outputBuffer) . "</textarea>\n" : '')
        . ($show ? "<p><a href=\"#\" onclick=\"Kuria.Error.WebErrorScreen.showTextareaAsHtml('{$textareaId}', this); return false;\">Show as HTML</a></p>\n" : '')
        . "</div>\n</div>\n</div>\n";
    }

    /**
     * Render a plaintext exception trace
     *
     * @param object $exception
     * @return string
     */
    public function renderPlaintextTrace($exception)
    {
        $trace = rtrim(Debug::renderException($exception, true, true));
        $rows = 1 + min(10, preg_match_all('/\r\n|\n|\r/', $trace, $matches));

        return <<<HTML
<div class="group">
    <div class="section">
        <h2 class="toggle-control closed" onclick="Kuria.Error.WebErrorScreen.toggle('plaintext-trace', this)">Plaintext trace</h2>
        <div id="plaintext-trace" class="hidden">
            <textarea readonly rows="{$rows}" cols="80" onclick="Kuria.Error.WebErrorScreen.selectTextareaContent(this)">{$this->escape($trace)}</textarea>
        </div>
    </div>
</div>\n
HTML;
    }

    /**
     * Render code preview
     *
     * @param string $file
     * @param int    $line
     * @param int    $lineRange
     * @return string|null
     */
    protected function renderCodePreview($file, $line, $lineRange = 5)
    {
        if (is_file($file) && filesize($file) < $this->maxCodePreviewFileSize) {
            return PhpCodePreview::file($file, $line, $lineRange);
        }
    }

    /**
     * Render file path
     *
     * @param string $file
     * @return string html
     */
    protected function renderFilePath($file)
    {
        return $this->escape(str_replace('\\', '/', $file));
    }

    /**
     * Get unique ID
     *
     * @return int
     */
    protected function getUid()
    {
        return ++$this->uidSeq;
    }
}
