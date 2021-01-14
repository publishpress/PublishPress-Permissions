<?php

namespace PublishPress\Permissions\UI;

class HintsMedia
{
    public static function fileFilteringPromo()
    {
        if (presspermit()->isPro()) {
            $msg = __('To block direct URL access to attachments of unreadable posts, activate the File Access module.', 'press-permit-core');
        } else {
            $msg = sprintf(
                __('To block direct URL access to attachments of unreadable posts, %1$supgrade to Permissions Pro%2$s and enable the File Access module.', 'press-permit-core'),
                '<a href="https://publishpress.com/pricing/">',
                '</a>'
            );
        }
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                $('#posts-filter').after('<a href="#" class="pp-file-filtering-promo"><?php _e('Block URL access', 'press-permit-core'); ?></a><span class="pp-ext-promo" style="display:none;"><?php echo $msg; ?></span>');

                $('a.pp-file-filtering-promo').on('click', function()
                {
                    $(this).hide().next('span').show();
                    return false;
                });

            });
            /* ]]> */
        </script>
        <?php
    }
}
