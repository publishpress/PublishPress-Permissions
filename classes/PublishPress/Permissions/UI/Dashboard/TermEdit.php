<?php

namespace PublishPress\Permissions\UI\Dashboard;

//use \PressShack\LibWP as PWP;
//use \PressShack\LibArray as Arr;

require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemEdit.php');

class TermEdit
{
    public function __construct()
    {
        if (!did_action('presspermit_term_edit_ui')) {
	        wp_enqueue_script('post');
	        wp_enqueue_script('postbox');
	
	        wp_enqueue_style('presspermit-item-edit', PRESSPERMIT_URLPATH . '/common/css/item-edit.css', [], PRESSPERMIT_VERSION);
	        wp_enqueue_style('presspermit-term-edit', PRESSPERMIT_URLPATH . '/common/css/term-edit.css', [], PRESSPERMIT_VERSION);
	
	        add_action('admin_print_scripts', ['\PublishPress\Permissions\UI\Dashboard\ItemEdit', 'scriptItemEdit']);
            add_action('admin_print_scripts', [$this, 'compatStyles']);

	        add_action('admin_menu', [$this, 'actAddMetaBoxes']);
	
	        if (!empty($_REQUEST['taxonomy'])) {
	            $taxonomy = sanitize_key($_REQUEST['taxonomy']);
	        	
	            if (presspermit()->isTaxonomyEnabled($taxonomy)) {
	                add_action('admin_head', [$this, 'actScriptsWP']);
	
	                add_action("{$taxonomy}_edit_form", [$this, 'actExceptionEditUI']);
	                add_action('edit_tag_form', [$this, 'actExceptionEditUI']);
	            } else {
	                add_action("{$taxonomy}_edit_form", [$this, 'actTaxonomyEnableUI']);
	                add_action('edit_tag_form', [$this, 'actTaxonomyEnableUI']);
	            }
	
	            if (!empty($_REQUEST['pp_universal'])) {
	                add_action("{$taxonomy}_edit_form", [$this, 'actUniversalExceptionsUIsupport']);
	                add_action('edit_tag_form', [$this, 'actUniversalExceptionsUIsupport']);
	            }
	        }
	
	        do_action('presspermit_term_edit_ui');
	    }
    }

    public function initItemExceptionsUI()
    {
        if (empty($this->item_exceptions_ui)) {
            include_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsUI.php');
            $this->item_exceptions_ui = new ItemExceptionsUI();
        }
    }

    // wrapper function so we don't have to load item_roles_ui class just to register the metabox
    public function drawExceptionsUI($term, $box)
    {
        static $done;

		if (empty($done)) {
			$done = [];
		}
	
        if (empty($box['id']) || !empty($done[$box['id']])) {
            return;
        }

        $done[$box['id']] = true;

        $this->initItemExceptionsUI();

        $for_item_type = (in_array($box['args']['op'], ['manage', 'associate'], true))
            ? $term->taxonomy
            : $box['args']['for_item_type'];

        $args = [
            'via_item_source' => 'term',
            'for_item_source' => 'post',
            'for_item_type' => $for_item_type,
            'via_item_type' => $term->taxonomy,
            'item_id' => $term->term_taxonomy_id
        ];

        $this->item_exceptions_ui->drawExceptionsUI($box, $args);
    }

