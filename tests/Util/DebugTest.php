<?php

namespace Kuria\Error\Util;

class DebugTest extends \PHPUnit_Framework_TestCase
{
    public function testDumpBasic()
    {
        $assertions = array(
            array('foo bar', '"foo bar"'),
            array(123, '123'),
            array(-123, '-123'),
            array(1.53, '1.530000'),
            array(-1.53, '-1.530000'),
            array(true, 'true'),
            array(false, 'false'),
            array(STDIN, 'resource\(stream#\d+\)', true),
            array(null, 'NULL'),
            array(array(1, 2, 3), 'array[3]'),
            array(new \stdClass(), 'object(stdClass)[0]'),
        );

        $this->assertDumpResults($assertions, 1);
    }

    public function testDumpString()
    {
        $assertions = array(
            array('12345678910', '"1234567891"...'),
            array('123456789žč', '"123456789ž"...'),
            array("\000", '"\000"'),
            array("\001", '"\001"'),
            array("\002", '"\002"'),
            array("\003", '"\003"'),
            array("\004", '"\004"'),
            array("\005", '"\005"'),
            array("\006", '"\006"'),
            array("\007", '"\a"'),
            array("\010", '"\b"'),
            array("\011", '"\t"'),
            array("\012", '"\n"'),
            array("\013", '"\v"'),
            array("\014", '"\f"'),
            array("\015", '"\r"'),
            array("\016", '"\016"'),
            array("\017", '"\017"'),
            array("\020", '"\020"'),
            array("\021", '"\021"'),
            array("\022", '"\022"'),
            array("\023", '"\023"'),
            array("\024", '"\024"'),
            array("\025", '"\025"'),
            array("\026", '"\026"'),
            array("\027", '"\027"'),
            array("\030", '"\030"'),
            array("\031", '"\031"'),
            array("\032", '"\032"'),
            array("\033", '"\033"'),
            array("\034", '"\034"'),
            array("\035", '"\035"'),
            array("\036", '"\036"'),
            array("\037", '"\037"'),
        );

        $this->assertDumpResults($assertions, 1, 10);
    }
    
    public function testDumpObject()
    {
        $testPropertyObject = new TestPropertiesA();
        $testPropertyObject->dynamic = 'hello';

        // NULLs in object property names are not tested here
        // beacause they are not supported before PHP 7
        $testKeyEscapesObject = new \stdClass();
        $testKeyEscapesObject->{"key-escapes-\t\n\v"} = 'a';
        $testKeyEscapesObject->{"key-binary-\001\002"} = 'b';

        $assertions = array(
            array($testPropertyObject, <<<EXPECTED
object(Kuria\Error\Util\TestPropertiesA)[8] {
    public [dynamic] => "hello"
    private [private] => "privateA"
    private [privateNonShadowed] => "privateNonShadowedA"
    protected [protected] => "protectedA"
    public [public] => "publicA"
    private static [staticPrivate] => "staticPrivateA"
    protected static [staticProtected] => "staticProtectedA"
    public static [staticPublic] => "staticPublicA"
}
EXPECTED
            ),
            array(new \DateTime('2015-01-01 00:00 UTC'), 'object(DateTime) "Thu, 01 Jan 2015 00:00:00 +0000"'),
            array(new TestToString(), 'object(Kuria\Error\Util\TestToString)[0] "foo bar"'),
            array($testKeyEscapesObject, <<<'EXPECTED'
object(stdClass)[2] {
    public [key-binary-\001\002] => "b"
    public [key-escapes-\t\n\v] => "a"
}
EXPECTED
            ),
            array(new TestDebugInfo(), <<<EXPECTED
object(Kuria\Error\Util\TestDebugInfo)[1] {
    [foo] => "bar"
}
EXPECTED
            ),
        );

        $this->assertDumpResults($assertions);
    }

    public function testDumpDeepObject()
    {
        $testObject = new \stdClass();

        $testObject->nestedObject = new \stdClass();

        $testObject->nestedObject->foo = array(1, 2, 3);
        $testObject->nestedObject->bar = new TestPropertiesA();
        $testObject->nestedObject->baz = new TestToString();        

        $expected = <<<EXPECTED
object(stdClass)[1] {
    public [nestedObject] => object(stdClass)[3] {
        public [bar] => object(Kuria\Error\Util\TestPropertiesA)[7]
        public [baz] => object(Kuria\Error\Util\TestToString)[0] "foo bar"
        public [foo] => array[3]
    }
}

EXPECTED;

        $this->assertSame($expected, Debug::dump($testObject, 3));
    }

