<?php

namespace Kuria\Error\Util;

class PhpCodePreviewTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $html = PhpCodePreview::code($this->getTestCode());

        $this->assertInternalType('string', $html);

        $this->assertContains('<ol class="code-preview">', $html);
        $this->assertContains('</ol>', $html);
        $this->assertContains('Example', $html);
        $this->assertContains('__construct', $html);
        $this->assertContains('sayHello', $html);
        $this->assertContains('echo', $html);

        $this->assertNotContains('class="active"', $html);
        $this->assertNotContains('start=', $html);
    }

    public function testRenderCustomClass()
    {
        $html = PhpCodePreview::code($this->getTestCode(), null, null, 'custom');

        $this->assertInternalType('string', $html);

        $this->assertContains('class="custom"', $html);
        $this->assertNotContains('class="code-preview"', $html);
    }

    public function testRenderActiveLine()
    {
        $html = PhpCodePreview::code($this->getTestCode(), 14);

        $this->assertInternalType('string', $html);

        $this->assertContains('<ol class="code-preview">', $html);
        $this->assertContains('</ol>', $html);
        $this->assertContains('Example', $html);
        $this->assertContains('__construct', $html);
        $this->assertContains('sayHello', $html);
        $this->assertContains('echo', $html);
        $this->assertRegExp('~class="active".+__construct~m', $html);

        $this->assertNotContains('start=', $html);
    }

    /**
     * @expectedException LogicException
     */
    public function testRenderLineRangeRequiresActiveLine()
    {
        PhpCodePreview::code($this->getTestCode(), null, 5);
    }

    public function testRenderActiveLineRange()
    {
        $html = PhpCodePreview::code($this->getTestCode(), 14, 5);

        $this->assertInternalType('string', $html);

        $this->assertContains('<ol ', $html);
        $this->assertContains('</ol>', $html);
        $this->assertContains('start="9"', $html);
        $this->assertContains('protected', $html);
        $this->assertContains('__construct', $html);
        $this->assertContains('$this', $html);
        
        $this->assertRegExp('~class="active".+__construct~m', $html);

        $this->assertNotContains('Example', $html);
        $this->assertNotContains('Lorem', $html);
        $this->assertNotContains('@var', $html);
        $this->assertNotContains('sayHello', $html);
        $this->assertNotContains('echo', $html);
    }
    
    public function testRenderFile()
    {
        $html = PhpCodePreview::file(__FILE__, __LINE__, 5);
        
        $this->assertInternalType('string', $html);

        $this->assertContains('<ol start="' . (__LINE__ - 4 - 5) . '" class="code-preview">', $html);
        $this->assertContains('</ol>', $html);
        $this->assertRegExp('~class="active".+PhpCodePreview~m', $html);
        $this->assertContains('__FILE__', $html);
        $this->assertContains('__LINE__', $html);
    }

    private function getTestCode()
    {
        return <<<'CODE'
<?php

/**
 * Example class
 */
class Lorem extends Ipsum
{
    /** @var string */
    protected $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @param string $to
     */
    public function sayHello($to)
    {
        echo <<<MESSAGE
Hello {$to},
my name is {$this->name}.
MESSAGE;
    }
}

CODE;
    }
}