    public function actAddMetaBoxes()
    {
        // ========= register WP-rendered metaboxes ============
        global $typenow;

        static $done;

        if (!empty($done)) {
            return;
        }

        $pp = presspermit();

        $taxonomy = (isset($_REQUEST['taxonomy'])) ? sanitize_key($_REQUEST['taxonomy']) : '';

        if (!in_array($taxonomy, $pp->getEnabledTaxonomies(), true)) {
            return;
        }

        $tt_id = (!empty($_REQUEST['tag_ID'])) ? PWP::termidToTtid((int)$_REQUEST['tag_ID'], $taxonomy) : 0;

        $post_type = (!empty($_REQUEST['pp_universal'])) ? '' : $typenow;

        $hidden_types = apply_filters('presspermit_hidden_post_types', []);
        $hidden_taxonomies = apply_filters('presspermit_hidden_taxonomies', []);

        if (!empty($hidden_taxonomies[$taxonomy]) || ($post_type && !empty($hidden_types[$post_type]))) {
            return;
        }

        if (
            !current_user_can('pp_assign_roles')
            || apply_filters('presspermit_disable_exception_ui', false, 'term', $tt_id, $post_type)
        ) {
            return;
        }

        $done = true;

        $tx = get_taxonomy($taxonomy);
        $type_obj = get_post_type_object($post_type);
        $register_type = ($post_type) ? $post_type : 'post';

        $ops = $pp->admin()->canSetExceptions(
            'read',
            $post_type,
            ['via_item_source' => 'term', 'via_item_type' => $taxonomy, 'for_item_source' => 'post']
        )
            ? ['read' => true] : [];

        $operations = apply_filters('presspermit_item_edit_exception_ops', $ops, 'post', $taxonomy, $post_type);

        $boxes = [];

        foreach (array_keys($operations) as $op) {
            if ($op_obj = $pp->admin()->getOperationObject($op, $post_type)) {
                if ('assign' == $op) {
                    $title = ($post_type)
                        ? sprintf(
                            __('Permissions: Assign this %2$s to %3$s', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name,
                            $type_obj->labels->name
                        )
                        : sprintf(
                            __('Permissions: Assign this %2$s', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name
                        );
                } elseif (in_array($op, ['read', 'edit'], true)) {
                    $title = ($post_type)
                        ? sprintf(
                            __('Permissions: %1$s %2$s in this %3$s', 'press-permit-core'),
                            $op_obj->label,
                            $type_obj->labels->name,
                            $tx->labels->singular_name
                        )
                        : sprintf(
                            __('Permissions: %1$s all content in this %2$s', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name
                        );
                } else {
                    $title = ($post_type)
                        ? sprintf(
                            __('Permissions: %1$s %2$s in this %3$s', 'press-permit-core'),
                            $op_obj->label,
                            $type_obj->labels->name,
                            $tx->labels->singular_name
                        )
                        : sprintf(
                            __('Permissions: %1$s this %2$s', 'press-permit-core'),
                            $op_obj->label,
                            $tx->labels->singular_name
                        );
                }

                Arr::setElem($boxes, [$op, "pp_{$op}_{$post_type}_exceptions"]);
                $boxes[$op]["pp_{$op}_{$post_type}_exceptions"]['for_item_type'] = $post_type;
                $boxes[$op]["pp_{$op}_{$post_type}_exceptions"]['title'] = $title;
            }
        }

        $boxes = apply_filters('presspermit_term_exceptions_metaboxes', $boxes, $taxonomy, $post_type);

        foreach ($boxes as $op => $boxes) {
            foreach ($boxes as $box_id => $_box) {
                add_meta_box(
                    $box_id,
                    $_box['title'],
                    [$this, 'drawExceptionsUI'],
                    $register_type,
                    'advanced',
                    'default',
                    ['for_item_type' => $_box['for_item_type'], 'op' => $op]
                );
            }
        }
    }

    private function prepMetaboxes()
    {
        global $tag_ID;
        if (empty($tag_ID)) {
            return;
        }

        static $been_here;
        if (isset($been_here)) {
            return;
        }

        $been_here = true;

        global $typenow;

        $post_type = (!empty($_REQUEST['pp_universal'])) ? '' : $typenow;
        $taxonomy = (isset($_REQUEST['taxonomy'])) ? sanitize_key($_REQUEST['taxonomy']) : '';

        if (current_user_can('pp_assign_roles')) {
            $this->initItemExceptionsUI();

            $tt_id = PWP::termidToTtid($tag_ID, $taxonomy);

            $args = ['for_item_type' => $post_type, 'hierarchical' => is_taxonomy_hierarchical($post_type)];  // via_src, for_src, via_type, item_id, args
            $this->item_exceptions_ui->data->loadExceptions('term', 'post', $taxonomy, $tt_id, $args);

            do_action('presspermit_prep_metaboxes', 'term', $taxonomy, $tt_id);
        }
    }

    public function actExceptionEditUI($tag)
    {
        global $taxonomy, $typenow;  // only deal with post type which edit form was linked from?

        static $been_here;
        if (isset($been_here)) {
            return;
        }

        $been_here = true;

        if ($typenow && !in_array($typenow, presspermit()->getEnabledPostTypes(), true)) {
            return;
        }

        $post_type = (!empty($_REQUEST['pp_universal'])) ? '' : $typenow;

        if (
            !current_user_can('pp_assign_roles')
            || apply_filters('presspermit_disable_exception_ui', false, 'term', $tag->term_taxonomy_id, $post_type)
        ) {
            return;
        }
        ?>
        <div class="edit-tag-actions">
            <input class="button button-primary" value="<?php _e('Update', 'press-permit-core'); ?>" type="submit">
        </div>
        <?php

        if ($post_type) {
            self::universalExceptionsNote($tag, $taxonomy, $post_type);
        }
        ?>
        <div id="poststuff" class="metabox-holder">
            <div id="post-body">
                <div id="post-body-content">
                    <?php

                    require_once(ABSPATH . 'wp-admin/includes/meta-boxes.php');

                    $this->prepMetaboxes();

                    $type = ($post_type) ? $post_type : 'post';
                    do_meta_boxes($type, 'advanced', $tag);

                    ?>
                </div> <!-- post-body-content -->
            </div> <!-- post-body -->
        </div> <!-- poststuff -->
        <?php

        if ($post_type) {
            self::universalExceptionsNote($tag, $taxonomy, $post_type);
        }
    }

    public function actTaxonomyEnableUI($tag)
    {
        global $taxonomy, $typenow;

        if ($typenow && !in_array($typenow, presspermit()->getEnabledPostTypes(), true)) {
            return;
        }

        ?>
        <br/><br/>
        <div id="poststuff" class="metabox-holder">
            <div id="post-body">
                <div id="post-body-content">
                    <?php

                    require_once(ABSPATH . 'wp-admin/includes/meta-boxes.php');

                    add_meta_box(
                        "pp_enable_taxonomy",
                        __('Permissions Settings', 'press-permit-core'),
                        [$this, 'drawSettingsUI'],
                        $taxonomy,
                        'advanced',
                        'default',
                        []
                    );

                    do_meta_boxes($taxonomy, 'advanced', $tag);

                    ?>
                </div> <!-- post-body-content -->
            </div> <!-- post-body -->
        </div> <!-- poststuff -->
        <?php

        echo '<div style="clear:both">&nbsp;</div>';
    }

    public function drawSettingsUI($term, $box)
    {
        if ($tx = get_taxonomy($term->taxonomy)) :
            ?>
            <label for="pp_enable_taxonomy"><input type="checkbox" name="pp_enable_taxonomy"/>
                <?php printf(__('enable custom permissions for %s', 'press-permit-core'), $tx->labels->name); ?>
            </label>
        <?php
        endif;
    }

    public function actUniversalExceptionsUIsupport()
    {
        ?>
        <input type="hidden" name="pp_universal" value="1"/>
        <?php
    }

    private function universalExceptionsNote($tag, $taxonomy, $post_type)
    {
        $tx_obj = get_taxonomy($taxonomy);
        $type_obj = get_post_type_object($post_type);
        ?>
        <div class="form-wrap">
            <p>
                <?php
                // if _wp_original_http_referer is not passed, redirect will be from universal exceptions edit form to type-specific exceptions edit form
                if (!$referer = wp_get_original_referer()) {
                    $referer = wp_get_referer();
                }

                $url = esc_url_raw(
                    add_query_arg(
                        '_wp_original_http_referer',
                        urlencode($referer),
                        "term.php?taxonomy=$taxonomy&amp;tag_ID={$tag->term_id}&amp;pp_universal=1"
                    )
                );

                printf(
                    __('Displayed permissions are those assigned for the "%1$s" type. You can also %2$sdefine universal %3$s permissions which apply to all related post types%4$s.', 'press-permit-core'),
                    $type_obj->labels->singular_name,
                    "<a href='$url'>",
                    $tx_obj->labels->singular_name,
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function compatStyles() {
        // Hide invalid Simple WP Membership Protection metabox which is triggered due to existence of add_meta_box() function.
        if (defined('SIMPLE_WP_MEMBERSHIP_VER') && !defined('PRESSPERMIT_ALLOW_SIMPLE_MEMBERSHIP_METABOX')):
        ?>
        <style type="text/css">
            #swpm_sectionid {
                display: none;
            }
        </style>
        <?php 
        endif;
    }

    public function actScriptsWP()
    {
        wp_enqueue_script('common');
        wp_enqueue_script('postbox');
        add_thickbox();
        wp_enqueue_script('media-upload');

        ItemEdit::scriptItemEdit($_REQUEST['taxonomy']);
    }
}
