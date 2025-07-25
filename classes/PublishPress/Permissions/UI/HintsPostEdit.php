<?php

namespace PublishPress\Permissions\UI;

class HintsPostEdit
{
    public static function postStatusPromo()
    {
        if (!defined('PUBLISHPRESS_STATUSES_VERSION')) {
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('a.edit-post-status').after('<a href="#" class="pp-statuses-promo" style="margin-left:5px"><?php esc_html_e('Customize', 'press-permit-core'); ?></a>'
                    + '<span class="pp-ext-promo" style="display:none;"><br />'
                    + '<?php
                    printf(
                        esc_html__('To customize publication workflow, %1$sinstall PublishPress Statuses%2$s.', 'press-permit-core'),
                        '<a href="https://wordpress.org/plugins/publishpress-statuses/" target="_blank">',
                        '</a>'
                    );
                    ?>'
                    + '</span>');

                    $('a.pp-statuses-promo').on('click', function()
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
}
