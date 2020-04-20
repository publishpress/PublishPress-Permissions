<?php
namespace PublishPress\Permissions\Collab\UI;

class AjaxUI
{
    public static function fltOperationCaptions($op_captions)
    {
        $op_captions['edit'] = (object)['label' => __('Edit'), 'noun_label' => __('Editing', 'press-permit-core')];

        if (presspermit()->getOption('publish_exceptions'))
            $op_captions['publish'] = (object)['label' => __('Publish'), 'noun_label' => __('Publishing', 'press-permit-core')];

        if (defined('REVISIONARY_VERSION'))
            $op_captions['revise'] = (object)['label' => __('Revise'), 'noun_label' => __('Revision', 'press-permit-core')];

        if (class_exists('Fork', false) && !defined('PP_DISABLE_FORKING_SUPPORT'))
            $op_captions['fork'] = (object)['label' => __('Fork'), 'noun_label' => __('Fork', 'press-permit-core')];

        $op_captions = array_merge($op_captions, [
            'associate' => (object)[
                'label' => __('Set as Parent', 'press-permit-core'), 
                'noun_label' => __('Set as Parent', 'press-permit-core'), 
                'agent_label' => __('Set as Parent', 'press-permit-core')
            ],
            
            'assign' => (object)[
                'label' => __('Assign Term', 'press-permit-core'), 
                'noun_label' => __('Assignment', 'press-permit-core')
            ],

            /*'publish' => (object)[
                'label' => __('Publish'), 
                'noun_label' => __('Publishing', 'press-permit-core')
            ],*/

            'manage' => (object)[
                'label' => __('Manage'), 
                'noun_label' => __('Management', 'press-permit-core')
            ],
        ]);

        return $op_captions;
    }

    public static function fltExceptionOperations($ops, $for_item_source, $for_item_type)
    {
        $pp = presspermit();

        if ('post' == $for_item_source) {
            $op_obj = $pp->admin()->getOperationObject('edit', $for_item_type);
            $ops['edit'] = $op_obj->label; //, 'delete' => __('Delete') );

            if (presspermit()->getOption('publish_exceptions')) {
                $op_obj = $pp->admin()->getOperationObject('publish', $for_item_type);
                $ops['publish'] = $op_obj->label; //, 'delete' => __('Delete') );
            }

            if (class_exists('Fork', false) && !defined('PP_DISABLE_FORKING_SUPPORT') && !in_array($for_item_type, ['forum'], true)) {
                $op_obj = $pp->admin()->getOperationObject('fork', $for_item_type);
                $ops['fork'] = $op_obj->label;
            }

            if (defined('REVISIONARY_VERSION') && !in_array($for_item_type, ['forum'], true)) {
                $op_obj = $pp->admin()->getOperationObject('revise', $for_item_type);
                $ops['revise'] = $op_obj->label;
            }

            if ($for_item_type && is_post_type_hierarchical($for_item_type)) {
                $op_obj = $pp->admin()->getOperationObject('associate', $for_item_type);
                $ops['associate'] = $op_obj->agent_label;
            }

            $type_arg = ($for_item_type) ? ['object_type' => $for_item_type] : [];
            if ($pp->getEnabledTaxonomies($type_arg)) {
                $op_obj = $pp->admin()->getOperationObject('assign', $for_item_type);
                $ops['assign'] = $op_obj->label;
            }

        } elseif ('_term_' == $for_item_source) {
            $op_obj = $pp->admin()->getOperationObject('manage');
            $ops['manage'] = $op_obj->label;

            //if ( is_taxonomy_hierarchical( $for_item_type ) )
            $op_obj = $pp->admin()->getOperationObject('associate');
            $ops['associate'] = $op_obj->agent_label;

        } elseif (in_array($for_item_source, ['pp_group', 'pp_net_group'], true)) {
            $op_obj = $pp->admin()->getOperationObject('manage', $for_item_type);
            $ops['manage'] = $op_obj->label;
        }

        return $ops;
    }

    public static function fltExceptionsStatusUi($html, $for_type, $args = [])
    {
        $defaults = ['operation' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!in_array($operation, ['read', 'publish_topics', 'publish_replies'], true) && ('attachment' != $for_type)) {
            $html .= '<p class="pp-checkbox" style="white-space:nowrap">'
                . '<input type="checkbox" id="pp_select_cond_post_status_unpub" name="pp_select_x_cond[]" value="post_status:{unpublished}"> '
                . '<label for="pp_select_cond_post_status_unpub">' . __('(unpublished)', 'press-permit-core') . '</label>'
                . '</p>';
        }

        return $html;
    }

    public static function fltExceptionViaTypes($types, $for_item_source, $for_type, $operation, $mod_type)
    {
        if ('_term_' == $for_item_source) {
            foreach (presspermit()->getEnabledTaxonomies(['object_type' => false], 'object') as $taxonomy => $tx_obj)
                $types[$taxonomy] = $tx_obj->labels->name;

            if ('manage' != $operation)
                unset($types['nav_menu']);
        }

        return $types;
    }
}
