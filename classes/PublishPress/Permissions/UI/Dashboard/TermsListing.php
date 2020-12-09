<?php

namespace PublishPress\Permissions\UI\Dashboard;

class TermsListing
{
    private $exceptions = [];

    public function __construct()
    {
        if (presspermit()->filteringEnabled()) {
            add_action('admin_print_footer_scripts', [$this, 'actScriptHideMainOption']);
            add_action('admin_print_footer_scripts', [$this, 'actScriptResize']);
            add_action('admin_print_footer_scripts', [$this, 'actScriptUniversalExceptions']);
        }

        if (empty($_REQUEST['tag_ID'])) {
            $taxonomy = sanitize_key($_REQUEST['taxonomy']);
            add_filter("manage_edit-{$taxonomy}_columns", [$this, 'fltDefineColumns']);
            add_filter("manage_{$taxonomy}_columns", [$this, 'fltDefineColumns']);
            add_filter("manage_{$taxonomy}_custom_column", [$this, 'fltCustomColumn'], 10, 3);

            add_action('after-' . $taxonomy . '-table', [$this, 'actShowNotes']);

            if (is_taxonomy_hierarchical($taxonomy)) {
                $tx_children = get_option("{$taxonomy}_children");

                if (!$tx_children || !is_array($tx_children) || !empty($_REQUEST['clear_db_cache']) || !get_option("_ppperm_refresh_{$taxonomy}_children")) {
                    delete_option("{$taxonomy}_children");
                    update_option("_ppperm_refresh_{$taxonomy}_children", true);
                }
            }
        }
    }

    public function actShowNotes()
    {
        global $typenow;

        if (empty($_REQUEST['pp_universal'])) {
            $taxonomy = sanitize_key($_REQUEST['taxonomy']);
            $tx_obj = get_taxonomy($taxonomy);
            $type_obj = get_post_type_object($typenow);
            $url = "edit-tags.php?taxonomy=$taxonomy&pp_universal=1";
            ?>
            <div class="form-wrap">
                <p>
                    <?php
                    printf(
                        __('Listed permissions are those assigned for the "%1$s" type. You can also %2$sdefine universal %3$s permissions which apply to all related post types%4$s.', 'press-permit-core'),
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
    }

    public function fltDefineColumns($columns)
    {
        global $typenow;

        if (empty($_REQUEST['pp_universal'])) {
            $taxonomy = sanitize_key($_REQUEST['taxonomy']);
            $type_obj = get_post_type_object($typenow);
            $title = __('Click to list/edit universal permissions', 'press-permit-core');
            $lbl = ($type_obj && $type_obj->labels) ? $type_obj->labels->singular_name : '';
            $caption = sprintf(__('%1$s Permissions %2$s*%3$s', 'press-permit-core'), $lbl, "<a href='edit-tags.php?taxonomy=$taxonomy&pp_universal=1' title='$title'>", '</a>');
        } else {
            $caption = __('Universal Permissions', 'press-permit-core');
        }

        if (defined('PRESSPERMIT_DEBUG')) {
            $columns = array_merge($columns, ['pp_ttid' => 'ID (ttid)']);
        }

        return array_merge($columns, ['pp_exceptions' => $caption]);
    }

    public function fltCustomColumn($val, $column_name, $id)
    {
        if ('pp_ttid' == $column_name) {
            global $taxonomy;
            $ttid = PWP::termidToTtid($id, $taxonomy);
            echo "$id ($ttid)";
        }

        if ('pp_exceptions' != $column_name) {
            return;
        }

        static $got_data;
        if (empty($got_data)) {
            $this->logTermData();
            $got_data = true;
        }

        global $taxonomy;
        $id = PWP::termidToTtid($id, $taxonomy);

        if (!empty($this->exceptions[$id])) {
            global $typenow;

            $pp_admin = presspermit()->admin();

            $op_names = [];

            foreach ($this->exceptions[$id] as $op) {
                if ($op_obj = $pp_admin->getOperationObject($op, $typenow))
                    $op_names[] = $op_obj->label;
            }

            uasort($op_names, 'strnatcasecmp');
            echo implode(", ", $op_names);
        }
    }

    public function actScriptResize()
    {
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                $('#col-left').css('width', '25%');
                $('#col-right').css('width', '75%');
                $('.column-slug').css('width', '15%');
                $('.column-posts').css('width', '10%');
            });
            /* ]]> */
        </script>
        <?php
    }

