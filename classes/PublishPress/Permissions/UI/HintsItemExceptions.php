<?php

namespace PublishPress\Permissions\UI;

class HintsItemExceptions
{
    public static function itemHints($for_item_type)
    {
        $pp = presspermit();

        if (('attachment' == $for_item_type) && !$pp->moduleActive('file-access')) {
            if (presspermit()->isPro()) {
                $msg = __('To block direct access to unreadable files, activate the File Access module.', 'press-permit-core');
            } else {
                $msg = sprintf(
                    __('To block direct access to unreadable files, %1$supgrade to Permissions Pro%2$s and install the File Access module.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>'
                );
            }
            echo "<div class='pp-ext-promo' style='padding:0.5em'>$msg</div>";
        }

        if (!$pp->moduleActive('collaboration')) {
            $msg = __('To customize editing permissions, enable the Collaborative Publishing module.', 'press-permit-core');
            echo "<div class='pp-ext-promo' style='padding:0.5em;margin-top:0'>$msg</div>";
        }
    }
}
