<?php

namespace Module\TopNotice;

use Pimple\Container;
use PPVersionNotices\Module\AdInterface;
use PPVersionNotices\Module\TopNotice\Module;
use PPVersionNotices\ServicesProvider;
use PPVersionNotices\Template\TemplateInvalidArgumentsException;

class ModuleTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \WpunitTester
     */
    protected $tester;

    /**
     * @var AdInterface
     */
    private $module;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        $container = new Container();
        $container->register(new ServicesProvider());

        $this->module = $container['module_top_notice'];
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    // Tests
    public function test_module_dont_enqueue_admin_assets_on_invalid_page()
    {
        // Force a valid page - based on the Dumb plugin
        set_current_screen('users.php');

        do_action('admin_init');
        do_action('admin_enqueue_scripts');

        $wp_styles = wp_styles();
        $this->assertNotContains('pp-version-notice-bold-purple-style', $wp_styles->queue);
        $this->assertNotContains('pp-version-notice-bold-purple-script', $wp_styles->queue);
    }

    public function test_module_enqueue_admin_assets_on_valid_page()
    {
        // Force a valid page - based on the Dumb plugin
        set_current_screen('edit.php');

        do_action('admin_init');
        do_action('admin_enqueue_scripts');

        $wp_styles = wp_styles();
        $this->assertContains('pp-version-notice-bold-purple-style', $wp_styles->queue);
    }

    public function test_module_add_action_to_display()
    {
        global $wp_filter;

        $this->assertArrayHasKey(Module::DISPLAY_ACTION, $wp_filter,
            'The action is not defined');
    }

    public function test_module_display_with_no_arguments_throws_exception()
    {
        $this->expectException(TemplateInvalidArgumentsException::class);

        try {
            ob_start();
            $this->module->display();
        } finally {
            ob_end_clean();
        }
    }

    public function test_module_display_with_arguments_returns_output()
    {
        $expected = '<div class="pp-version-notice-bold-purple"><div class="pp-version-notice-bold-purple-message">You\'re using Dumb Plugin Free. Please, </div><div class="pp-version-notice-bold-purple-button"><a href="http://example.com/upgrade" target="_blank">upgrade to pro</a></div>.</div>';

        $this->expectOutputString($expected);

        $message = 'You\'re using Dumb Plugin Free. Please, %supgrade to pro%s.';
        $link    = 'http://example.com/upgrade';

        $this->module->display($message, $link);
    }

    public function test_module_display_with_no_arguments_throws_exception_for_action()
    {
        $this->expectException(TemplateInvalidArgumentsException::class);

        try {
            ob_start();
            do_action(Module::DISPLAY_ACTION);
        } finally {
            ob_end_clean();
        }
    }

    public function test_module_display_with_arguments_returns_output_for_action()
    {
        $expected = '<div class="pp-version-notice-bold-purple"><div class="pp-version-notice-bold-purple-message">You\'re using Dumb Plugin Free. Please, </div><div class="pp-version-notice-bold-purple-button"><a href="http://example.com/upgrade" target="_blank">upgrade to pro</a></div>.</div>';

        $this->expectOutputString($expected);

        $message = 'You\'re using Dumb Plugin Free. Please, %supgrade to pro%s.';
        $link    = 'http://example.com/upgrade';

        do_action(Module::DISPLAY_ACTION, $message, $link);
    }

    public function test_module_display_with_arguments_returns_output_for_action_and_multiple_plugins()
    {
        $expected = <<<OUTPUT
<div class="pp-version-notice-bold-purple">
    <span class="pp-version-notice-bold-purple-message">You're using Test A Free. Please, <a href="http://example.com/upgrade-a" target="_blank">upgrade to pro</a>.</span>
</div>
OUTPUT;

        $messageA = 'You\'re using Test A Free. Please, %supgrade to pro%s.';
        $linkA    = 'http://example.com/upgrade-a';

        do_action(Module::DISPLAY_ACTION, $messageA, $linkA);

        // --------------------------
        $expected = <<<OUTPUT
<div class="pp-version-notice-bold-purple">
    <span class="pp-version-notice-bold-purple-message">You're using Test B Free. Please, <a href="http://example.com/upgrade-b" target="_blank">upgrade to pro</a>.</span>
</div>
OUTPUT;

        $messageB = 'You\'re using Test B Free. Please, %supgrade to pro%s.';
        $linkB    = 'http://example.com/upgrade-b';

        do_action(Module::DISPLAY_ACTION, $messageB, $linkB);
    }

    public function test_module_doesnt_display_the_ad_on_invalid_page()
    {
        // Force a valid page - based on the Dumb plugin
        set_current_screen('users.php');

        ob_start();
        do_action('in_admin_header');

        $output = ob_get_clean();

        $this->assertStringNotContainsString('You\'re using Dumb Plugin', $output);
    }

    public function test_module_displays_the_ad_on_valid_page()
    {
        $expected = '<div class="pp-version-notice-bold-purple"><div class="pp-version-notice-bold-purple-message">You\'re using Dumb Plugin One Free. Please, </div><div class="pp-version-notice-bold-purple-button"><a href="http://example.com/upgrade" target="_blank">upgrade to pro</a></div>.</div>';

        $this->expectOutputString($expected);

        // Force a valid page - based on the Dumb plugin
        set_current_screen('edit.php');

        do_action('in_admin_header');
    }
}
