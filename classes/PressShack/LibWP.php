<?php
namespace PressShack;

class LibWP
{
    /**
     * Returns true if is a beta or stable version of WP 5.
     *
     * @return bool
     */
    public static function isWp5()
    {
        global $wp_version;
        return version_compare($wp_version, '5.0', '>=') || substr($wp_version, 0, 2) === '5.';
    }

    /**
     * Based on Edit Flow's \Block_Editor_Compatible::should_apply_compat method.
     *
     * @return bool
     */
    public static function isBlockEditorActive($post_type = '', $args = [])
    {
        global $current_user;

        $defaults = ['suppress_filter' => false, 'force_refresh' => false];
        $args = array_merge($defaults, $args);
        $suppress_filter = $args['suppress_filter'];

        // Check if Revisionary lower than v1.3 is installed. It disables Gutenberg.
        if (defined('REVISIONARY_VERSION') && version_compare(REVISIONARY_VERSION, '1.3-beta', '<')) {
            return false;
        }

        static $buffer;
        if (!isset($buffer)) {
            $buffer = [];
        }

        if (!$post_type) {
            if (!$post_type = self::findPostType()) {
                $post_type = 'page';
            }
        }

        if ($post_type_obj = get_post_type_object($post_type)) {
            if (!$post_type_obj->show_in_rest) {
                return false;
            }
        }

        if (isset($buffer[$post_type]) && empty($args['force_refresh']) && !$suppress_filter) {
            return $buffer[$post_type];
        }

        if (class_exists('Classic_Editor')) {
			if (isset($_REQUEST['classic-editor__forget']) && (isset($_REQUEST['classic']) || isset($_REQUEST['classic-editor']))) {
				return false;
			} elseif (isset($_REQUEST['classic-editor__forget']) && !isset($_REQUEST['classic']) && !isset($_REQUEST['classic-editor'])) {
				return true;
			} elseif (get_option('classic-editor-allow-users') === 'allow') {
				if ($post_id = self::getPostID()) {
					$which = get_post_meta( $post_id, 'classic-editor-remember', true );

					if ('block-editor' == $which) {
						return true;
					} elseif ('classic-editor' == $which) {
						return false;
					}
				} else {
                    $use_block = ('block' == get_user_meta($current_user->ID, 'wp_classic-editor-settings'));
                    return $use_block && apply_filters('use_block_editor_for_post_type', $use_block, $post_type, PHP_INT_MAX);
				}
			}
		}

		$pluginsState = array(
			'classic-editor' => class_exists( 'Classic_Editor' ), // is_plugin_active('classic-editor/classic-editor.php'),
			'gutenberg'      => function_exists( 'the_gutenberg_project' ), //is_plugin_active('gutenberg/gutenberg.php'),
			'gutenberg-ramp' => class_exists('Gutenberg_Ramp'),
		);
		
		$conditions = [];

        if ($suppress_filter) remove_filter('use_block_editor_for_post_type', $suppress_filter, 10, 2);

		/**
		 * 5.0:
		 *
		 * Classic editor either disabled or enabled (either via an option or with GET argument).
		 * It's a hairy conditional :(
		 */
		// phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.NonceVerification.NoNonceVerification
        $conditions[] = (self::isWp5() || $pluginsState['gutenberg'])
						&& ! $pluginsState['classic-editor']
						&& ! $pluginsState['gutenberg-ramp']
						&& apply_filters('use_block_editor_for_post_type', true, $post_type, PHP_INT_MAX);

		$conditions[] = self::isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'block'
                            && ! isset($_GET['classic-editor__forget']));

