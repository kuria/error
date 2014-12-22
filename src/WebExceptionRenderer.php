<?php

namespace Kuria\Error;

use Kuria\Highlighter\Highlighter;
use Kuria\Highlighter\PhpHighlighter;

/**
 * Web error renderer
 *
 * @author ShiraNai7 <shira.cz>
 */
class WebExceptionRenderer implements ExceptionRendererInterface
{
    /** @var string title used if debug = false */
    public $title = 'Internal server error';
    /** @var string text used if debug = false */
    public $text = 'Something went wrong while processing your request. Please try again later.';

    /** @var string extra HTML put after the main error description */
    public $extraHtml = '';
    /** @var bool suppress default HTML output */
    public $replaceHtml = false;

    /** @var string extra CSS put after the main styles */
    public $extraCss = '';
    /** @var bool disable default CSS styles */
    public $replaceCss = false;

    /** @var string extra JS put after the main javascripts */
    public $extraJs = '';
    /** @var bool disable default javascripts */
    public $replaceJs = false;

    /** @var int|null override layout flags */
    public $layoutFlags = null;

    /** Code preview - max file size */
    const CODE_PREVIEW_MAX_FILE_SIZE = 2097152; // 2MB
    /** Flag - narrow wrapper */
    const FLAG_NARROW_WRAPPER = 1;
    /** Flag - enable debug assets */
    const FLAG_DEBUG_ASSETS = 2;

    /** @var PhpHighlighter|null */
    protected $highlighter;
    /** @var int */
    protected $uidSeq = 0;

    public function render($debug, \Exception $exception, $outputBuffer = null)
    {
        $html = '';
        $flags = 0;

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (!$this->replaceHtml) {
            if ($debug) {
                // debug
                $flags |= self::FLAG_DEBUG_ASSETS;

                // get title
                $title = DebugUtils::getExceptionName($exception);

                // render exceptions
                $chain = DebugUtils::getExceptionChain($exception);
                $totalExceptions = sizeof($chain);

                for ($i = 0; $i < $totalExceptions; ++$i) {
                    $html .= $this->renderException($chain[$i], $i, $totalExceptions);

                    if (0 === $i) {
                        // render extras after the first exception
                        $html .= $this->extraHtml;
                        $html .= $this->renderOutputBuffer($outputBuffer);
                    }
                }
            } else {
                // non-debug
                $title = $this->title;
                $flags |= self::FLAG_NARROW_WRAPPER;

                $html = "<div class=\"section major standalone has-icon icon-warning\">
<h1>{$this->title}</h1>
<p>{$this->text}</p>
</div>\n";
                $html .= $this->extraHtml;
            }
        } else {
            $html = $this->extraHtml;
        }

        if (null !== $this->layoutFlags) {
            $flags = $this->layoutFlags;
        }

        // render layout
        $this->renderLayout(
            $flags,
            $title,
            $html
        );
    }

