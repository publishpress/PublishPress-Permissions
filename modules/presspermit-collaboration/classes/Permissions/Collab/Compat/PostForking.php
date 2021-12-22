<?php
namespace PublishPress\Permissions\Collab\Compat;

class PostForking
{
    function __construct()
    {
        global $wpdb;
        add_filter('option_' . $wpdb->prefix . 'user_roles', [$this, 'bypass_forking_negation_rolecaps'], 60);
        $this->prevent_redundant_role_update();

        add_action('admin_init', [$this, 'regulate_action_links'], 90);

        // preliminary support for Post Forking plugin
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            add_filter('presspermit_apply_additions', [$this, 'flt_apply_additions'], 10, 5);
            add_filter('user_has_cap', [$this, 'fltUserHasCap'], 100, 3);
        }
    }

    // Post Forking's implementation is problematic in that supplemental bbPress / BuddyPress roles receive negative caps, 
    // blocking associated users (including admin) from editing forks
    function bypass_forking_negation_rolecaps($user_roles)
    {
        global $fork;

        foreach (array_keys($user_roles) as $role_name) {
            if (!in_array($role_name, ['subscriber', 'contributor', 'author', 'editor', 'administrator'], true)) {
                foreach (array_intersect_key($user_roles[$role_name]['capabilities'], $fork->capabilities->defaults['subscriber']) as $cap => $stored_val) {
                    if (!$stored_val)
                        unset($user_roles[$role_name]['capabilities'][$cap]);
                }
            }
        }

        return $user_roles;
    }

    function regulate_action_links()
    {
        global $pagenow;

        if ('edit.php' == $pagenow) {
            $any_caps = $this->any_forking_caps();
            $fork_published_only = ($any_caps) ? presspermit()->getOption('fork_published_only') : false;

            if (!$any_caps || $fork_published_only) {
                global $fork;
                remove_filter('post_row_actions', [$fork->admin, 'row_actions'], 10, 2);
                remove_filter('page_row_actions', [$fork->admin, 'row_actions'], 10, 2);
            }

            if ($fork_published_only) {
                add_filter('post_row_actions', [$this, 'row_fork_actions'], 10, 2);
                add_filter('page_row_actions', [$this, 'row_fork_actions'], 10, 2);
            }
        }
    }

    function row_fork_actions($actions, $post)
    {
        global $fork;

        if (post_type_supports(get_post_type($post), 'fork')) {
            if ($status_obj = get_post_status_object($post->post_status)) {
                if (!empty($status_obj->public) || !empty($status_obj->private)) {
                    $label = ($fork->branches->can_branch($post)) ? __('Create branch', 'fork') : __('Fork', 'fork');
                    $actions[] = '<a href="' . admin_url("?fork={$post->ID}") . '">' . $label . '</a>';
                }
            }
        }

        if (Fork::post_type == get_post_type($post)) {
            $parent = $fork->revisions->get_previous_revision($post);
            $actions[] = '<a href="' . admin_url("revision.php?action=diff&left={$parent}&right={$post->ID}") . '">' . __('Compare', 'fork') . '</a>';
        }

        return $actions;
    }

    function flt_apply_additions($additions, $where, $required_operation, $post_type, $args = [])
    {
        $defaults = ['source_alias' => '', 'has_cap_check' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        if (!is_admin() || $pp->isContentAdministrator() || !$this->any_forking_caps() || ('edit' != $required_operation))
            return $additions;

        if ($has_cap_check && ('fork_post' != $has_cap_check))
            return $additions;

        $_args = [];
        $_args['append_post_type_clause'] = false;

        if ($fork_exceptions = \PublishPress\Permissions\DB\Permissions::addExceptionClauses('1=1', 'fork', $post_type, $_args)) {
            if ($pp->getOption('fork_published_only')) {
                global $wpdb;
                $stati = apply_filters('presspermit_forkable_stati', get_post_stati(['public' => true, 'private' => true], 'names', 'or'));
                $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
                $status_clause = "$src_table.post_status IN ('" . implode("','", $stati) . "') AND ";
            } else
                $status_clause = '';

            $author_clause = '';
            if ($pp->getOption('fork_require_edit_others')) {
                global $wpdb;

                $user = presspermit()->getUser();

                $type_obj = get_post_type_object($post_type);
                if ($type_obj && empty($user->allcaps[$type_obj->cap->edit_others_posts])) {
                    $src_table = ($source_alias) ? $source_alias : $wpdb->posts;
                    $author_clause = "$src_table.post_author = $user->ID AND ";
                }
            }

            if ($status_clause || $author_clause)
                $additions[] = "{$status_clause}{$author_clause}( $fork_exceptions )";
            else
                $additions[] = $fork_exceptions;
        }

        return $additions;
    }

    // $wp_sitecaps = current user's site-wide capabilities
    // $reqd_caps = primitive capabilities being tested / requested
    // $args = array with:
    //      $args[0] = original capability requirement passed to current_user_can (possibly a meta cap)
    //      $args[1] = user being tested
    //      $args[2] = post id (could be a post_id, link_id, term_id or something else)
    //
    function fltUserHasCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        if (('edit_post' == $args[0]) && !empty($args[2])) {
            $user = presspermit()->getUser();

            if (($args[1] != $user->ID) || !presspermit()->getOption('fork_require_edit_others'))
                return $wp_sitecaps;

            $_post = get_post($args[2]);
            if ('fork' == $_post->post_type) {
                if (get_post_field('post_author', $_post->post_parent) != $user->ID) {
                    if ($parent_type_obj = get_post_type_object(get_post_field('post_type', $_post->post_parent))) {
                        if (empty($user->allcaps[$parent_type_obj->cap->edit_others_posts])) {
                            unset($wp_sitecaps['edit_forks']);
                        }
                    }
                }
            }
        }

        return $wp_sitecaps;
    }

    private function prevent_redundant_role_update()
    {
        if (get_option('ppce_fork_caps_stored')) {
            global $fork;

            if (!empty($fork) && !empty($fork->capabilities)) {
                remove_action('init', [&$fork->capabilities, 'add_caps']);
            }
        } else {
            update_option('ppce_fork_caps_stored', true);
        }
    }

    private function any_forking_caps($post_type = '')
    {
        if (presspermit()->isContentAdministrator())
            return true;

        if (!$post_type)
            $post_type = PWP::findPostType();

        if (in_array($post_type, presspermit()->getEnabledPostTypes(), true)) {
            $ids = presspermit()->getUser()->getExceptionPosts('fork', 'include', $post_type);

            if (count($ids)) { // if this user has an exception to fork only "none", indicate no capabilities
                if ((1 == count($ids)) && (0 == reset($ids)))
                    return false;
            }
        }

        return post_type_supports($post_type, 'fork');
    }
}