        $conditions[] = self::isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'classic'
                            && isset($_GET['classic-editor__forget']));

        $conditions[] = $pluginsState['gutenberg-ramp'] 
                        && apply_filters('use_block_editor_for_post', true, get_post(self::getPostID()), PHP_INT_MAX);

		// Returns true if at least one condition is true.
		$result = count(
				   array_filter($conditions,
					   function ($c) {
						   return (bool)$c;
					   }
				   )
               ) > 0;
        
        if (!$suppress_filter) {
            $buffer[$post_type] = $result;
        }

        // Returns true if at least one condition is true.
        return $result;
    }

    public static function sanitizeWord($key)
    {
        return preg_replace('/[^A-Za-z0-9_\-\.:]/', '', $key);
    }

    public static function sanitizeCSV($key)
    {
        return preg_replace('/[^A-Za-z0-9_\-\.,}{:\|\(\)\s\t\r\n]/', '', $key);
    }

    public static function isAttachment()
    {
        global $wp_query;
        return !empty($wp_query->query_vars['attachment_id']) || !empty($wp_query->query_vars['attachment']);
    }

    public static function isFront()
    {
        $is_front = (!is_admin() && !defined('XMLRPC_REQUEST') && !defined('DOING_AJAX')
            && (!defined('REST_REQUEST') || !REST_REQUEST) && !self::doingCron());

        return (defined('REST_REQUEST') && REST_REQUEST) ? apply_filters('presspermit_is_front', $is_front) : $is_front;
    }

    public static function doingCron()
    {
        // WP Cron Control plugin disables core cron by setting DOING_CRON on every site access.  Use plugin's own scheme to detect actual cron requests.
        $doing_cron = defined('DOING_CRON')
            && (!class_exists('WP_Cron_Control') || !defined('ABSPATH')) && apply_filters('pp_doing_cron', true);

        return $doing_cron;
    }

    // support array matching for post type
    public static function getPostStatuses($args, $return = 'names', $operator = 'and', $params = [])
    {
        if (isset($args['post_type'])) {
            $post_type = $args['post_type'];
            unset($args['post_type']);
            $stati = get_post_stati($args, 'object', $operator);

            foreach ($stati as $status => $obj) {
                if (!empty($obj->post_type) && !array_intersect((array)$post_type, (array)$obj->post_type))
                    unset($stati[$status]);
            }

            $statuses = ('names' == $return) ? array_keys($stati) : $stati;
        } else {
            $statuses = get_post_stati($args, $return, $operator);
        }

        return apply_filters('presspermit_get_post_statuses', $statuses, $args, $return, $operator, $params);
    }

    public static function findPostType($post_id = 0, $return_default = true)
    {
        global $typenow, $post;

        if (!$post_id && defined('REST_REQUEST') && REST_REQUEST) {
            if ($_post_type = apply_filters('presspermit_rest_post_type', '')) {
                return $_post_type;
            }
        }

        if (!$post_id && !empty($typenow))
            return $typenow;

        if (is_object($post_id))
            $post_id = $post_id->ID;

        if ($post_id && !empty($post) && ($post->ID == $post_id)) {
            return $post->post_type;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) { // todo: separate static function to eliminate redundancy with PostFilters::fltPostsClauses()
            $ajax_post_types = apply_filters('pp_ajax_post_types', ['ai1ec_doing_ajax' => 'ai1ec_event']);
            foreach (array_keys($ajax_post_types) as $arg) {
                if (!empty($_REQUEST[$arg]) || (!empty($_REQUEST['action']) && ($arg == $_REQUEST['action'])))
                    return $ajax_post_types[$arg];
            }
        }

        if ($post_id) { // note: calling static function already compared post_id to global $post
            if ($_post = get_post($post_id)) {
                $_type = $_post->post_type;
            }

            if (!empty($_type))
                return $_type;
        }

        // no post id was passed in, or we couldn't retrieve it for some reason, so check $_REQUEST args
        global $pagenow, $wp_query;

        if (!empty($wp_query->queried_object)) {
            if (isset($wp_query->queried_object->post_type))
                $object_type = $wp_query->queried_object->post_type;
            elseif (isset($wp_query->queried_object->name)) {
                if (post_type_exists($wp_query->queried_object->name))  // bbPress forums list
                    $object_type = $wp_query->queried_object->name;
            }
        } elseif (in_array($pagenow, ['post-new.php', 'edit.php'])) {
            $object_type = !empty($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        } elseif (in_array($pagenow, ['edit-tags.php'])) {
            $object_type = !empty($_REQUEST['taxonomy']) ? sanitize_key($_REQUEST['taxonomy']) : 'category';
        } elseif (in_array($pagenow, ['admin-ajax.php']) && !empty($_REQUEST['taxonomy'])) {
            $object_type = sanitize_key($_REQUEST['taxonomy']);
        } elseif (!empty($_POST['post_ID'])) {
            if ($_post = get_post($_POST['post_ID']))
                $object_type = $_post->post_type;
        } elseif (!empty($_GET['post'])) {  // post.php
            if ($_post = get_post($_GET['post']))
                $object_type = $_post->post_type;
        }

        if (empty($object_type)) {
            if ($return_default) // default to post type
                return 'post';
        } elseif ('any' != $object_type) {
            return $object_type;
        }
    }

    public static function getPostID()
    {
        global $post, $wp_query;

        if (defined('REST_REQUEST') && REST_REQUEST) {
            if ($_post_id = apply_filters('presspermit_rest_post_id', 0)) {
                return $_post_id;
            }
        }

        if (!empty($post) && is_object($post)) {
            if ('auto-draft' == $post->post_status)
                return 0;
            else
                return $post->ID;
        } elseif (!is_admin() && !empty($wp_query) && is_singular()) {
            if (!empty($wp_query)) {
                if (!empty($wp_query->query_vars) && !empty($wp_query->query_vars['p'])) {
                    return $wp_query->query_vars['p'];
                } elseif (!empty($wp_query->query['post_type']) && !empty($wp_query->query['name'])) {
                    global $wpdb;
                    return $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1",
                            $wp_query->query['post_type'],
                            $wp_query->query['name']
                        )
                    );
                }
            }
        } elseif (isset($_REQUEST['post'])) {
            return (int)$_REQUEST['post'];
        } elseif (isset($_REQUEST['post_ID'])) {
            return (int)$_REQUEST['post_ID'];
        } elseif (isset($_REQUEST['post_id'])) {
            return (int)$_REQUEST['post_id'];
        } elseif (defined('WOOCOMMERCE_VERSION') && !empty($_REQUEST['product_id'])) {
            return (int)$_REQUEST['product_id'];
        }
    }

    public static function getTaxonomyCap($taxonomy, $cap_property)
    {
        $tx_obj = get_taxonomy($taxonomy);
        return ($tx_obj && isset($tx_obj->cap->$cap_property)) ? $tx_obj->cap->$cap_property : '';
    }

    public static function getTypeCap($post_type, $cap_property)
    {
        $type_obj = get_post_type_object($post_type);
        return ($type_obj && isset($type_obj->cap->$cap_property)) ? $type_obj->cap->$cap_property : '';
    }

    public static function ttidToTermid($tt_ids, &$taxonomy, $all_terms = false)
    {
        if (!$taxonomy && is_array($tt_ids))  // if multiple terms are involved, avoid complication of multiple taxonomies
            return false;

        if (!$all_terms) {
            static $buffer_all_terms;

            if (!isset($buffer_all_terms)) {
                global $wpdb;

                $buffer_all_terms = [];

                $results = $wpdb->get_results("SELECT taxonomy, term_id, term_taxonomy_id FROM $wpdb->term_taxonomy");
                foreach ($results as $row) {
                    $buffer_all_terms[$row->taxonomy][] = $row;
                }
            }

            $all_terms = $buffer_all_terms;
        }

        $term_ids = [];

        if (is_object($all_terms) || !$tt_ids) // error on invalid taxonomy
            return $tt_ids;

        foreach ((array)$tt_ids as $tt_id) {
            foreach (array_keys($all_terms) as $_taxonomy) {
                if ($taxonomy && ($_taxonomy != $taxonomy))  // if conversion is for a single term, taxonomy specification is not required
                    continue;

                foreach (array_keys($all_terms[$_taxonomy]) as $key) {
                    if ($all_terms[$_taxonomy][$key]->term_taxonomy_id == $tt_id) {
                        $term_ids[] = $all_terms[$_taxonomy][$key]->term_id;

                        if (!$taxonomy)
                            $taxonomy = $_taxonomy;  // set byref variable to determined taxonomy

                        break;
                    }
                }
            }
        }

        return (is_array($tt_ids)) ? $term_ids : current($term_ids);
    }

    public static function termidToTtid($term_ids, $taxonomy, $all_terms = false)
    {
        if (!$all_terms) {
            static $buffer_all_terms;

            if (!isset($buffer_all_terms)) {
                global $wpdb;

                $buffer_all_terms = [];

                $results = $wpdb->get_results("SELECT taxonomy, term_id, term_taxonomy_id FROM $wpdb->term_taxonomy");
                foreach ($results as $row) {
                    $buffer_all_terms[$row->taxonomy][] = $row;
                }
            }

            $all_terms = $buffer_all_terms;
        }

        $tt_ids = [];

        if (is_object($all_terms) || !$term_ids || !isset($all_terms[$taxonomy])) // error on invalid taxonomy
            return $term_ids;

        foreach ((array)$term_ids as $term_id) {
            foreach (array_keys($all_terms[$taxonomy]) as $key)
                if (($all_terms[$taxonomy][$key]->term_id == $term_id)) {
                    $tt_ids[] = $all_terms[$taxonomy][$key]->term_taxonomy_id;
                    break;
                }
        }

        return (is_array($term_ids)) ? $tt_ids : current($tt_ids);
    }

    public static function getDescendantIds($item_source, $item_id, $args = [])
    {
        // previously, removed some items based on user_can_admin_object()
        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/AncestryQuery.php');
        return \PressShack\AncestryQuery::queryDescendantIDs($item_source, $item_id, $args);
    }

    // written because WP is_plugin_active() requires plugin folder in arg
    public static function isPluginActive($check_plugin_file)
    {
        $plugins = (array)get_option('active_plugins');
        foreach ($plugins as $plugin_file) {
            if (false !== strpos($plugin_file, $check_plugin_file)) {
                return $plugin_file;
            }
        }

        if (is_multisite()) {
            $plugins = (array)get_site_option('active_sitewide_plugins');

            // network activated plugin names are array keys
            foreach (array_keys($plugins) as $plugin_file) {
                if (false !== strpos($plugin_file, $check_plugin_file)) {
                    return $plugin_file;
                }
            }
        }
    }

    // Wrapper to prevent poEdit from adding core WordPress strings to the plugin .po
    public static function __wp($string, $unused = '')
    {
        return __($string);
    }

    public static function wpVer($wp_ver_requirement)
    {
        static $cache_wp_ver;

        if (empty($cache_wp_ver)) {
            global $wp_version;
            $cache_wp_ver = $wp_version;
        }

        if (!version_compare($cache_wp_ver, '0', '>')) {
            // If global $wp_version has been wiped by WP Security Scan plugin, temporarily restore it by re-including version.php
            if (file_exists(ABSPATH . WPINC . '/version.php')) {
                include(ABSPATH . WPINC . '/version.php');
                $return = version_compare($wp_version, $wp_ver_requirement, '>=');
                $wp_version = $cache_wp_ver;  // restore previous wp_version setting, assuming it was cleared for security purposes
                return $return;
            } else {
                // Must be running a future version of WP which doesn't use version.php
                return true;
            }
        }

        // normal case - global $wp_version has not been tampered with
        return version_compare($cache_wp_ver, $wp_ver_requirement, '>=');
    }

    public static function isMuPlugin($plugin_path = '')
    {
        if ( ! $plugin_path && defined(PRESSPERMIT_FILE) ) {
            $plugin_path = PRESSPERMIT_FILE;
        }
        return (defined('WPMU_PLUGIN_DIR') && (false !== strpos($plugin_path, WPMU_PLUGIN_DIR)));
    }

    public static function isNetworkActivated($plugin_file = '')
    {
        if ( ! $plugin_file && defined('PRESSPERMIT_FILE') ) {
            $plugin_file = plugin_basename(PRESSPERMIT_FILE);
        }
        return (array_key_exists($plugin_file, (array)maybe_unserialize(get_site_option('active_sitewide_plugins'))));
    }

    public static function isAjax($action)
    {
        return defined('DOING_AJAX') && DOING_AJAX && !empty($_REQUEST['action']) && in_array($_REQUEST['action'], (array)$action);
    }

    public static function doingAdminMenus()
    {
        return (
            (did_action('_admin_menu') && !did_action('admin_menu'))  // menu construction
            || (did_action('admin_head') && !did_action('adminmenu'))  // menu display
        );
    }

    public static function postAuthorClause($args = []) {
        global $wpdb, $current_user;

        $defaults = [
            'user_id' => $current_user->ID,
            'compare' => '=',
            'join' => '',
        ];

        if ($ppma_active = defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=') && !empty($args['join']) && strpos($args['join'], 'ppma_t')) {
            $defaults['join_table'] = 'ppma_t';
        }

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (empty($args['src_table'])) {
            $src_table = (!empty($args['source_alias'])) ? $args['source_alias'] : $wpdb->posts;
            $args['src_table'] = $src_table;
        } else {
            $src_table = $args['src_table'];
        }

        if ($ppma_active && $join_table && !defined('PRESSPERMIT_DISABLE_AUTHORS_JOIN')) {
            $join_where = ($compare == '=') ? "OR $join_table.term_id > 0" : "AND $join_table.term_id IS NULL";
            $clause = "( $src_table.post_author $compare '$user_id' $join_where )";
        } else {
            $clause = "$src_table.post_author $compare $user_id";
        }

        return $clause;
    }
}
