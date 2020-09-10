<?php
namespace PublishPress\Permissions\Collab\UI;

class RoleUsageQuery
{
    /**
     * List of found group ids
     *
     * @access private
     * @var array
     */
    var $results;

    /**
     * Total number of found roles for the current query
     *
     * @access private
     * @var int
     */
    var $total_roles = 0;

    /**
     *
     * @param string|array $args The query variables
     * @return WP_Group_Query
     */
    function __construct($query = null)
    {
        $this->query_vars = wp_parse_args($query, [
            'blog_id' => get_current_blog_id(),
            'include' => [],
            'exclude' => [],
            'search' => '',
            'orderby' => 'login',
            'order' => 'ASC',
            'offset' => '', 'number' => '',
            'count_total' => true,
            'fields' => 'all',
        ]);

        $this->prepare_query();
        $this->query();
    }

    function prepare_query()
    {
    }

    /**
     * Execute the query, with the current variables
     *
     * @since 3.1.0
     * @access private
     */
    function query()
    {
        global $wp_roles;

        $roles = presspermit()->role_defs->pattern_roles;

        foreach ($wp_roles->role_names as $role_name => $role_caption) {
            if (('administrator' != $role_name) && !isset($this->results[$role_name])) {
                $roles[$role_name] = (object)['labels' => (object)['singular_name' => $role_caption]];
            }
        }

        $ordered_roles = [];
        foreach (array_keys($roles) as $role_name) {
            $ordered_roles[$role_name] = $roles[$role_name]->labels->singular_name;
        }
        uasort($ordered_roles, 'strnatcasecmp');

        $this->results = [];
        foreach (array_keys($ordered_roles) as $role_name) {
            $this->results[$role_name] = $roles[$role_name];
            $this->results[$role_name]->name = $role_name;
            $this->results[$role_name]->usage = self::get_role_usage($role_name);
        }

        $this->total_roles = count($this->results);
    }

    public static function get_role_usage($role_name)
    {
        $pp = presspermit();

        if (isset($pp->role_defs->pattern_roles[$role_name]))
            return 'pattern';

        elseif (isset($pp->role_defs->direct_roles[$role_name]))
            return 'direct';
        else
            return false;
    }

    /*
     * Used internally to generate an SQL string for searching across multiple columns
     *
     * @access protected
     * @since 3.1.0
     *
     * @param string $string
     * @param array $cols
     * @param bool $wild Whether to allow wildcard searches. Default is false for Network Admin, true for
     *  single site. Single site allows leading and trailing wildcards, Network Admin only trailing.
     * @return string
     */
    function get_search_sql($string, $cols, $wild = false)
    {
    }

    /**
     * Return the list of groups
     *
     * @access public
     *
     * @return array
     */
    function get_results()
    {
        return $this->results;
    }

    /**
     * Return the total number of groups for the current query
     *
     * @access public
     *
     * @return array
     */
    function get_total()
    {
        return $this->total_roles;
    }
}
