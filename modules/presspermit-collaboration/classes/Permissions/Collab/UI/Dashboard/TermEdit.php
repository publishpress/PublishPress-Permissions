<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

//use \PressShack\LibArray as Arr;

class TermEdit
{
    function __construct()
    {
        add_filter('presspermit_item_edit_exception_ops', [$this, 'flt_item_edit_exception_ops'], 10, 4);

        add_filter('presspermit_term_exceptions_metaboxes', [$this, 'term_exceptions_metaboxes'], 10, 3);
        add_action('presspermit_prep_metaboxes', [$this, 'pp_prep_metaboxes'], 10, 3);
        //add_action( 'pp_update_item_exceptions', [$this, 'update_item_exceptions'], 10, 3 );
    }

    function flt_item_edit_exception_ops($operations, $for_item_source, $taxonomy, $for_item_type)
    {
        $pp = presspermit();

        foreach (['edit', 'fork', 'revise', 'assign'] as $op) {
            if ($pp->admin()->canSetExceptions(
                $op, 
                $for_item_type, 
                ['via_item_source' => 'term', 'via_type_name' => $taxonomy, 'for_item_source' => $for_item_source]
            )) {
                $operations[$op] = true;
            }
        }

        return $operations;
    }

    function update_item_exceptions($via_item_source, $item_id, $args)
    {
        if ('term' == $via_item_source) {
            ItemSave::itemUpdateProcessExceptions('term', 'term', $item_id, $args);
        }
    }

    function pp_prep_metaboxes($via_item_source, $via_item_type, $tt_id)
    {
        if ('term' == $via_item_source) {
            global $typenow;

            if (!$typenow) {
                $args = ['for_item_type' => $via_item_type];  // via_item_source, for_item_source, via_item_type, item_id
                do_action('presspermit_load_item_exceptions', 'term', 'term', $via_item_type, $tt_id, $args);
            }
        }
    }

    function term_exceptions_metaboxes($boxes, $taxonomy, $typenow)
    {
        global $typenow;
        if (!$typenow) {  // term management / association exceptions UI only displed when editing "Universal Exceptions" (empty post type)
            $tx = get_taxonomy($taxonomy);
            $add_boxes = [];

            foreach (['manage', 'associate'] as $op) {
                if ($op_obj = presspermit()->admin()->getOperationObject($op, $typenow)) {

                    $caption = ('associate' == $op) 
                    ? sprintf(
                        __('Permissions: Select this %1$s as Parent', 'press-permit-core'), 
                        $tx->labels->singular_name
                    )
                    : sprintf(
                        __('Permissions: %1$s this %2$s', 'press-permit-core'), 
                        $op_obj->label, 
                        $tx->labels->singular_name
                    );

                    Arr::setElem($add_boxes, [$op, "pp_{$op}_{$taxonomy}_exceptions"]);
                    $add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['for_item_type'] = $taxonomy;
                    $add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['for_item_source'] = 'term'; // $taxonomy;
                    $add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['title'] = $caption;
                }
            }

            $boxes = array_merge($add_boxes, $boxes);  // put Category Management Exceptions box at the top
        }

        return $boxes;
    }
}
