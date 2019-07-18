<?php

namespace PublishPress\Permissions;

class FrontFilters
{
    public function __construct() {
        if ( $this->isActiveWidgetPrefix('calendar-')) {
            add_filter('query', [$this, 'fltCalendar']);
        }

        if (presspermit()->getOption('strip_private_caption')) {
            add_filter('the_title', [$this, 'fltTitle'], 10, 3);

            if (defined('WPLANG') && WPLANG) {
                add_filter('gettext', [$this, 'fltGetText'], 10, 3);
            }
        }

        add_action('wp_print_footer_scripts', [$this, 'fltHideEmptyMenus']);
    }

    private function isActiveWidgetPrefix($id_prefix)
    {
        global $wp_registered_widgets;

        foreach ((array)wp_get_sidebars_widgets() as $sidebar => $widgets) {
            if ('wp_inactive_widgets' != $sidebar && is_array($widgets)) {
                foreach ($widgets as $widget) {
                    if (
                        isset($wp_registered_widgets[$widget]['id'])
                        && (0 === strpos($wp_registered_widgets[$widget]['id'], $id_prefix))
                    ) {
                        return $sidebar;
                    }
                }
            }
        }

        return false;
    }

    public function fltHideEmptyMenus()
    {
        if (!wp_script_is('jquery')) {
            return;
        }
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                $("ul.nav-menu").not(":has(li)").hide().closest('div').hide();
            });
            /* ]]> */
        </script><?php
    }

    public function fltTitle($title)
    {
        if (0 === strpos($title, 'Private: ') || 0 === strpos($title, 'Protected: ')) {
            $title = substr($title, strpos($title, ':') + 2);
        }

        return $title;
    }

    public function fltGetText($translated_text, $orig_text)
    {
        if (('Private: %s' == $orig_text) || ('Protected: %s' == $orig_text)) {
            $translated_text = '%s';
        }

        return $translated_text;
    }

    public function fltCalendar($query)
    {
        if (
            strpos($query, "DISTINCT DAYOFMONTH") || strpos($query, "post_title, DAYOFMONTH(post_date)")
            || strpos($query, "MONTH(post_date) AS month")
        ) {
            $query = apply_filters('presspermit_posts_request', $query);
        }

        return $query;
    }
}
