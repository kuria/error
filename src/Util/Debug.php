<?php

namespace Kuria\Error\Util;

/**
 * Debug utility class
 *
 * @author ShiraNai7 <shira.cz>
 */
class Debug
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
     * @param mixed       $value        the value to dump
     * @param int         $maxLevel     maximum nesting level
     * @param int|null    $maxStringLen maximum number of characters to dump (null = no limit)
     * @param string|null $encoding     string encoding (null = mb_internal_encoding())
     * @param int         $currentLevel current nesting level
     * @return string
     */
    public static function dump($value, $maxLevel = 2, $maxStringLen = 64, $encoding = null, $currentLevel = 1)
    {
        $output = '';
        $type = gettype($value);
        $indent = str_repeat('    ', $currentLevel);

        switch ($type) {
            case 'array':
                if ($currentLevel < $maxLevel && !empty($value)) {
                    // full
                    $output .= 'array[' . sizeof($value) . "] {\n";
                    foreach ($value as $key => $item) {
                        $output .= $indent . (is_string($key) ? self::dumpString($key, $maxStringLen, $encoding, '[]', '...') : "[{$key}]") . ' => ';
                        $output .= self::dump($item, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                    }
                    if ($currentLevel > 1) {
                        $output .= str_repeat('    ', $currentLevel - 1);
                    }
                    $key = $item = null;
                    $output .= "}";
                } else {
                    // short
                    $output .= 'array[' . sizeof($value) . "]";
                }
                break;
            case 'object':
                $output .= 'object(';
                if (PHP_MAJOR_VERSION >= 7) {
                    // PHP 7+ (anonymous class names contain a NULL byte)
                    $className = get_class($value);
                    
                    $output .= false !== ($nullBytePos = strpos($className, "\0"))
                        ? substr($className, 0, $nullBytePos)
                        : $className
                    ;
                } else {
                    $output .= get_class($value);
                }
                $output .= ')';
                if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
                    // datetime
                    $output .= " \"{$value->format(DATE_RFC1123)}\"";
                } else {
                    // fetch properties
                    if (method_exists($value, '__debugInfo')) {
                        // use __debugInfo (PHP 5.6 feature)
                        $properties = $value->__debugInfo();
                        $actualProperties = false;
                    } else {
                        // use actual properties
                        $properties = self::getObjectProperties($value, true, true);
                        $actualProperties = true;
                    }

                    // dump
                    $output .= '[' . sizeof($properties) . ']';
                    if ($currentLevel < $maxLevel && !empty($properties)) {
                        // full
                        $output .= " {\n";
                        if ($actualProperties) {
                            foreach ($properties as $key => $item) {
                                $output .=
                                    $indent
                                    . implode(' ', \Reflection::getModifierNames($item->getModifiers())) . ' '
                                    . self::dumpString($key, $maxStringLen, $encoding, '[]', '...')
                                    . ' => '
                                ;
                                $output .= self::dump($item->getValue($value), $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                            }
                        } else {
                            foreach ($properties as $key => $item) {
                                $output .= $indent . (is_string($key) ? self::dumpString($key, $maxStringLen, $encoding, '[]', '...') : "[{$key}]") . ' => ';
                                $output .= self::dump($item, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                            }
                        }
                        $properties = $key = $item = null;
                        if ($currentLevel > 1) {
                            $output .= str_repeat('    ', $currentLevel - 1);
                        }
                        $output .= '}';
                    } elseif (method_exists($value, '__toString')) {
                        // too deep or no properties - use __toString()
                        $output .= ' ' . self::dumpString((string) $value, $maxStringLen, $encoding, '""', '...');
                    }
                }
                break;
            case 'string':
                $output .= self::dumpString($value, $maxStringLen, $encoding, '""', '...');
                break;
            case 'integer':
                $output .= $value;
                break;
            case 'double':
                $output .= sprintf('%F', $value);
                break;
            case 'boolean':
                $output .= ($value ? 'true' : 'false');
                break;
            case 'resource':
                $output .= 'resource(' . get_resource_type($value) . '#' . ((int) $value) . ")";
                break;
            default:
                $output .= $type;
                break;
        }

        $output .= "\n";

        return $output;
    }

    /**
     * Dump a string and return the result
     *
     * All ASCII < 32 will be escaped in C style.
     *
     * @param string               $string    the string to dump
     * @param int|null             $maxLength maximum number of characters to dump (null = no limit)
     * @param string|null          $encoding  string encoding (null = mb_internal_encoding())
     * @param string|string[]|null $quotes    quote symbols (2 byte string or a 2-element array)
     * @param string|null          $ellipsis  ellipsis string (appended at the end if the string had to be shortened)
     * @return string
     */
    public static function dumpString($string, $maxLength = null, $encoding = null, $quotes = null, $ellipsis = null)
    {
        $stringLength = null === $encoding 
            ? mb_strlen($string)
            : mb_strlen($string, $encoding)
        ;
        $tooLong = null !== $maxLength && $stringLength > $maxLength;

        return
            $quotes[0]
            . addcslashes(
                $tooLong
                    ? (null === $encoding
                        ? mb_substr($string, 0, $maxLength)
                        : mb_substr($string, 0, $maxLength, $encoding)
                    )
                    : $string,
                "\000..\037"
            )
            . $quotes[1]
            . ($tooLong ? $ellipsis : '')
        ;
    }

    /**
     * Get all properties of the given object
     *
     * @param object $object
     * @param bool   $includeStatic
     * @param bool   $getReflection
     * @param bool   $sorted
     * @return mixed[]|\ReflectionProperty[]
     */
    public static function getObjectProperties($object, $includeStatic = true, $getReflection = false, $sorted = true)
    {
        $output = array();
        
        try {
            $filter =
                \ReflectionProperty::IS_PUBLIC
                | \ReflectionProperty::IS_PROTECTED
                | \ReflectionProperty::IS_PRIVATE
            ;

            $parentFilter = \ReflectionProperty::IS_PRIVATE;

            if ($includeStatic) {
                $filter |= \ReflectionProperty::IS_STATIC;
                $parentFilter |= \ReflectionProperty::IS_STATIC;
            }

            $reflection = new \ReflectionObject($object);
            foreach ($reflection->getProperties($filter) as $property) {
                $property->setAccessible(true);

                $output[$property->getName()] = $getReflection ? $property : $property->getValue($object);
            }

            foreach (class_parents($object) as $parentClass) {
                $reflection = new \ReflectionClass($parentClass);

                foreach ($reflection->getProperties($parentFilter) as $property) {
                    // in case of private property shadowing, use the child's value
                    if (!array_key_exists($name = $property->getName(), $output)) {
                        $property->setAccessible(true);

                        $output[$name] = $getReflection ? $property : $property->getValue($object);
                    }
                }
            }
        }  catch (\ReflectionException $e) {
            // some objects may not be fully accessible (e.g. instances of internal classes)
        }

        if ($sorted) {
            ksort($output, SORT_STRING);
        }

        return $output;
    }

    /**
     * Attempt to clean output buffers
     *
     * Some built-in or non-removable buffers cannot be cleaned.
     *
     * @param int|null $targetLevel     target buffer level or null (= all buffers)
     * @param bool     $capture         capture and return buffer contents 1/0
     * @param bool     $catchExceptions catch buffer exceptions 1/0 (false = rethrow)
     * @return string|bool captured buffer if $capture = true, boolean status otherwise
     */
    public static function cleanBuffers($targetLevel = null, $capture = false, $catchExceptions = false)
    {
        if (null === $targetLevel) {
            $targetLevel = 0;

            if ('' != ini_get('output_buffer') || '' != ini_get('output_handler')) {
                ++$targetLevel;
            }
        }

        if ($capture) {
            $buffer = '';
        }

        if (($bufferLevel = ob_get_level()) > $targetLevel) {
            $cleanFunction = $capture ? 'ob_get_clean' : 'ob_end_clean';

            do {
                $lastBufferLevel = $bufferLevel;

                $e = null;
                try {
                    $result = $cleanFunction();
                } catch (\Exception $e) {
                } catch (\Throwable $e) {
                }

                $bufferLevel = ob_get_level();

                if (null === $e) {
                    if ($capture) {
                        $buffer = $result . $buffer;
                    }
                } elseif (!$catchExceptions) {
                    throw $e;
                }
            } while ($bufferLevel > $targetLevel && $bufferLevel < $lastBufferLevel);
        }

        return $capture ? $buffer : $bufferLevel <= $targetLevel;
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
            header_remove();
            
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
     * @param object $node the current exception instance
     * @return object[]
     */
    public static function getExceptionChain($node)
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
     * Join exception chains together
     *
     * @param object $exception1,...
     * @return object|null the last exception passed
     */
    public static function joinExceptionChains()
    {
        $nodes = func_get_args();

        $lastNodeIndex = sizeof($nodes) - 1;

        if ($lastNodeIndex > 0) {
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
                $previousProperty = new \ReflectionProperty(
                    ($parents = class_parents($initialNode)) ? end($parents) : $initialNode,
                    'previous'
                );
                
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
     * Get textual information about an exception
     *
     * @param object $exception      the exception instance
     * @param bool   $renderTrace    render exception traces 1/0
     * @param bool   $renderPrevious render previous exceptions 1/0
     * @return string
     */
    public static function renderException($exception, $renderTrace = true, $renderPrevious = false)
    {
        $exceptions = $renderPrevious
            ? self::getExceptionChain($exception)
            : array($exception)
        ;
        $totalExceptions = sizeof($exceptions);

        $output = '';
        for ($i = 0; $i < $totalExceptions; ++$i) {
            if ($i > 0 && $renderTrace) {
                $output .= "\n";
            }

            $output .= ($renderPrevious ? '[' . ($i + 1) . "/{$totalExceptions}] " : '')
                . self::getExceptionName($exceptions[$i])
                . ': ' . ($exceptions[$i]->getMessage() ?: '<no message>')
                . " in {$exceptions[$i]->getFile()} on line {$exceptions[$i]->getLine()}\n"
            ;

            if ($renderTrace) {
                $output .= $exceptions[$i]->getTraceAsString() . "\n";
            }
        }

        return $output;
    }

    /**
     * Get name of the given exception
     *
     * @param object $exception
     * @return string
     */
    public static function getExceptionName($exception)
    {
        $name = null;

        if ($exception instanceof \ErrorException) {
            $name = self::getErrorNameByCode($exception->getSeverity());
        }

        if (null === $name) {
            $name = get_class($exception);
        }

        if (0 !== ($code = $exception->getCode())) {
            $name .= " ({$code})";
        }

        return $name;
    }

    /**
     * Get PHP error name by its code
     *
     * @param int $code PHP error code
     * @return string|null
     */
    public static function getErrorNameByCode($code)
    {
        if (isset(self::$errorCodes[$code])) {
            return self::$errorCodes[$code];
        }
    }

    /**
     * Try to detect whether autoloading is currently active
     *
     * This is part of a workaround for a bug that has been fixed in PHP
     * versions 5.4.21+, 5.5.5+ and 5.6.0+
     *
     * https://bugs.php.net/42098
     *
     * @return bool
     */
    public static function isAutoloadingActive()
    {
        $testClass = 'Kuria\Error\Debug__NonexistentClass';
        
        $autoloadingActive = false;
        $autoloadChecker = function ($class) use (&$autoloadingActive, $testClass) {
            if ($class === $testClass) {
                $autoloadingActive = true;
            }
        };

        if (spl_autoload_register($autoloadChecker, false, true)) {
            class_exists($testClass);
        }
        spl_autoload_unregister($autoloadChecker);

        return $autoloadingActive;
    }
}
