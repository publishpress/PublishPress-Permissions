<?php
namespace PublishPress\Permissions\Collab;

class REST_Workarounds
{
    function __construct() {
        add_action('presspermit_user_init', [$this, 'actHandleRestTermAssignment'], 50);
    }

    function actHandleRestTermAssignment()
    {
        if (empty($_POST) || (false === strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2')))
            return;

        $request_uri = $_SERVER['REQUEST_URI'];

        $pp = presspermit();

        foreach ($pp->getEnabledPostTypes([], 'object') as $type_obj) {
            $type_rest_base = (!empty($type_obj->rest_base)) ? $type_obj->rest_base : $type_obj->name;

            if (false === strpos($request_uri, "/wp-json/wp/v2/$type_rest_base/"))
                continue;

            $matches = [];
            preg_match("/\/wp-json\/wp\/v2\/$type_rest_base\/([0-9]+)\//", $request_uri, $matches);
            if (empty($matches[1]))
                continue;

            $post_id = $matches[1];

            $enabled_taxonomies = $pp->getEnabledTaxonomies([], 'object');

            foreach ($enabled_taxonomies as $tx_obj) {
                $rest_base = (!empty($tx_obj->rest_base)) ? $tx_obj->rest_base : $tx_obj->name;

                if (!empty($_REQUEST[$rest_base])) {
                    $taxonomy = $tx_obj->name;

                    $user_terms = get_terms(
                        $taxonomy, 
                        ['required_operation' => 'assign', 'hide_empty' => 0, 'fields' => 'ids', 'post_type' => $type_obj->name]
                    );
                    
                    $selected_terms = array_intersect($_REQUEST[$rest_base], $user_terms);

                    $stored_terms = Collab::getObjectTerms($post_id, $taxonomy, ['fields' => 'ids']);

                    if (!defined('PPCE_DISABLE_' . strtoupper($taxonomy) . '_RETENTION')) {
                        if ($deselected_terms = array_diff($stored_terms, $selected_terms)) {
                            if ($unremovable_terms = array_diff($deselected_terms, $user_terms)) {
                                $selected_terms = array_map('strval', array_merge($selected_terms, $unremovable_terms));
                            }
                        }
                    }

                    $_REQUEST[$rest_base] = $selected_terms;
                    $_POST[$rest_base] = $selected_terms;
                }
            }
        } // end foreach type
    }
}
