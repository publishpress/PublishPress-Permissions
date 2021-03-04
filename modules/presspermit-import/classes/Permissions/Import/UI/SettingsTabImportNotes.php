<?php
namespace PublishPress\Permissions\Import\UI;

class SettingsTabImportNotes
{
    function displayNotes() {
        ?>
        <ul class="pp-notes">
            <li><?php _e('The import can be run multiple times if source values change.', 'press-permit-core-hints'); ?></li>
            <li><?php _e('Configuration items will be imported even if the request exceeds PHP execution time limit. Repeat as necessary until all items are imported.', 'press-permit-core-hints'); ?></li>
            <li><?php _e('Current Role Scoper configuration is not modified or deleted. You will still be able to restore previous behavior by reactivating Role Scoper if necessary.', 'press-permit-core-hints'); ?></li>
            <li><?php _e('Following import, you should manually review the results and confirm that permissions are correct. Some manual followup may be required.', 'press-permit-core-hints'); ?></li>
            <li><?php _e('If your Role Scoper configuration has Category Restrictions on the Author or Editor role, specific Publish Permissions will be enabled to control publishing permissions separate from editing permissions. Existing specific Edit Permissions will be mirrored as specific Publish Permissions to maintain previous access.', 'press-permit-core-hints'); ?></li>
            <li><?php _e('Category Restrictions on the Editor role are converted to specific Edit, Publish and Term Assignment Permissions. If a Post Editor should be blocked from editing other&apos;s posts within a specified category but still be able to submit / edit / publish their own posts in that category, they will need to be switched to a WordPress role that does not have the "edit_others_pages" capability. Then specific editing permissions can be granted per-category.', 'press-permit-core-hints'); ?></li>
        </ul>
        <?php
    }
}
