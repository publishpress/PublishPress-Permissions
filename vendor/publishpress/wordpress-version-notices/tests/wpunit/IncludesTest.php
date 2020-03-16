<?php

class IncludesTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \WpunitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    public function test_library_being_loaded_by_the_plugin()
    {
        $this->assertTrue(defined('PP_VERSION_NOTICES_LOADED'), 'Library is not properly loaded');
        $this->assertTrue(class_exists('PPVersionNotices\\ServicesProvider'), 'ServicesProvider class was not found');
    }
}
