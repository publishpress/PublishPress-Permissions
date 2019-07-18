<?php

namespace PublishPress\Permissions;

class RoleDefaults
{
    public static function getDefaultRolecaps($role, $return_default = true)
    {
        $type_obj = get_post_type_object('post');
        $cap = $type_obj->cap;

        switch ($role) {
            case 'subscriber':
                return array_fill_keys(['read'], true);
                break;
            case 'contributor':
                return array_fill_keys(['read', $cap->edit_posts, $cap->delete_posts], true);
                break;
            case 'author':
                return array_fill_keys([
                    'read', $cap->edit_posts, $cap->edit_published_posts, $cap->publish_posts, $cap->delete_posts,
                    $cap->delete_published_posts, 'upload_files'
                ], true);

                break;
            //case 'reviewer':
            // return array_fill_keys( ['read', $cap->edit_posts, $cap->edit_others_posts, $cap->delete_posts, $cap->delete_others_posts, 'upload_files', 'unfiltered_html'], true );
            // break;
            case 'editor':
                return array_fill_keys([
                    'read', $cap->read_private_posts, $cap->edit_posts, $cap->edit_published_posts, $cap->edit_private_posts,
                    $cap->edit_others_posts, $cap->publish_posts, $cap->delete_posts, $cap->delete_others_posts,
                    $cap->delete_published_posts, $cap->delete_private_posts, 'upload_files', 'unfiltered_html',
                    'moderate_comments', 'manage_categories'
                ], true);

                break;
            default:
                if ($def_caps = apply_filters('presspermit_default_rolecaps', [], $role, $cap))
                    return $def_caps;
                else
                    return ($return_default) ? ['read' => true] : [];
        }
    }
}