    /**
     * Render the HTML layout
     *
     * @param int    $flags   layout flags, see WebErrorHandler::FLAG_* constants
     * @param string $title   main title
     * @param string $content html content
     */
    public function renderLayout($flags, $title, $content)
    {
        $js = $this->getLayoutJs($flags);
        $css = $this->getLayoutCss($flags);

        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
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
     * @param int $flags layout flags, see WebErrorHandler::FLAG_* constants
     * @return string
     */
    public function getLayoutCss($flags = 0)
    {
        // read flags
        $narrowWrapper = (0 !== ($flags & self::FLAG_NARROW_WRAPPER));
        $debugAssets = (0 !== ($flags & self::FLAG_DEBUG_ASSETS));

        ob_start();

        if (!$this->replaceCss) {
        ?>
* {margin: 0; padding: 0;}
body {background-color: #ccc; font-family: 'Trebuchet MS', 'Geneva CE', lucida, sans-serif; font-size: 13px;}
h1, h2, h3, h4 {font-weight: normal;}
h1 {font-size: 2em;}
h2 {font-size: 1.5em;}
h3 {font-size: 1.2em; margin: 0.5em 0; color: #999;}
h4 {font-size: 1.1em; margin: 0.5em 0; color: #999;}
h1 em, h2 em {font-size: 0.9em;}
p {line-height: 140%; margin: 0.5em 0;}
ul, ol {margin: 0.5em 0; padding-left: 3em;}
em {color: #777;}
table {border-collapse: collapse;}
td, th {padding: 0.5em 1em; border: 1px solid #ddd;}
th {background-color: #eee;}
td {background-color: #fff;}
hr {border: 0; border-bottom: 1px solid #999;}
a {color: #00e; text-decoration: none;}
a:hover {color: #000; text-decoration: underline;}
a:active {color: #f00;}

#wrapper {margin: 0 auto; padding: 0 1em; overflow: hidden; max-width: <?php echo $narrowWrapper ? '700' : '1200' ?>px;}

div.section {overflow-x: auto; position: relative;}
div.major h2 {font-size: 2em;}
div.minor.standalone {background-color: #eee;}
div.group, div.standalone {margin: 1.5em 0; padding: 2em; background-color: #fafafa; border: 1px solid #aaa; border-radius: 10px; box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);}
div.group > div.section {margin-top: 0.5em;}
div.group > div.section:first-child {margin-top: 0;}

/* Icon by by Yannick from http://www.flaticon.com */
div.section.has-icon {padding-right: 84px;}
div.section.has-icon:before {opacity: 0.2; position: absolute; right: 10px; top: 50%;}
div.section.has-icon.standalone {padding-right: 94px;}
div.section.has-icon.standalone:before {right: 20px;}
div.section.has-icon.icon-warning:before {content: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAAA2CAQAAADdG1eJAAACY0lEQVR4AcXXz2sUdxjA4Wcnu4fN+iOBEoKJbM1ViChaJeDFi0IELREShRY8tAElksXtUVmUZd1Ts5gEYu+19NJehBx6tCd7ESneBVF6bi8t2F56WJZhnfnuzOwz/8BnXnhfZgg3o+O11zpmjMGUl/79/3lpSqBImIpnFgEseqZSbMBdS/otuVtkwDH3DbrvWHEBu6oGVe0WFbDqkjiXrBYRcNgW4m05nH9AxyzizerkHXDWumHWnc0zoGxPZJjInnJ+AZtO+JgTNvMKqGtJoqWeT8C2miRqtvMIWHFZUpetZB1wUE8aPQezDWibk8acdpYBp90W52fnnfeTOLedzipgQvz2/2HVc8+teif+IkxkE7DhlDg/+hv843txTtnIImDeQ/H+AvCneA/Njx7w2AGhDng8asAVV43iqiuJAsLfIMkEwwMeOGpURz0IDTjpjizccTJBQOgeB9yRhAG3nJGVM26lDTiiLUttR9IF9BySpUN6aQKWXZO1a5aTBkzakYcdk8kCWuryUNdKErCoIS8Nix8LKKX4rp8DMJ/iv6I0PGDdOUmtmAZT1iR1zvqwgFmPJFf1QlPTb2qSe2RWn5J+T63J3w+ux0/gojVFWHMxLqBqV1F2VQcDuGdBURbcGww4rinEG2+EaDreH1CypyK9Ly1Y8IX0KvaUoAS+8kR6L3wG+NWS9L72HRFmdIV4B+CtEF0zROiaFmLZp6DucyGmdSn7xA1hJvzuKa4rC3PDNyWbvjU+jchN43QzUjNOk5FXxulV5BfjtB/Z0fDBOHyw4UmELRd07HuvKO/t67hgm/8Ag6dqX/zuTucAAAAASUVORK5CYII=); margin-top: -29px;}

@media (max-width: 400px) {
    div.section.has-icon:before {display: none;}
}
<?php if ($debugAssets): ?>

div.output-buffer textarea {width: 100%; margin: 1em 0; padding: 0.5em; border: 1px solid #aaa; box-sizing: border-box; -moz-box-sizing: border-box; -webkit-box-sizing: border-box;}

ol.code-preview {overflow-x: auto; padding-left: 60px; border: 1px solid #ddd; line-height: 1.5; font-family: monospace; white-space: nowrap; background-color: #eee;}
ol.code-preview > li {background-color: #fff; padding-left: 10px;}
ol.code-preview > li.active {background-color: #ebf4ff;}

table.trace {width: 100%;}
table.trace > tbody > tr.trace > th {width: 1%;}
table.trace > tbody > tr.trace > td {cursor: pointer;}
table.trace > tbody > tr.trace:hover > td, table.trace > tbody > tr.trace.trace-open > td {background-color: #ebf4ff;}
table.trace > tbody > tr.trace-extra {display: none;}

table.argument-list {margin-top: 0.5em; width: 100%;}
table.argument-list:first-child {margin: 0;}
table.argument-list > tbody > tr > th,
table.argument-list > tbody > tr > th {width: 1%;}

iframe.html-preview {border: 1px solid #aaa; width: 100%; height: 500px; background-color: #fff;}

.hidden {display: none;}
<?php endif ?>
<?php
        }

        echo $this->extraCss;

        return ob_get_clean();
    }

    /**
     * Get javascript for the HTML layout
     *
     * @param int $flags layout flags, see WebErrorHandler::FLAG_* constants
     * @return string
     */
    public function getLayoutJs($flags = 0)
    {
        // read flags
        $debugAssets = (0 !== ($flags & self::FLAG_DEBUG_ASSETS));

        ob_start();

        if (!$this->replaceJs && $debugAssets) {
        ?>
(function(){
    window.errorHandlerToggleTrace = function (traceId) {
    try {
        var trace = document.getElementById('trace' + traceId),
            traceExtra = document.getElementById('trace_extra' + traceId)
        ;
        if (trace && traceExtra) {
            if ('' === traceExtra.style.display || 'none' === traceExtra.style.display) {
                // show
                trace.className = 'trace trace-open';
                try {
                    traceExtra.style.display = 'table-row';
                } catch (e) {
                    // IE7 :)
                    traceExtra.style.display = 'block';
                }
            } else {
                // hide
                trace.className = 'trace trace-closed';
                traceExtra.style.display = 'none';
            }
        }
    } catch (e) {
        console.log(e);
    }
    };

    window.showTextareaAsHtml = function (textareaId) {
        var textarea = document.getElementById(textareaId),
            iframe = document.getElementById('html_preview' + textareaId)
        ;

        if (textarea) {
            if (iframe) {
                iframe.parentNode.removeChild(iframe);
            } else {
                iframe = document.createElement('iframe');
                iframe.src = 'about:blank';
                iframe.className = 'html-preview';
                iframe.id = 'html_preview' + textareaId;

                iframe = textarea.parentNode.insertBefore(iframe, textarea);

                iframe.contentWindow.document.open('text/html');
                iframe.contentWindow.document.write(textarea.value);
                iframe.contentWindow.document.close();
            }
        }
    };
})();
<?php
        }

        echo $this->extraJs;

        return ob_get_clean();
    }

    /**
     * Escape the given string string for HTML
     *
     * @param string $string the string to escape
     * @return string html
     */
    public function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
    }

    /**
     * Render code preview
     *
     * @param string $file        file path
     * @param int    $line        line number
     * @param int    $lineRange   range of lines to render (range-line-range)
     * @param int    $maxFileSize maximum file size
     * @return string|null
     */
    public function renderCodePreview($file, $line, $lineRange = 5, $maxFileSize = self::CODE_PREVIEW_MAX_FILE_SIZE)
    {
        if (!is_file($file) || filesize($file) > $maxFileSize || $line < 1) {
            return;
        }

        $code = @highlight_file($file, true);
        if (false !== $code) {
            // process highlighted code
            $lines = explode(
                '<br />',
                preg_replace(
                    '~^<code[^>]*?>\s*<span[^>]*?>(.*)</span>\s*</code>$~s',
                    '$1',
                    $code
                )
            );
            $code = null;

            // determine range
            $start = max(0, $line - $lineRange - 1);
            $end = min(sizeof($lines) - 1, $start + $lineRange * 2);
            
            // render
            $output = '<ol start="' . ($start + 1) . '" class="code-preview">';
            for ($i = $start; $i <= $end; ++$i) {
                $output .= '<li' . ($i + 1 === $line ? ' class="active"' : '') . '>'
                    . $this->repairHighlightedLine($lines[$i])
                    . "</li>\n"
                ;
            }
            $output .= "</ol>\n";

            return $output;
        }
    }

    /**
     * Repair a line from highlight_file()
     *
     * @param string $line
     * @return string
     */
    protected function repairHighlightedLine($line)
    {
        $openingTag = strpos($line, '<span');
        $closingTag = strpos($line, '</span>');

        // unmatched </span> at the beginning
        if (false !== $closingTag && (false === $openingTag || $closingTag < $openingTag)) {
            // remove it
            $line = substr_replace($line, '', $closingTag, 7);
        }

        // missing </span> at the end
        if (preg_match('~<span[^>]*>(?!.*</span>).*$~s', $line)) {
            $line .= '</span>';
        }

        return $line;
    }

    /**
     * Render exception
     *
     * @param \Exception $exception the exception instance
     * @param int        $index     index of the current exception
     * @param int        $total     total number of exceptions
     * @return string html
     */
    public function renderException(\Exception $exception, $index, $total)
    {
        $trace = $exception->getTrace();
        $isMain = 0 === $index;

        $message = '';
        $info = '';

        $title = DebugUtils::getExceptionName($exception);
        $titleTag = $isMain ? 'h1' : 'h2';

        $message = '<p>' . nl2br($this->escape($exception->getMessage()), false) . '</p>';
        $info = "\n<p>in <em>" . $this->renderFilePath($exception->getFile()) . "</em> on line <em>" . $exception->getLine() . "</em></p>";

        // title, message, file
        $html = "<div class=\"group exception\">
<div class=\"section major has-icon icon-warning\">
<{$titleTag}><em>" . ($index + 1) . "/{$total}</em> {$title}</{$titleTag}>
{$message}{$info}
</div>\n";

        // code preview
        $codePreview = $this->renderCodePreview($exception->getFile(), $exception->getLine(), $isMain ? 5 : 3);
        if (null !== $codePreview) {
            $html .= "<div class=\"section\">{$codePreview}</div>\n";
            $codePreview = null;
        }

        // trace
        if (!empty($trace)) {
            $html .= $this->renderTrace($trace);
        }

        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render trace
     *
     * @param array $trace                   the trace array
     * @param bool  $hideFirstFrameArguments do not render arguments of the first frame 1/0
     * @return string html
     */
    public function renderTrace(array $trace, $hideFirstFrameArguments = false)
    {
        $traceCounter = sizeof($trace) - 1;
        $html = "<div class=\"section\">
<h3>Stack trace</h3>
<table class=\"trace\">
<tbody>\n";

        foreach ($trace as $frameIndex => $frame) {

        $frameUid = $this->getUid();

        // call
        if (isset($frame['type'], $frame['class'])) {
            $call = "{$frame['class']}{$frame['type']}";
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

        // row
        $html .= "<tr class=\"trace trace-closed\" id=\"trace{$frameUid}\" onclick=\"errorHandlerToggleTrace({$frameUid})\">
        <th>{$traceCounter}</th>
        <td><em>{$call}</em>{$frame['function']}<em>(" . (isset($frame['args']) ? sizeof($frame['args']) : 0) . ")</em></td>
        <td>{$file}</td>
    </tr>

    <tr class=\"trace-extra\" id=\"trace_extra{$frameUid}\">
    <td></td>
    <td colspan=\"2\">\n";

        // code preview
        if (isset($frame['file'], $frame['line'])) {
            $html .= $this->renderCodePreview($frame['file'], $frame['line'], 3);
        }

        // arguments
        if (!empty($frame['args']) && (0 !== $frameIndex || !$hideFirstFrameArguments)) {
            $html .= $this->renderArguments($frame['args']);
        }

        $html .= "
    </td>
    </tr>\n";
        --$traceCounter;

        }

        $html .= "</tbody>
</table>
</div>\n";

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
            $html .= "<tr><th>{$i}</th><td><pre>" . $this->escape(DebugUtils::getDump($args[$i])) . "</pre></td></tr>\n";
        }
        $html .= "</tbody></table>\n";

        return $html;
    }

    /**
     * Render output buffer
     *
     * @param string|null $outputBuffer
     * @param int         $maxLen       maximum length of output buffer to be rendered
     * @return string html
     */
    public function renderOutputBuffer($outputBuffer, $maxLen = 102400)
    {
        // see if buffer is empty
        if (null === $outputBuffer || '' === $outputBuffer) {
            return '';
        }

        // analyse value
        $message = null;
        $show = false;
        if (strlen($outputBuffer) > $maxLen) {
            $message = 'The output buffer contents are too long.';
        } elseif (0 !== preg_match('/[\\x00-\\x09\\x0B\\x0C\\x0E-\\x1F]/', $outputBuffer)) {
            $message = 'The output buffer contains unprintable characters.';
        } else {
            $rows = 1 + min(10, preg_match_all('/\\r\\n|\\n|\\r/', $outputBuffer, $matches));
            $show = true;
        }

        // render
        if ($show) {
            $textareaId = sprintf('outputBuffer%d', $this->getUid());
        }

        return "<div class=\"section minor standalone output-buffer\">
<h2>Output buffer <em>(" . strlen($outputBuffer) . ")</em></h2>\n"
        . (null === $message ? '' : "<p>{$message}</p>\n")
        . ($show ? "<p><a href=\"javascript:void showTextareaAsHtml('{$textareaId}')\">Show as HTML</a></p>\n" : '')
        . ($show ? "<textarea id=\"{$textareaId}\" rows=\"{$rows}\" cols=\"80\">" . $this->escape($outputBuffer) . "</textarea>\n" : '')
        . "</div>\n";
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
