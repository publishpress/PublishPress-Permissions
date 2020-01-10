<?php

namespace PublishPress\Permissions\UI\Dashboard;

class Help
{
    public static function registerContextualHelp()
    {
        $screen_obj = get_current_screen();

        if (is_object($screen_obj)) {
            $screen = $screen_obj->id;
        } else {
            return;
        }

        if (strpos($screen, 'presspermit-')) {
            $match = [];
            if (!preg_match("/admin_page_pp-[^@]*-*/", $screen, $match)) {
                if (!preg_match("/_page_pp-[^@]*-*/", $screen, $match)) {
                    preg_match("/pp-[^@]*-*/", $screen, $match);
                }
            }

            if ($match) {
                if ($pos = strpos($match[0], 'presspermit-')) {
                    $link_section = substr($match[0], $pos + strlen('presspermit-'));
                    $link_section = str_replace('_t', '', $link_section);
                }
            }
        } elseif (in_array($screen_obj->base, ['post', 'page', 'upload', 'users', 'edit-tags', 'edit'], true)) {
            $link_section = $screen_obj->base;
        }

        if (!empty($link_section)) {
            $screen_obj->add_help_tab([
                'id' => 'presspermit',            //unique id for the tab
                'title' => __('Permissions Help', 'press-permit-core'),      //unique visible title for the tab
                'content' => '',  //actual help text
                'callback' => ['\PublishPress\Permissions\UI\Dashboard\Help', 'showContextualHelp'], //optional function to callback
            ]);
        }
    }

    public static function showContextualHelp()
    {
        $help = '';

        $opt_val = presspermit()->getOption('edd_key');

        if (!is_array($opt_val) || count($opt_val) < 2) {
            $activated = false;
            $expired = false;
        } else {
            $activated = ('valid' == $opt_val['license_status']);
            $expired = ('expired' == $opt_val['license_status']);
        }

        if (!empty($expired)) : ?>
            <div class="activating"><span class="pp-key-wrap pp-key-expired">
                <?php
                printf(
                    __('Your license key has expired. For priority support and Pro modules, <a href="%s" target="_blank">please renew</a>', 'press-permit-core'),
                    'admin.php?page=presspermit-settings&amp;pp_renewal=1'
                );
                ?>
            </span></div>
        <?php elseif (empty($activated)) : ?>
            <div class="activating">
                <p><span class="pp-key-wrap pp-key-expired">
                    <?php
                    printf(
                        __('For priority support and Pro modules, <a href="%1$s">activate your license key</a>', 'press-permit-core'),
                        'admin.php?page=presspermit-settings'
                    );
                    ?>
                </span></p>
                <p><span class="pp-key-wrap pp-key-expired">
                    <?php
                    printf(
                        __('If you need a key, <a href="%s" target="_blank">Explore Pro packages</a>', 'press-permit-core'),
                        'https://publishpress.com/pricing/'
                    );
                    ?>
                </span></p>
            </div>
        <?php endif;

        $help .= '<ul><li>' . sprintf(
                __('%1$s PublishPress Permissions Documentation%2$s', 'press-permit-core'),
                "<a href='https://publishpress.com/presspermit/' target='_blank'>",
                '</a>'
            ) . '</li>';

        if (!empty($expired) || empty($activated)) {
            $help .= '<li>' . sprintf(
                    __('%1$s Submit a Help Ticket%2$s', 'press-permit-core'),
                    "<a href='admin.php?page=presspermit-settings&amp;pp_help_ticket=1' target='_blank'>",
                    '</a></li>'
                );
        } else {
            $help .= '<li>' . sprintf(
                    __('%1$s Submit a Help Ticket (with config data upload)%2$s *', 'press-permit-core'),
                    "<a href='admin.php?page=presspermit-settings&amp;pp_help_ticket=1' target='_blank'>",
                    '</a>'
                ) . '</li>';
        }

        $help .= '</ul>';

        if (empty($expired) && !empty($activated)) {
            $help .= '<div>';
            $help .= __('* to control which configuration data is uploaded, see Permissions > Settings > Install > Help', 'press-permit-core');
            $help .= '</div>';
        } else {
            $help .= '<p></p>';
        }

        echo $help;
    }
}