    public function testDumpArray()
    {
        $testArray = array(
            "hello" => 'world',
            "key_escapes_\000\011\012\013\014\015" => 'a',
            "key_binary_\000\001\002" => 'b',
            'nested_array' => array(
                123,
                array(1, 2, 3),
            ),
        );

        $expected = <<<'EXPECTED'
array[4] {
    [hello] => "world"
    [key_escapes_\000\t\n\v\f\r] => "a"
    [key_binary_\000\001\002] => "b"
    [nested_array] => array[2] {
        [0] => 123
        [1] => array[3]
    }
}

EXPECTED;

        $this->assertSame($expected, Debug::dump($testArray, 3));
    }

    /**
     * @param array[] $assertions array of arrays: value, expected output, [is_regex?]
     */
    private function assertDumpResults(array $assertions, $maxLevel = 2, $maxStringLen = 64)
    {
        foreach ($assertions as $assertion) {
            list($value, $expected, $isRegex) = $assertion + array(2 => null);

            $result = Debug::dump($value, $maxLevel, $maxStringLen);

            if ($isRegex) {
                $this->assertRegExp('~^' . $expected . '\n$~', $result);
            } else {
                $this->assertSame($expected . "\n", $result);
            }
        }
    }

    public function testGetObjectProperties()
    {
        $expectedDefaultA = array(
            'dynamic' => 'dynamicA',
            'private' => 'privateA',
            'privateNonShadowed' => 'privateNonShadowedA',
            'protected' => 'protectedA',
            'public' => 'publicA',
            'staticPrivate' => 'staticPrivateA',
            'staticProtected' => 'staticProtectedA',
            'staticPublic' => 'staticPublicA',
        );

        $expectedNonStaticA = array(
            'dynamic' => 'dynamicA',
            'private' => 'privateA',
            'privateNonShadowed' => 'privateNonShadowedA',
            'protected' => 'protectedA',
            'public' => 'publicA',
            'staticPrivate' => 'staticPrivateA',
            'staticProtected' => 'staticProtectedA',
            'staticPublic' => 'staticPublicA',
        );

        $expectedDefaultB = array(
            'dynamic' => 'dynamicB',
            'private' => 'privateB',
            'privateNonShadowed' => 'privateNonShadowedA',
            'protected' => 'protectedB',
            'public' => 'publicB',
            'staticPrivate' => 'staticPrivateB',
            'staticProtected' => 'staticProtectedB',
            'staticPublic' => 'staticPublicB',
        );

        $expectedNonStaticB = array(
            'dynamic' => 'dynamicB',
            'private' => 'privateB',
            'privateNonShadowed' => 'privateNonShadowedA',
            'protected' => 'protectedB',
            'public' => 'publicB',
            'staticPrivate' => 'staticPrivateB',
            'staticProtected' => 'staticProtectedB',
            'staticPublic' => 'staticPublicB',
        );

        $a = new TestPropertiesA();
        $a->dynamic = 'dynamicA';

        $b = new TestPropertiesB();
        $b->dynamic = 'dynamicB';

        $this->assertSame($expectedDefaultA, Debug::getObjectProperties($a));
        $this->assertSame($expectedNonStaticA, Debug::getObjectProperties($a, false));
        $this->assertSame($expectedDefaultB, Debug::getObjectProperties($b));
        $this->assertSame($expectedNonStaticB, Debug::getObjectProperties($b, false));
    }

    public function testCleanBuffers()
    {
        $bufferLevel = ob_get_level();
        
        for ($i = 0; $i < 5; ++$i) {
            ob_start();
            echo $i;
        }
        
        $buffer = Debug::cleanBuffers($bufferLevel, true);

        $this->assertSame('01234', $buffer);
        $this->assertSame($bufferLevel, ob_get_level());
    }

    public function testCleanBuffersWithCaughtException()
    {
        $initialBufferLevel = ob_get_level();

        ob_start();
        echo 'a';

        ob_start(function ($buffer, $phase) {
            if (0 !== ($phase & PHP_OUTPUT_HANDLER_CLEAN)) {
                throw new \Exception('Test buffer exception');
            }
        });
        echo 'b';

        ob_start();
        echo 'c';

        $buffer = Debug::cleanBuffers($initialBufferLevel, true, true);

        $this->assertSame('ac', $buffer); // b gets discarded
        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    public function testCleanBuffersWithRethrownException()
    {
        $bufferLevel = ob_get_level();
        $testBufferException = new \Exception('Test buffer exception');

        ob_start();
        echo 'lorem';

        ob_start(function ($buffer, $phase) use ($testBufferException) {
            if (0 !== (PHP_OUTPUT_HANDLER_END & $phase)) {
                throw $testBufferException;
            }
        });
        
        echo 'ipsum';

        ob_start();
        echo 'dolor';
        
        $e = null;

        try {
            Debug::cleanBuffers($bufferLevel);
        } catch (\Exception $e) {
            $this->assertSame($testBufferException, $e);
        }

        Debug::cleanBuffers($bufferLevel, false, true);

        if (null === $e) {
            $this->fail('The buffer exception was not rethrown');
        }

        $this->assertSame($bufferLevel, ob_get_level());
    }

    public function testCleanBuffersAboveCurrentLevel()
    {
        $this->assertTrue(Debug::cleanBuffers(ob_get_level() + 1));
        $this->assertSame('', Debug::cleanBuffers(ob_get_level() + 1, true));
    }
    
    public function testGetExceptionChain()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $this->assertSame(array($a, $b, $c), Debug::getExceptionChain($a));
        $this->assertSame(array($b, $c), Debug::getExceptionChain($b));
        $this->assertSame(array($c), Debug::getExceptionChain($c));
    }

