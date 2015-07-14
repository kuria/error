<?php

namespace Kuria\Error;

/**
 * Contextual error exception
 *
 * @author ShiraNai7 <shira.cz>
 */
class ContextualErrorException extends \ErrorException
{
    /** @var array|null */
    protected $context;

    public function __construct($message, $code, $severity, $filename, $line, $previous, array $context)
    {
        parent::__construct($message, $code, $severity, $filename, $line, $previous);

        $this->context = $context;
    }

    /**
     * Get the variable context
     *
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }
}
