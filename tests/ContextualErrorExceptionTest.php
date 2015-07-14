<?php

namespace Kuria\Error;

class ContextualErrorExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testAttributes()
    {
        $context = array(
            'foo' => 'bar',
        );

        $previous = new \Exception('Test');
        $contextual = new ContextualErrorException('Test contextual', 123, E_USER_ERROR, __FILE__, 321, $previous, $context);

        $this->assertSame('Test contextual', $contextual->getMessage());
        $this->assertSame(123, $contextual->getCode());
        $this->assertSame(__FILE__, $contextual->getFile());
        $this->assertSame(321, $contextual->getLine());
        $this->assertSame($previous, $contextual->getPrevious());
        $this->assertSame($context, $contextual->getContext());
    }
}
