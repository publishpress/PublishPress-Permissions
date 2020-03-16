<?php 

class ActivatePluginsCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    public function tryToActivateDumbPluginsUsingTheLibrary(AcceptanceTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin(['dumb-plugin-one', 'dumb-plugin-two']);
        $I->canSeePluginActivated('dumb-plugin-one');
        $I->canSeePluginActivated('dumb-plugin-two');
    }
}
