<?php

namespace Kuria\Error;

/**
 * Debug utility class
 *
 * @author ShiraNai7 <shira.cz>
 */
class DebugUtils
{
    /** @var string[] */
    private static $errorCodes = array(
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core error',
        E_CORE_WARNING => 'Core warning',
        E_COMPILE_ERROR => 'Compile error',
        E_COMPILE_WARNING => 'Compile warning',
        E_USER_ERROR => 'User error',
        E_USER_WARNING => 'User warning',
        E_USER_NOTICE => 'User notice',
        E_STRICT => 'Strict notice',
        E_RECOVERABLE_ERROR => 'Recoverable error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User deprecated',
    );

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Dump a value
     *
     * @param mixed $value        the value to dump
     * @param int   $maxLevel     maximum nesting level
     * @param int   $maxStringLen limit of string characters do dump
     * @param int   $currentLevel current nesting level
     * @return null prints the output
     */
    public static function dump($value, $maxLevel = 2, $maxStringLen = 64, $currentLevel = 1)
    {
        $objPropertyFilter =
            \ReflectionProperty::IS_PUBLIC
            | \ReflectionProperty::IS_PROTECTED
            | \ReflectionProperty::IS_PRIVATE
        ;

        // indentation
        $indent = str_repeat('    ', $currentLevel);

        // dump
        if (is_array($value)) {
            // array
            if ($currentLevel < $maxLevel) {
                // full
                echo "array(", sizeof($value), ") {\n";
                foreach ($value as $arrKey => $arrValue) {
                    echo $indent, '[', $arrKey, '] => ';
                    self::dump($arrValue, $maxLevel, $maxStringLen, $currentLevel + 1);
                }
                if ($currentLevel > 1) {
                    echo str_repeat('    ', $currentLevel - 1);
                }
                $arrKey = $arrValue = null;
                echo "}\n";
            } else {
                // short
                echo "array(", sizeof($value), ")\n";
            }
        } elseif (is_object($value)) {
            // object
            if ($currentLevel < $maxLevel) {
                // full
                echo "object(", get_class($value), ") {\n";
                if (method_exists($value, '__debugInfo')) {
                    // use __debugInfo (PHP 5.6 feature)
                    foreach ($value->__debugInfo() as $debugKey => $debugValue) {
                        echo $indent, '[', $debugKey, '] => ';
                        self::dump($debugValue, $maxLevel, $maxStringLen, $currentLevel + 1);
                    }
                    $debugKey = $debugValue = null;
                } else {
                    // use actual properties
                    try {
                        $objReflection = new \ReflectionObject($value);
                        foreach ($objReflection->getProperties($objPropertyFilter) as $objProp) {
                            $objProp->setAccessible(true);
                            echo $indent, '[', $objProp->getName(), '] => ';
                            self::dump($objProp->getValue($value), $maxLevel, $maxStringLen, $currentLevel + 1);
                        }
                        $objReflection = $objProp = null;
                    } catch (\Exception $e) {
                        // ignore reflection errors
                        // some internal/extension objects may not be fully accessible
                    }
                }
                if ($currentLevel > 1) {
                    echo str_repeat('    ', $currentLevel - 1);
                }
                echo "}\n";
            } else {
                // short
                echo "object(", get_class($value), ")\n";
            }
        } elseif (is_string($value)) {
            // string
            $strLen = strlen($value);
            echo "string({$strLen}) ";
            if ($strLen > $maxStringLen) {
                var_export(substr($value, 0, $maxStringLen));
                echo "...";
            } else {
                var_export($value);
            }
            echo "\n";
        } else {
            // other
            var_dump($value);
        }
    }

    /**
     * Dump a value and return the result
     *
     * @param mixed $value        the value to dump
     * @param int   $maxLevel     maximum nesting level
     * @param int   $maxStringLen limit of string characters do dump
     * @return string
     */
    public static function getDump($value, $maxLevel = 2, $maxStringLen = 64)
    {
        ob_start();
        try {
            self::dump($value, $maxLevel, $maxStringLen);

            return ob_get_clean();
        } catch (\Exception $e) {
            ob_clean();
            throw $e;
        }
    }

    /**
     * Attempt to clean all output buffers
     *
     * @param bool $capture attempt to capture and return buffer contents 1/0
     * @return string|null
     */
    public static function cleanBuffers($capture = false)
    {
        // clear
        $buffer = $capture ? '' : null;
        try {
            if ('' != ini_get('output_buffer') || '' != ini_get('output_handler')) {
                $targetBufferLevel = 1;
            } else {
                $targetBufferLevel = 0;
            }
            while ($targetBufferLevel !== ($bufferLevel = ob_get_level())) {
                if ($capture) {
                    $buffer = $buffer . ob_get_clean();
                } else {
                    ob_end_clean();
                }
            }
        } catch (\Exception $e) {
            // some built-in buffers cannot be cleaned
        }

        return $buffer;
    }

