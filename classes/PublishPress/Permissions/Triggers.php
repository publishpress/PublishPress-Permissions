<?php

namespace PublishPress\Permissions;

/**
 * Triggers class
 *
 * Deals with content, user or site changes which may require a 
 * corresponding permissions data update or other action. 
 * 
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2019, PublishPress
 *
 */
class Triggers
{
    private $users_to_sync = [];

    public function __construct()
    {
        // Content, user or site changes which require attention

        // =============== Post Maintenance =================
        add_action('save_post', [$this, 'actSavePost'], 10, 2);
        add_action('delete_post', [$this, 'actDeletePost']);

        add_action('edit_attachment', [$this, 'actEditAttachment']);

        // log post status transition to recognize new posts and status change to/from private
        add_action('transition_post_status', [$this, 'actLogPostStatus'], 10, 3);

        // =============== Term Maintenance =================
        add_action('created_term', [$this, 'actSaveTerm'], 10, 3);
        add_action('edited_term', [$this, 'actSaveTerm'], 10, 3);
        add_action('delete_term', [$this, 'actDeleteTerm'], 10, 3);

        // @todo: make this optional
        // include private posts in the post count for each term
        global $wp_taxonomies;
        foreach ($wp_taxonomies as $key => $t) {
            if (isset($t->update_count_callback) && ('update_post_term_count' == $t->update_count_callback)) {
                $wp_taxonomies[$key]->update_count_callback = [$this, 'updatePostTermCount'];
            }
        }

        // =============== User Maintenance =================
        add_action('profile_update', [$this, 'actSyncWordPressRoles']);
        add_action('profile_update', [$this, 'actUpdateUserGroups']);
        add_action('set_user_role', [$this, 'actScheduleRoleSync']);

        if (is_multisite()) {
            add_action('remove_user_from_blog', [$this, 'actDeleteUsers'], 10, 2);
            add_action('add_user_to_blog', [$this, 'actScheduleUserSync'], 10, 3);
            add_action('deleted_user', [$this, 'actDeleteUsersFromNetwork']);
        } else {
            add_action('user_register', [$this, 'actScheduleUserSync'], 10, 3);
            add_action('user_register', [$this, 'actSetNewUserGroups']);
            add_action('delete_user', [$this, 'actDeleteUsers']);
        }

        add_action('presspermit_deleted_group', [$this, 'actDeletedGroup'], 10, 2);

        add_filter('editable_roles', [$this, 'fltHideRoles']);

        // Follow up on role creation / deletion by Capability Manager or other equivalent plugin
        // Capability Manager doesn't actually modify the stored role def until after the option update we're hooking on, so defer our maintenance operation
        global $wpdb;
        add_action("update_option_{$wpdb->prefix}user_roles", [$this, 'actScheduleRoleSync']);

        global $wp_roles; // fires on role creation / deletion
        if (!empty($wp_roles)) {
            add_filter("update_option_{$wp_roles->role_key}", [$this, 'fltUpdateWpRoles']);
        }
    }

    public function fltHideRoles($roles)
    {
        if ($pp_only = (array) presspermit()->getOption('supplemental_role_defs')) {

            if (!empty($_REQUEST['user_id'])) {  // display role already set for this user, regardless of pp_only setting
                $user = new \WP_User((int)$_REQUEST['user_id']);
                if (!empty($user->roles)) {
                    $pp_only = array_diff($pp_only, $user->roles);
                }
            }

            $roles = array_diff_key($roles, array_fill_keys($pp_only, true));
        }
        return $roles;
    }

    public function fltUpdateWpRoles($roles)
    {
        global $wp_roles;
        wp_cache_delete($wp_roles->role_key, 'options');
        $wp_roles = new \WP_Roles();

        $this->actSyncWordPressRoles();
        return $roles;
    }

    public function actLogPostStatus($new_status, $old_status, $post)
    {
        presspermit()->admin()->setLastPostStatus($post->ID, $old_status);
    }

    public function actEditAttachment($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        require_once(PRESSPERMIT_CLASSPATH . '/PostSave.php');
        PostSave::actSaveItem('post', $post_id, false);
    }

    public function actSavePost($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!empty(presspermit()->flags['ignore_save_post'])) {
            return;
        }

