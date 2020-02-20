<?php
namespace PublishPress\Permissions\Collab;

class PageHierarchyFilters
{
    public static function fltDropdownPages($orig_options_html)
    {
        if (!strpos($orig_options_html, 'parent_id') || !$orig_options_html || presspermit()->isUserUnfiltered())
            return $orig_options_html;

        $post_type = PWP::findPostType();

        // User can't associate or de-associate a page with Main page unless they have edit_pages site-wide.
        // Prepend the Main Page option if appropriate (or, to avoid submission errors, if we generated no other options)
        if (!Collab::userCanAssociateMain($post_type)) {
            global $post;
            $is_new = ($post->post_status == 'auto-draft');

            if (!$is_new) {
                global $post;
                $object_id = (!empty($post->ID)) ? $post->ID : PWP::getPostID();

                $stored_parent_id = (!empty($post->ID)) ? $post->post_parent : get_post_field('post_parent', $object_id);
            }

            if ($is_new || $stored_parent_id) {
                $mat = [];
                preg_match('/<option[^v]* value="">[^<]*<\/option>/', $orig_options_html, $mat);

                if (!empty($mat[0]))
                    return str_replace($mat[0], '', $orig_options_html);
            }
        }

        return $orig_options_html;
    }
}
