<?php

class TopNoticeCest
{
    public function _before(AcceptanceTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin(['dumb-plugin-one', 'dumb-plugin-two']);
    }

    public function tryToSeeTheDashboardWithoutTheTopNotice(AcceptanceTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnAdminPage('admin.php');
        $I->dontSee('You\'re using Dumb Plugin One Free');
    }

    public function tryToSeeTheTopNoticeForDumbPluginOne(AcceptanceTester $I)
    {
        $I->amOnAdminPage('edit.php?post_type=post');
        $I->see('You\'re using Dumb Plugin One Free', '.pp-version-notice-bold-purple-message');
        $I->dontSee('You\'re using Dumb Plugin Two Free', '.pp-version-notice-bold-purple-message');

        $notices = $I->grabMultiple('.pp-version-notice-bold-purple-message');
        $I->assertEquals(1, count($notices), 'Has more than one notice');
    }

    public function tryToSeeTheTopNoticeForDumbPluginTwo(AcceptanceTester $I)
    {
        $I->amOnAdminPage('edit.php?post_type=page');
        $I->see('You\'re using Dumb Plugin Two Free', '.pp-version-notice-bold-purple-message');
        $I->dontSee('You\'re using Dumb Plugin One Free', '.pp-version-notice-bold-purple-message');

        $notices = $I->grabMultiple('.pp-version-notice-bold-purple-message');
        $I->assertEquals(1, count($notices), 'Has more than one notice');
    }
}