    public function testJoinExceptionChains()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $z = new \Exception('Z');
        $y = new \Exception('Y', 0, $z);
        $x = new \Exception('X', 0, $y);

        $result = Debug::joinExceptionChains($a, $x);

        $this->assertSame($x, $result);
        $this->assertSame($y, $x->getPrevious());
        $this->assertSame($z, $y->getPrevious());
        $this->assertSame($a, $z->getPrevious());
        $this->assertSame($b, $a->getPrevious());
        $this->assertSame($c, $b->getPrevious());
        $this->assertNull($c->getPrevious());
    }

    /**
     * @requires PHP 7.0
     */
    public function testJoinExceptionChainsWithDifferentExceptionHierarchies()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $z = new \Error('Z');
        $y = new \Error('Y', 0, $z);
        $x = new \Error('X', 0, $y);

        $result = Debug::joinExceptionChains($a, $x);

        $this->assertSame($x, $result);
        $this->assertSame($y, $x->getPrevious());
        $this->assertSame($z, $y->getPrevious());
        $this->assertSame($a, $z->getPrevious());
        $this->assertSame($b, $a->getPrevious());
        $this->assertSame($c, $b->getPrevious());
        $this->assertNull($c->getPrevious());
    }

    public function testRenderException()
    {
        $testException = new \Exception(
            'Test exception',
            0,
            new \Exception('Test previous exception')
        );

        // default (trace = on, previous = off)
        $output = Debug::renderException($testException);

        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertContains('{main}', $output);

        // trace = on, previous = on
        $output = Debug::renderException($testException, true, true);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
       
        $this->assertContains('{main}', $output);

        // trace = off, previous = on
        $output = Debug::renderException($testException, false, true);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);

        // trace = off, previous = off
        $output = Debug::renderException($testException, false, false);
        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
    }

    public function testGetExceptionName()
    {
        $this->assertSame('Exception', Debug::getExceptionName(new \Exception('Test exception')));
        $this->assertSame('Exception (123)', Debug::getExceptionName(new \Exception('Test exception', 123)));
        $this->assertSame('Error', Debug::getExceptionName(new \ErrorException('Test error', 0, E_ERROR)));
        $this->assertSame('Error (456)', Debug::getExceptionName(new \ErrorException('Test error', 456, E_ERROR)));
        $this->assertSame('ErrorException', Debug::getExceptionName(new \ErrorException('Test error', 0, 123456789)));
        $this->assertSame('ErrorException (789)', Debug::getExceptionName(new \ErrorException('Test error', 789, 123456789)));
    }

    public function testGetErrorNameByCode()
    {
        $errorLevels = array(
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
        
        foreach ($errorLevels as $code => $name) {
            $this->assertSame($name, Debug::getErrorNameByCode($code));
        }
    }

    public function testIsAutoloadingActive()
    {
        $this->assertTrue(Debug::isAutoloadingActive());
    }
}

class TestPropertiesA
{
    public static $staticPublic = 'staticPublicA';
    protected static $staticProtected = 'staticProtectedA';
    private static $staticPrivate = 'staticPrivateA';
    public $public = 'publicA';
    protected $protected = 'protectedA';
    private $private = 'privateA';
    private $privateNonShadowed = 'privateNonShadowedA';
}

class TestPropertiesB extends TestPropertiesA
{
    public static $staticPublic = 'staticPublicB';
    protected static $staticProtected = 'staticProtectedB';
    private static $staticPrivate = 'staticPrivateB';
    public $public = 'publicB';
    protected $protected = 'protectedB';
    private $private = 'privateB';
}

class TestToString
{
    public function __toString()
    {
        return 'foo bar';
    }
}

class TestDebugInfo
{
    public $someprop = 'somevalue';

    public function __debugInfo()
    {
        return array(
            'foo' => 'bar',
        );
    }
}
