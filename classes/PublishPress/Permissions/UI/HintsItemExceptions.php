<?php

namespace PublishPress\Permissions\UI;

class HintsItemExceptions
{
    public static function itemHints($for_item_type)
    {
        $pp = presspermit();

        if (('attachment' == $for_item_type) && !$pp->moduleActive('file-access')) {
            echo "<div class='pp-ext-promo' style='padding:0.5em'>";

            if (presspermit()->isPro()) {
                esc_html_e('To block direct access to unreadable files, activate the File Access module.', 'press-permit-core');
            } else {
                printf(
                    esc_html__('To block direct access to unreadable files, %1$supgrade to Permissions Pro%2$s and install the File Access module.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>'
                );
            }

            echo "</div>";
        }

        if (!$pp->moduleActive('collaboration')) {
            echo "<div class='pp-ext-promo' style='padding:0.5em;margin-top:0'>";
            esc_html_e('To customize editing permissions, enable the Collaborative Publishing module.', 'press-permit-core');
            echo "</div>";
        }
    }
}
