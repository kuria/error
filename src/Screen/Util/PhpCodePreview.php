<?php declare(strict_types=1);

namespace Kuria\Error\Screen\Util;

/**
 * PHP code preview utility
 */
abstract class PhpCodePreview
{
    /**
     * Preview code from a PHP file
     *
     * Returns NULL on failure.
     */
    static function file(string $file, ?int $activeLine = null, ?int $lineRange = null, string $className = 'code-preview'): ?string
    {
        $highlighted = @highlight_file($file, true);

        if ($highlighted !== false) {
            return static::process($highlighted, $activeLine, $lineRange, $className);
        }

        return null;
    }

    /**
     * Preview code from a string containing PHP code
     *
     * Returns NULL on failure.
     */
    static function code(string $phpCode, ?int $activeLine = null, ?int $lineRange = null, string $className = 'code-preview'): ?string
    {
        $phpCode = @highlight_string($phpCode, true);

        if ($phpCode !== false) {
            return static::process($phpCode, $activeLine, $lineRange, $className);
        }

        return null;
    }

    /**
     * Process highlighted PHP code (html)
     */
    protected static function process(string $html, ?int $activeLine, ?int $lineRange, string $className): string
    {
        // process highlighted code
        $lines = explode(
            '<br />',
            preg_replace(
                '~^<code[^>]*?>\s*<span[^>]*?>(.*)</span>\s*</code>$~s',
                '$1',
                $html
            )
        );
        $html = null;

        // determine range
        if ($lineRange !== null) {
            if ($activeLine === null) {
                throw new \LogicException('Cannot render line range without specified active line');
            }
            $start = max(0, $activeLine - $lineRange - 1);
            $end = min(sizeof($lines) - 1, $start + $lineRange * 2);
        } else {
            $start = 0;
            $end = sizeof($lines) - 1;
        }

        // render
        $output = '<ol'
            . ($start > 0 ? ' start="' . ($start + 1) . '"' : '')
            . ($className !== null ? ' class="' . $className . '"' : '')
            . '>';
        for ($i = $start; $i <= $end; ++$i) {
            $output .= '<li' . ($activeLine !== null && $i + 1 === $activeLine ? ' class="active"' : '') . '>'
                . static::repairHighlightedLine($lines[$i])
                . "</li>\n";
        }
        $output .= "</ol>\n";

        return $output;
    }

    /**
     * Repair a single line from highlight_file() or highlight_string()
     */
    protected static function repairHighlightedLine(string $line): string
    {
        $openingTag = strpos($line, '<span');
        $closingTag = strpos($line, '</span>');

        // unmatched </span> at the beginning
        if ($closingTag !== false && ($openingTag === false || $closingTag < $openingTag)) {
            // remove it
            $line = substr_replace($line, '', $closingTag, 7);
        }

        // missing </span> at the end
        if (preg_match('{<span[^>]*>(?!.*</span>).*$}s', $line)) {
            $line .= '</span>';
        }

        return $line;
    }
}
