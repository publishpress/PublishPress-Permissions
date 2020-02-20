<?php
namespace PublishPress\Permissions;

class REST
{
    //var $request;
    var $is_view_method = false;
    var $endpoint_class = '';
    var $taxonomy = '';
    var $post_type = '';
    var $post_id = 0;
    var $post_status = '';
    var $is_posts_request = false;
    var $is_terms_request = false;
    var $operation = '';
    var $params = [];

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new REST();
            presspermit()->doing_rest = true;
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('presspermit_rest_post_cap_requirement', [$this, 'fltRestPostCapRequirement'], 10, 2);
    }

    public static function getPostType()
    {
        return self::instance()->post_type;
    }

    public static function getPostID()
    {
        return self::instance()->post_id;
    }

    function fltRestPostCapRequirement($orig_cap, $item_id)
    {
        if ('edit' == $this->operation) {
            $post_type = get_post_field('post_type', $item_id);

            if ($type_obj = get_post_type_object($post_type)) {
                if ($orig_cap == $type_obj->cap->read_post) {
                    $orig_cap = $type_obj->cap->edit_post;
                }
            }
        }

        return $orig_cap;
    }

    function pre_dispatch($rest_response, $rest_server, $request)
    {
        $method = $request->get_method();
		$path   = $request->get_route();
        
		foreach ( $rest_server->get_routes() as $route => $handlers ) {
			$match = preg_match( '@^' . $route . '$@i', $path, $matches );

			if ( ! $match ) {
				continue;
			}

			$args = [];
			foreach ( $matches as $param => $value ) {
				if ( ! is_int( $param ) ) {
					$args[ $param ] = $value;
				}
			}

			foreach ( $handlers as $handler ) {
                if (!is_array($handler['callback']) || !isset($handler['callback'][0]) || !is_object($handler['callback'][0])) {
                    continue;
                }

				$this->endpoint_class = get_class($handler['callback'][0]);

                if (!in_array(
                    $this->endpoint_class, 
                    ['WP_REST_Posts_Controller', 'WP_REST_Posts_Terms_Controller', 'WP_REST_Terms_Controller'], 
                    true)
                ) {
                    continue;
                }

                //$this->request = $request;

                $this->is_view_method = in_array($method, [\WP_REST_Server::READABLE, 'GET']);
                $this->params = $request->get_params();
                
                $headers = $request->get_headers();
                $this->referer = (isset($headers['referer'])) ? $headers['referer'] : '';
                if (is_array($this->referer)) {
                    $this->referer = reset($this->referer);
                }

                $this->operation = (isset($this->params['context'])) ? sanitize_key($this->params['context']) : '';
                if ('view' == $this->operation) {
                    $this->operation = 'read';
                }

			  // voluntary filtering of get_items (for WYSIWY can edit, etc.)
                if ($this->is_view_method && ('read' == $this->operation) && !empty($_REQUEST['operation'])) {
                    $this->operation = $_REQUEST['operation'];
                }
			
                // NOTE: setting or default may be adapted downstream
                if (!in_array($this->operation, ['edit', 'assign', 'manage', 'delete'], true)) {
                    $this->operation = ($this->is_view_method) ? 'read' : 'edit';
                }

                switch ($this->endpoint_class) {
                    case 'WP_REST_Posts_Controller':
                        $this->post_type = (!empty($args['post_type'])) ? $args['post_type'] : '';
                        
                        if ( ! $this->post_id = (!empty($args['id'])) ? $args['id'] : 0 ) {
                            $this->post_id = (!empty($this->params['id'])) ? $this->params['id'] : 0;
                        }

                        if (('revision' != $this->post_type) && presspermit()->getTypeOption('default_privacy', $this->post_type)) {
                            if (false === get_post_meta($this->post_id, '_pp_original_status')) {
                                global $wpdb;
                                if ( $post_status = $wpdb->get_var( $wpdb->prepare("SELECT post_status FROM $wpdb->posts WHERE ID = %s", $this->post_id) ) ) {
                                    update_post_meta($this->post_id, '_pp_original_status', $this->post_status);
                                }
                            }
                        }

                        // workaround for superfluous post retrieval by Gutenberg on Parent Page query
                        if ($this->is_view_method && !$this->post_id) {
                            $params = $request->get_params();

                            if (!empty($params['exclude']) || !empty($params['parent_exclude'])) {
                                // Prevent Gutenberg from triggering needless post_name retrieval (for permalink generation) for each item in Page Parent dropdown
                                if (!empty($_SERVER) && !empty($_SERVER['HTTP_REFERER']) && false !== strpos($_SERVER['HTTP_REFERER'], admin_url())) {
                                    global $wp_post_types;

                                    if (!$this->post_type) {
                                        $id = (!empty($params['exclude'])) ? $params['exclude'] : $params['parent_exclude'];
                                        $this->post_type = get_post_field('post_type', $id);
                                    }

                                    if (!empty($wp_post_types[$this->post_type])) {
                                        $wp_post_types[$this->post_type]->publicly_queryable = false;
                                        $wp_post_types[$this->post_type]->_builtin = false;
                                    }

                                    // Prevent Gutenberg from triggering revisions retrieval for each item in Page Parent dropdown
                                    add_filter('wp_revisions_to_keep', function($num, $post) {return 0;}, 10, 2);
                                }
                            }
                        }

                        if (!$this->post_type) {
                            if (!$this->post_type = get_post_field('post_type', $this->post_id)) {
                                return $rest_response;
                            }
                        } elseif (!empty($args['post_type'])) {
                            $this->post_type = $args['post_type'];
                        }

                        $this->is_posts_request = true;

                        if (presspermit()->isContentAdministrator()) {
                            break;
                        }

                        // do this here because WP does not trigger a capability check if the post type is public
                        if ($this->post_id && in_array($this->post_type, presspermit()->getEnabledPostTypes(), true)) {
                            if ('read' == $this->operation) {
                                $post_status_obj = get_post_status_object(get_post_field('post_status', $this->post_id));
                                $check_cap = ($post_status_obj->public) ? 'read_post' : '';

                            } elseif(in_array($this->operation, ['edit','delete'], true)) {
                                $check_cap = "{$this->operation}_post";
                            } else {
                                $check_cap = false;
                            }

                            if ($check_cap && ! current_user_can($check_cap, $this->post_id)) {
                                return self::rest_denied();
                            }
                        }

                        break;

                    case 'WP_REST_Terms_Controller':
                        if (empty($args['taxonomy'])) break;

                        $this->taxonomy = $args['taxonomy'];

                        $required_operation = ('read' == $this->operation) ? 'read' : 'manage';
                        
                        $this->is_terms_request = true;

                        if (presspermit()->isContentAdministrator()) {
                            break;
                        }

                        if (!empty($args['post'])) {
                            $post_id = $this->params['post'];

                            $check_cap = ('read' == $required_operation) ? 'read_post' : 'edit_post';

                            if (!current_user_can($check_cap, $post_id)) {
                                return self::rest_denied();
                            }
                        }

                        if (!empty($params['id'])) {
                            $user_terms = get_terms(
                                $this->taxonomy, 
                                ['required_operation' => $required_operation, 'hide_empty' => 0, 'fields' => 'ids']
                            );

                            if (!in_array($params['id'], $user_terms)) {
                                return self::rest_denied();
                            }
                        }

                        break;

                } // end switch
            }
        }

        if ($this->is_posts_request) {
            add_filter('presspermit_rest_post_type', [$this, 'fltRestPostType']);
            add_filter('presspermit_rest_post_id', [$this, 'fltRestPostID']);
        }

        return $rest_response;
    }  // end function pre_dispatch

    private function rest_denied()
    {
        return new \WP_Error('rest_forbidden', __("Sorry, you are not allowed to do that."), ['status' => 403]);
    }

    function fltRestPostType($post_type)
    {
        return ($this->post_type) ? $this->post_type : $post_type;
    }

    function fltRestPostID($post_id)
    {
        return ($this->post_id) ? $this->post_id : $post_id;
    }
}
