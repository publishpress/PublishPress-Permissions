<?php

namespace Template;

use PPVersionNotices\Template\TemplateLoader;
use PPVersionNotices\Template\TemplateLoaderInterface;
use PPVersionNotices\Template\TemplateNotFoundException;

class TemplateLoaderTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \WpunitTester
     */
    protected $tester;

    /**
     * @var TemplateLoaderInterface
     */
    protected $templateLoader;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        $templatePath = PP_VERSION_NOTICES_BASE_PATH . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . '_data' .
            DIRECTORY_SEPARATOR . 'dumb-templates';

        $this->templateLoader = new TemplateLoader($templatePath);
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    // Tests
    public function test_exception_if_template_is_not_found_when_displaying()
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->templateLoader->displayOutput('NotExistent', 'any');
    }

    public function test_exception_if_template_is_not_found_when_returning()
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->templateLoader->returnOutput('NotExistent', 'any');
    }

    public function test_displayed_template_output()
    {
        $expected = '<h1>Test1</h1>';

        $this->expectOutputString($expected);

        $this->templateLoader->displayOutput('Dumb', 'test1');
    }

    public function test_returned_template_output()
    {
        $expected = '<h1>Test1</h1>';

        $output = $this->templateLoader->returnOutput('Dumb', 'test1');

        $this->assertEquals($expected, $output);
    }

    public function test_displayed_template_output_with_context()
    {
        $expected = '<h1>Test2: bar1, bar2</h1>';

        $this->expectOutputString($expected);

        $context = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];

        $this->templateLoader->displayOutput('Dumb', 'test2', $context);
    }

    public function test_returned_template_output_with_context()
    {
        $expected = '<h1>Test2: bar1, bar2</h1>';

        $context = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];

        $output = $this->templateLoader->returnOutput('Dumb', 'test2', $context);


        $this->assertEquals($expected, $output);
    }
}
