<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class DashboardWidgetsFilters 
{
    function __construct() {
        if (!class_exists('Glance_That')) {
            add_action('dashboard_glance_items', [$this, 'act_right_now_pending']);
        	add_action('right_now_content_table_end', [$this, 'act_right_now_pending']);
        }
    }

    function act_right_now_pending()
    {
        $post_types = array_diff_key(get_post_types(['public' => true, 'show_ui' => true], 'object', 'or'), ['attachment' => true]);

        $moderation_statuses = ['pending' => get_post_status_object('pending')];
        $post_stati = get_post_stati([], 'object');
        foreach ($post_stati as $status_name => $status_obj) {
            if (!empty($status_obj->moderation)) {
                $moderation_statuses[$status_name] = $status_obj;
            }
        }

        $moderation_statuses = apply_filters('presspermit_order_statuses', $moderation_statuses);

        $tag_open = '<div style="padding-bottom:4px">';
        $tag_close = '</div>';

        foreach ($post_types as $post_type => $post_type_obj) {
            if ($num_posts = wp_count_posts($post_type)) {
                foreach ($moderation_statuses as $status_name => $status_obj) {
                    if (!empty($num_posts->$status_name) && ('pending' != $status_name)) {
                        echo "\n\t" . $tag_open;

                        $num = number_format_i18n($num_posts->$status_name);

                        if (intval($num_posts->$status_name) <= 1) {
                            $text = sprintf(__('%1$s %2$s', 'press-permit-core'), $status_obj->label, $post_type_obj->labels->singular_name);
                        } else {
                            $text = sprintf(__('%1$s %2$s', 'press-permit-core'), $status_obj->label, $post_type_obj->labels->name);
                        }
                        
                        $type_clause = ('post' == $post_type) ? '' : "&post_type=$post_type";

                        $url = "edit.php?post_status=$status_name{$type_clause}";
                        $num = "<a href='$url'><span class='pending-count'>$num</span></a> ";
                        $text = "<a class='waiting' href='$url'>$text</a>";

                        $type_class = ($post_type_obj->hierarchical) ? 'b-pages' : 'b-posts';

                        echo '<td class="first b ' . $type_class . ' b-waiting">' . $num . '</td>';
                        echo '<td class="t posts">' . $text . '</td>';
                        echo '<td class="b"></td>';
                        echo '<td class="last t"></td>';
                        echo "{$tag_close}\n\t";
                    }
                }
            }
        }

        echo '<div style="height:5px"></div>';
    }
}