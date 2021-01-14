<?php

namespace PublishPress\Permissions\UI;

class HintsPostEdit
{
    public static function postStatusPromo()
    {
        $pp = presspermit();

        if (!$pp->moduleActive('status-control')) {
            if ($pp->moduleExists('status-control')) {
                $msg = __('To define custom privacy statuses, activate the Status Control module.', 'press-permit-core');
            } else {
                $msg = sprintf(
                    __('To define custom privacy statuses, %1$supgrade to Permissions Pro%2$s and enable the Status Control module.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>'
                );
            }
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('#visibility-radio-private').next('label').after('<a href="#" class="pp-custom-privacy-promo" style="margin-left:5px"><?php _e('define custom privacy', 'press-permit-core'); ?></a><span class="pp-ext-promo" style="display:none;"><br /><?php echo $msg; ?></span>');

                    $('a.pp-custom-privacy-promo').on('click', function()
                    {
                        $(this).hide().next('span').show();
                        return false;
                    });

                });
                /* ]]> */
            </script>
            <?php
        }

        if (!$pp->moduleActive('status-control') || !$pp->moduleActive('collaboration')) {
            $need_exts = [];
            if (!$pp->moduleActive('collaboration'))
                $need_exts[] = 'Collaborative Publishing';

            if (!$pp->moduleActive('status-control'))
                $need_exts[] = 'Status Control';

            $need_exts = implode(' and ', $need_exts);

            if (presspermit()->isPro()) {
                $msg = sprintf(
                    __('To define publication workflow statuses, %1$sactivate%2$s %3$s.', 'press-permit-core'),
                    '<a href="admin.php?page=presspermit-settings&pp_tab=install">',
                    '</a>',
                    $need_exts
                );
            } else {
                $msg = sprintf(
                    __('To define publication workflow statuses, %1$supgrade to Permissions Pro%2$s. Then enable the following modules: %3$s.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>',
                    $need_exts
                );
            }
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('a.edit-post-status').after('<a href="#" class="pp-custom-moderation-promo" style="margin-left:5px"><?php _e('Customize', 'press-permit-core'); ?></a><span class="pp-ext-promo" style="display:none;"><br /><?php echo $msg; ?></span>');

                    $('a.pp-custom-moderation-promo').on('click', function()
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