    public function actScriptUniversalExceptions()
    {
        global $post_type;

        if (empty($_REQUEST['pp_universal'])) {
            return;
        }
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            function updateQueryStringParameterPP(uri, key, value) {
                <?php /* https://stackoverflow.com/a/6021027 */ ?>
                var re = new RegExp("([?|&])" + key + "=.*?(&|$)", "i");
                separator = uri.indexOf('?') !== -1 ? "&" : "?";
                if (uri.match(re)) {
                    return uri.replace(re, '$1' + key + "=" + value + '$2');
                } else {
                    return uri + separator + key + "=" + value;
                }
            }

            jQuery(document).ready(function ($) {
                $('#the-list tr').each(function (i, e) {
                    $(e).find("a.row-title,span.edit a").each(function (ii, ee) {
                        var u = $(ee).attr('href').replace('&post_type=<?php echo $post_type; ?>', '');
                        $(ee).attr('href', u + '&pp_universal=1');
                    });
                });
            });
            /* ]]> */
        </script>
        <?php
    }

    // In "Add New Term" form, hide the "Main" option from Parent dropdown if the logged user doesn't have manage_terms cap site-wide
    public function actScriptHideMainOption()
    {
        if (!empty($_REQUEST['action']) && ('edit' == $_REQUEST['action'])) {
            return;
        }

        if (!empty($_REQUEST['taxonomy'])) {  // using this with edit-link-categories
            if ($tx_obj = get_taxonomy($_REQUEST['taxonomy'])) {
                $cap_name = $tx_obj->cap->manage_terms;
            }
        }

        if (empty($cap_name)) {
            $cap_name = 'manage_categories';
        }

        if (!empty(presspermit()->getUser()->allcaps[$cap_name])
        ) {
            if (!presspermit()->getUser()->getExceptionTerms('manage', 'include', $_REQUEST['taxonomy'], $_REQUEST['taxonomy'], ['merge_universals' => true])) {
            	return;
            }
        }
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                $('#parent option[value="-1"]').remove();
            });
            /* ]]> */
        </script>
        <?php
    }

    private function logTermData()
    {
        global $wp_object_cache, $wpdb, $typenow;

        $taxonomy = sanitize_key($_REQUEST['taxonomy']);

        if (!empty($wp_object_cache) && (isset($wp_object_cache->cache[$taxonomy]) || isset($wp_object_cache->cache['terms']))) {
            $cache = (isset($wp_object_cache->cache[$taxonomy])) ? $wp_object_cache->cache[$taxonomy] : $wp_object_cache->cache['terms'];
        }

        if (!empty($cache)) {
            if (isset($cache)) { // Note: array is keyed "blog_id:term_id" on Multisite installs
                $listed_term_ids = [];
                foreach ($cache as $k => $term) {
                    if (!is_object($term)) {
                        continue;
                    }

                    if (!is_numeric($k)) {
                        $arr = explode(':', $k);
                        if (!$arr || (count($arr) != 2) || !is_numeric(array_pop($arr))) {
                            continue;
                        }
                    }

                    $listed_tt_ids[] = $term->term_taxonomy_id;
                }
            }

            if (empty($_REQUEST['paged'])) {
                $listed_tt_ids[] = 0;
            }
        } else {
            return;
        }

        if (!empty($_REQUEST['pp_universal'])) {
            $typenow = '';
        } elseif (empty($typenow)) {
            $typenow = (isset($_REQUEST['post_type'])) ? sanitize_key($_REQUEST['post_type']) : '';
        }

        $this->exceptions = [];

        if (!empty($listed_tt_ids)) {
            $agent_type_csv = implode("','", array_merge(['user'], presspermit()->groups()->getGroupTypes()));
            $id_csv = implode("','", $listed_tt_ids);
            $post_type = (!empty($_REQUEST['pp_universal'])) ? '' : $typenow;

            $for_type_csv = ($typenow) ? "'$post_type'" : "'', '$taxonomy'";

            $results = $wpdb->get_results("SELECT DISTINCT i.item_id, e.operation FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id WHERE e.for_item_type IN ($for_type_csv) AND e.via_item_source = 'term' AND e.via_item_type = '$taxonomy' AND e.agent_type IN ('$agent_type_csv') AND i.item_id IN ('$id_csv')");

            foreach ($results as $row) {
                $this->exceptions[$row->item_id][] = $row->operation;
            }
        }
    }
}