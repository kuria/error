<?php declare(strict_types=1);

namespace Kuria\Error\Screen\Util;

use PHPUnit\Framework\TestCase;

class PhpCodePreviewTest extends TestCase
{
    function testRender()
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

    function testRenderCustomClass()
    {
        $html = PhpCodePreview::code($this->getTestCode(), null, null, 'custom');

        $this->assertInternalType('string', $html);

        $this->assertContains('class="custom"', $html);
        $this->assertNotContains('class="code-preview"', $html);
    }

    function testRenderActiveLine()
    {
        $html = PhpCodePreview::code($this->getTestCode(), 11);

        $this->assertInternalType('string', $html);

        $this->assertContains('<ol class="code-preview">', $html);
        $this->assertContains('</ol>', $html);
        $this->assertContains('Example', $html);
        $this->assertContains('__construct', $html);
        $this->assertContains('sayHello', $html);
        $this->assertContains('echo', $html);
        $this->assertRegExp('{class="active".+__construct}m', $html);

        $this->assertNotContains('start=', $html);
    }

    function testRenderLineRangeRequiresActiveLine()
    {
        $this->expectException(\LogicException::class);

        PhpCodePreview::code($this->getTestCode(), null, 5);
    }

    function testRenderActiveLineRange()
    {
        $html = PhpCodePreview::code($this->getTestCode(), 11, 2);

        $this->assertInternalType('string', $html);

        $this->assertContains('<ol ', $html);
        $this->assertContains('</ol>', $html);
        $this->assertContains('start="9"', $html);
        $this->assertContains('protected', $html);
        $this->assertContains('__construct', $html);
        $this->assertContains('$this', $html);
        
        $this->assertRegExp('{class="active".+__construct}m', $html);
        $this->assertNotContains('@var', $html);
        $this->assertNotContains('Example', $html);
        $this->assertNotContains('Lorem', $html);
        $this->assertNotContains('sayHello', $html);
        $this->assertNotContains('echo', $html);
    }
    
    function testRenderFile()
    {
        $html = PhpCodePreview::file(__FILE__, __LINE__, 5);
        
        $this->assertInternalType('string', $html);

        $this->assertContains('<ol start="' . (__LINE__ - 4 - 5) . '" class="code-preview">', $html);
        $this->assertContains('</ol>', $html);
        $this->assertRegExp('{class="active".+PhpCodePreview}m', $html);
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

    function __construct(string $name)
    {
        $this->name = $name;
    }

    function sayHello(string $to): void
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
