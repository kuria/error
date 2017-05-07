<?php

namespace Kuria\Error\Util;

/**
 * PHP code preview utility
 *
 * @author ShiraNai7 <shira.cz>
 */
class PhpCodePreview
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Preview code from a PHP file
     *
     * @param string   $file       file path
     * @param int|null $activeLine active line number
     * @param int|null $lineRange  range of lines to render (around active line)
     * @param string   $className  wrapper class name
     * @return string|null
     */
    public static function file($file, $activeLine = null, $lineRange = null, $className = 'code-preview')
    {
        $highlighted = @highlight_file($file, true);

        if (false !== $highlighted) {
            return static::render($highlighted, $activeLine, $lineRange, $className);
        }
    }

    /**
     * Preview code from a string containing PHP code
     *
     * @param string   $code       php code
     * @param int|null $activeLine active line number
     * @param int|null $lineRange  range of lines to render (around active line)
     * @param string   $className  wrapper class name
     * @return string|null
     */
    public static function code($code, $activeLine = null, $lineRange = null, $className = 'code-preview')
    {
        $code = @highlight_string($code, true);

        if (false !== $code) {
            return static::render($code, $activeLine, $lineRange, $className);
        }
    }

    /**
     * Render code preview
     *
     * @param string   $code
     * @param int|null $activeLine
     * @param int|null $lineRange
     * @param string   $className
     * @return string|null
     */
    protected static function render($code, $activeLine, $lineRange, $className)
    {
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
        if (null !== $lineRange) {
            if (null === $activeLine) {
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
            . (null !== $className ? ' class="' . $className . '"' : '')
            . '>'
        ;
        for ($i = $start; $i <= $end; ++$i) {
            $output .= '<li' . (null !== $activeLine && $i + 1 === $activeLine ? ' class="active"' : '') . '>'
                . static::repairHighlightedLine($lines[$i])
                . "</li>\n"
            ;
        }
        $output .= "</ol>\n";

        return $output;
    }

    /**
     * Repair a single line from highlight_file/string()
     *
     * @param string $line
     * @return string
     */
    protected static function repairHighlightedLine($line)
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
}
