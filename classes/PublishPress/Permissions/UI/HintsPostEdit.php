<?php

namespace PublishPress\Permissions\UI;

class HintsPostEdit
{
    public static function postStatusPromo()
    {
        $pp = presspermit();

        if (!$pp->moduleActive('status-control')) {
            $custom_msg = printf(
                esc_html__('To define custom privacy statuses, %1$supgrade to Permissions Pro%2$s and enable the Status Control module.', 'press-permit-core'),
                '<a href="https://publishpress.com/pricing/">',
                '</a>'
            );
            if ($pp->moduleExists('status-control')) {
                $custom_msg = esc_html_e('To define custom privacy statuses, activate the Status Control module.', 'press-permit-core');
            }
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    $('#visibility-radio-private').next('label').after('<a href="#" class="pp-custom-privacy-promo" style="margin-left:5px"><?php esc_html_e('define custom privacy', 'press-permit-core'); ?></a>' +
                        '<span class="pp-ext-promo" style="display:none;"><br />' +
                        '<?php $custom_msg; ?>' +
                        '</span>');

                    $('a.pp-custom-privacy-promo').on('click', function() {
                        $(this).hide().next('span').show();
                        return false;
                    });
                });
                /* ]]> */
            </script>
            <?php
        }

        if (!defined('PUBLISHPRESS_STATUSES_VERSION')) {
            $custom_msg = printf(
                esc_html__('To customize publication workflow, %1$sinstall PublishPress Statuses%2$s.', 'press-permit-core'),
                '<a href="https://wordpress.org/plugins/publishpress-statuses/" target="_blank">',
                '</a>'
            );
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function($) {
                    $('a.edit-post-status').after('<a href="#" class="pp-statuses-promo" style="margin-left:5px"><?php esc_html_e('Customize', 'press-permit-core'); ?></a>' +
                        '<span class="pp-ext-promo" style="display:none;"><br />' +
                        '<?php $custom_msg; ?>' +
                        '</span>');

                    $('a.pp-statuses-promo').on('click', function() {
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
