<?php

namespace PublishPress\Permissions\UI;

class HintsMedia
{
    public static function fileFilteringPromo()
    {
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                $('#posts-filter').after('<a href="#" class="pp-file-filtering-promo"><?php esc_html_e('Block URL access', 'press-permit-core'); ?></a><span class="pp-ext-promo" style="display:none;">'
                + '<?php
                    if (presspermit()->isPro()) {
                        esc_html_e('To block direct URL access to attachments of unreadable posts, activate the File Access module.', 'press-permit-core');
                    } else {
                        printf(
                            esc_html__('To block direct URL access to attachments of unreadable posts, %1$supgrade to Permissions Pro%2$s and enable the File Access module.', 'press-permit-core'),
                            '<a href="https://publishpress.com/pricing/">',
                            '</a>'
                        );
                    }
                ?>'
                + '</span>');

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