        require_once(PRESSPERMIT_CLASSPATH . '/PostSave.php');
        PostSave::actSaveItem('post', $post_id, $post);
    }

    public function actDeletePost($post_id)
    {
        // could defer role/cache maint to speed potential bulk deletion, but script may be interrupted before admin_footer
        require_once(PRESSPERMIT_CLASSPATH . '/ItemDelete.php');
        return ItemDelete::itemDeleted('post', $post_id);
    }

    public function actSaveTerm($term_id, $tt_id, $taxonomy)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/TermSave.php');
        return TermSave::actSaveItem($term_id, $tt_id, $taxonomy);
    }

    public function actDeleteTerm($term_id, $tt_id, $taxonomy)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/ItemDelete.php');
        ItemDelete::itemDeleted('term', $tt_id);
    }

    public function actSetNewUserGroups($user_id)
    {
        $this->doGroupUpdate($user_id);
    }

    public function actUpdateUserGroups($user_id)
    {
        if (empty($_POST['pp_editing_user_groups'])) { // otherwise we'd delete group assignments if another plugin calls do_action('profile_update') unexpectedly
            return;
        }

        $this->doGroupUpdate($user_id);
    }

    private function doGroupUpdate($user_id)
    {
        global $wpdb;
        $metagroup_ids = $wpdb->get_col("SELECT ID FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role'");

        require_once(PRESSPERMIT_CLASSPATH . '/UserGroupsUpdate.php');
        UserGroupsUpdate::addUserGroups($user_id, $metagroup_ids);
        UserGroupsUpdate::removeUserGroups($user_id, $metagroup_ids);
    }

    public function actDeleteUsers($user_ids, $blog_id_arg = 0)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/GroupUpdate.php');

        foreach ((array)$user_ids as $user_id) {
            DB\GroupUpdate::deleteUserFromGroups($user_id);
        }

        ppc_delete_agent_permissions($user_ids, 'user');
    }

    public function actDeleteUsersFromNetwork($user_ids)
    {
        $user_ids = (array)$user_ids;  // as of WP 3.6, passed value is not an array, but allow for forward compat
        foreach ($user_ids as $id) {
            foreach (get_blogs_of_user($id) as $blog) {
                if (is_multisite()) {
                    switch_to_blog($blog->userblog_id);
                }

                ppc_delete_agent_permissions($id, 'user');
                
                if (is_multisite()) {
                    restore_current_blog();
                }
            }
        }
    }

    public function actDeletedGroup($group_id, $agent_type)
    {
        if ('pp_group' == $agent_type) {
            ppc_delete_agent_permissions($group_id, $agent_type);
        }
    }

    public function actScheduleUserSync($user_id, $role_name = '', $blog_id = '')
    {
        if (!$this->users_to_sync) {
            add_action('shutdown', [$this, 'syncUsers']);
        }

        $this->users_to_sync[] = $user_id;
    }

    public function syncUsers()
    {
        if ($this->users_to_sync) {
            $this->actSyncWordPressRoles($this->users_to_sync);
        }
    }

    public function actSyncWordPressRoles($user_ids = '', $role_name = '', $blog_id_arg = '')
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
        PluginUpdated::syncWordPressRoles($user_ids, $role_name, $blog_id_arg);
    }

    public function actScheduleRoleSync()
    {
        // Capability Manager doesn't actually create the role until after the option update we're hooking on, so defer our maintenance operation
        if (!has_action('shutdown', [$this, 'actSyncAllRoles'])) {
            add_action('shutdown', [$this, 'actSyncAllRoles']);
        }
    }

    // simplifies attaching this function to hook which pass irrelevant argument
    public function actSyncAllRoles()
    {
        $this->actSyncWordPressRoles();
    }

    // modifies WP core _updatePostTermCount to include private posts in the count, since PP roles can grant access to them
    public function updatePostTermCount($terms)
    {
        global $wpdb;

        foreach ((array)$terms as $term) {
            $stati_csv = "'" . implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'or')) . "'";
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts"
                    . " WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id"
                    . " AND post_status IN ($stati_csv) AND term_taxonomy_id = %d",
                    $term
                )
            );

            $wpdb->update($wpdb->term_taxonomy, compact('count'), ['term_taxonomy_id' => $term]);
        }
    }
}