    /**
     * Attempt to replace headers
     *
     * @param string[] $newHeaders list of new headers to set
     * @return bool
     */
    public static function replaceHeaders(array $newHeaders)
    {
        if (!headers_sent()) {
            if (function_exists('header_remove')) {
                header_remove();
            }
            for ($i = 0; isset($newHeaders[$i]); ++$i) {
                header($newHeaders[$i]);
            }

            return true;
        }

        return false;
    }

    /**
     * List exceptions starting from the given exception
     *
     * @param \Exception $node the current exception instance
     * @return \Exception[]
     */
    public static function getExceptionChain(\Exception $node)
    {
        $chain = array();
        $hashMap = array();

        while (null !== $node && !isset($hashMap[$hash = spl_object_hash($node)])) {
            $chain[] = $node;
            $hashMap[$hash] = true;
            $node = $node->getPrevious();
        }

        return $chain;
    }

    /**
     * Get textual information about an exception
     *
     * @param \Exception $exception    the exception instance
     * @param bool       $showTrace    show exception traces 1/0
     * @param bool       $showPrevious show previous exceptions 1/0
     * @return string
     */
    public static function printException(\Exception $exception, $showTrace = true, $showPrevious = false)
    {
        $exceptions = $showPrevious
            ? self::getExceptionChain($exception)
            : array($exception)
        ;
        $totalExceptions = sizeof($exceptions);

        $output = '';
        for ($i = 0; $i < $totalExceptions; ++$i) {
            if ($i > 0 && $showTrace) {
                $output .= "\n";
            }

            $output .= ($showPrevious ? '[' . ($i + 1) . "/{$totalExceptions}] " : '')
                . self::getExceptionName($exceptions[$i])
                . ": {$exceptions[$i]->getMessage()}"
                . " in {$exceptions[$i]->getFile()} on line {$exceptions[$i]->getLine()}\n"
            ;

            if ($showTrace) {
                $output .= $exceptions[$i]->getTraceAsString() . "\n";
            }
        }

        return $output;
    }

    /**
     * Join exception chains together
     *
     * @param \Exception $exception1,...
     * @return \Exception|null the last exception passed
     */
    public static function joinExceptionChains()
    {
        /* @var $nodes \Exception[] */
        $nodes = func_get_args();

        $lastNodeIndex = sizeof($nodes) - 1;

        if ($lastNodeIndex > 0) {
            $exceptionReflection = new \ReflectionClass('Exception');

            // iterate over all but the last node
            for ($i = 0; $i < $lastNodeIndex; ++$i) {

                // find initial node of the next chain
                $initialNode = $nodes[$i + 1];
                $hashMap = array();
                while (($previousNode = $initialNode->getPrevious()) && !isset($hashMap[$hash = spl_object_hash($previousNode)])) {
                    $initialNode = $previousNode;
                }

                // connect end of the current chain (= current node)
                // to the initial node of the next chain
                $previousProperty = $exceptionReflection->getProperty('previous');
                $previousProperty->setAccessible(true);
                $previousProperty->setValue($initialNode, $nodes[$i]);
            }
        }

        return $lastNodeIndex >= 0
            ? $nodes[$lastNodeIndex]
            : null
        ;
    }

    /**
     * Get name of the given exception
     *
     * @param \Exception $exception
     * @return string
     */
    public static function getExceptionName(\Exception $exception)
    {
        if ($exception instanceof \ErrorException) {
            return self::getErrorNameByCode($exception->getSeverity() ?: E_ERROR);
        } else {
            $name = get_class($exception);
            if (0 !== ($code = $exception->getCode())) {
                $name .= " ({$code})";
            }

            return $name;
        }
    }

    /**
     * Get PHP error name by its code
     *
     * @param int $code PHP error code
     * @return string
     */
    public static function getErrorNameByCode($code)
    {
        if (isset(self::$errorCodes[$code])) {
            return self::$errorCodes[$code];
        } else {
            return "Unknown error ({$code})";
        }
    }

    /**
     * Try to detect whether autoloading is currently active
     *
     * @return bool
     */
    public static function isAutoloadingActive()
    {
        $testClass = __NAMESPACE__ . '\__DebugUtils__NonexistentClass';
        $autoloadingActive = false;
        $autoloadChecker = function ($class) use (&$autoloadingActive, $testClass) {
            if ($class === $testClass) {
                $autoloadingActive = true;
            }
        };

        try {
            if (spl_autoload_register($autoloadChecker, false, true)) {
                class_exists($testClass);
            }
            spl_autoload_unregister($autoloadChecker);
        } catch (\Exception $e) {
            spl_autoload_unregister($autoloadChecker);
        }

        return $autoloadingActive;
    }
}
