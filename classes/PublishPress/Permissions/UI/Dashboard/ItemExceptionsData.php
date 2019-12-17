<?php

namespace PublishPress\Permissions\UI\Dashboard;

//use \PressShack\LibArray as Arr;

class ItemExceptionsData
{
    public $agent_info = [];
    public $current_exceptions;
    public $inclusions_active = [];

    public function __construct()
    {
        add_action('presspermit_load_item_exceptions', [$this, 'loadExceptions'], 10, 5);
    }

    public function loadExceptions($via_item_source, $for_item_source, $via_item_type, $item_id, $args = [])
    {
        global $wpdb, $wp_roles;

        if (!isset($this->current_exceptions)) {
            $this->current_exceptions = [];
            $this->agent_info = [];
        }

        $defaults = array_merge(
            compact('for_item_source', 'item_id'),
            [
                'agent_type' => '',
                'agent_id' => [],
                'for_item_type' => '',
                'for_item_status' => '',
                'via_item_source' => $via_item_source
            ]
        );

        $force_vars = [
            'cols' => 'e.agent_type, e.agent_id, e.operation, e.mod_type, i.eitem_id, i.assign_for',
            'return_raw_results' => true,
            'inherited_from' => '',
            'assign_for' => '',
            'hierarchical' => false,
        ];

        $args = array_merge($defaults, $args, $force_vars);

        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        foreach (array_keys($force_vars) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : $via_item_type;

        if (('term' == $via_item_source) && !$for_item_type) {
            unset($args['for_item_source']);
            $args['post_types'] = [''];
        }

        $args['cols'] .= ', e.for_item_type';

        if (!empty($agent_type)) {
            $agent_type = $agent_type;
            $agents_by_type = [$agent_type => []];  // need this for ajax when an agent type has no current stored
        } else {
            $agents_by_type = [];
        }

        $exc = $pp->getExceptions($args);

        foreach ($exc as $row) {
            $row->assign_for = trim($row->assign_for);  // work around Project Nami issue (enum column values get padded with trailing spaces)

            Arr::setElem(
                $this->current_exceptions,
                [$row->for_item_type, $row->operation, $row->agent_type, $row->agent_id, $row->assign_for, $row->mod_type]
            );

            $this->current_exceptions[$row->for_item_type][$row->operation][$row->agent_type][$row->agent_id][$row->assign_for][$row->mod_type] = $row->eitem_id;

            $agents_by_type[$row->agent_type][] = $row->agent_id;
        }

        foreach (array_keys($agents_by_type) as $agent_type) {
            $agents_by_type[$agent_type] = array_unique($agents_by_type[$agent_type]);

            $ids = $agents_by_type[$agent_type];
            if (!empty($agent_id)) {
                $ids = array_merge($ids, (array)$agent_id);  // ajax passes in specific id(s)
            }

            if ('user' == $agent_type) {
                $this->agent_info['user'] = $wpdb->get_results(
                    "SELECT ID, user_login as name, display_name FROM $wpdb->users"
                    . " WHERE ID IN ('" . implode("','", array_map('intval', $ids)) . "')"
                    . " ORDER BY user_login",
                    OBJECT_K
                );
            } elseif ('pp_group' != $agent_type) {
                $_args = ['ids' => $ids];
                $this->agent_info[$agent_type] = $pp->groups()->getGroups($agent_type, $_args);
            }
        }

        // retrieve info for all WP roles regardless of exception storage
        $_args = ['cols' => "ID, group_name AS name, metagroup_type, metagroup_id"];

        if (!empty($agent_id)) {  // ajax usage
            $_args['ids'] = (array)$agent_id;
        } else {
            $_where = (isset($agents_by_type['pp_group']))
                ? "ID IN ('" . implode("','", $agents_by_type['pp_group']) . "')"
                : "  metagroup_type != 'wp_role'";

            if (!$pp_only_roles = $pp->getOption('supplemental_role_defs')) {
                $pp_only_roles = [];
            }

            $_args['where'] = " AND ( $_where OR ( metagroup_type = 'wp_role' AND metagroup_id NOT IN ('" . implode("','", $pp_only_roles) . "') ) )";
        }

        $this->agent_info['pp_group'] = $pp->groups()->getGroups('pp_group', $_args);

        $this->agent_info['wp_role'] = [];

        // rekey WP role exceptions
        foreach ($this->agent_info['pp_group'] as $agent_id => $group) {
            if ('wp_role' == $group->metagroup_type) {
                $this->agent_info['wp_role'][$agent_id] = (object)$this->agent_info['pp_group'][$agent_id];

                if ($role_exists = isset($wp_roles->role_names[$group->metagroup_id])) {
                    $this->agent_info['wp_role'][$agent_id]->name = $wp_roles->role_names[$group->metagroup_id];
                }

                unset($this->agent_info['pp_group'][$agent_id]);

                if ($role_exists || in_array($group->metagroup_id, ['wp_anon', 'wp_all', 'wp_auth'], true)) {
                    foreach (array_keys($this->current_exceptions) as $for_item_type) {
                        foreach (array_keys($this->current_exceptions[$for_item_type]) as $op) {
                            if (isset($this->current_exceptions[$for_item_type][$op]['pp_group'][$agent_id])) {

                                $this->current_exceptions[$for_item_type][$op]['wp_role'][$agent_id]
                                    = (array)$this->current_exceptions[$for_item_type][$op]['pp_group'][$agent_id];

                                unset($this->current_exceptions[$for_item_type][$op]['pp_group'][$agent_id]);
                            }
                        }
                    }
                }
            }
        }

        // don't include orphaned assignments in metabox tab count
        foreach (array_keys($this->current_exceptions) as $for_item_type) {
            foreach (array_keys($this->current_exceptions[$for_item_type]) as $op) {
                foreach (array_keys($this->current_exceptions[$for_item_type][$op]) as $agent_type) {

                    $this->current_exceptions[$for_item_type][$op][$agent_type]
                        = array_intersect_key($this->current_exceptions[$for_item_type][$op][$agent_type], $this->agent_info[$agent_type]);
                }
            }
        }

        // determine if inclusions are set for any agents
        $where = ('term' == $via_item_source) ? "AND e.via_item_type = '$via_item_type'" : '';
        $where .= ('term' == $via_item_source) ? '' : " AND e.for_item_source = '$for_item_source'";

        $query_users = (isset($this->agent_info['user'])) ? array_keys($this->agent_info['user']) : [];
        if (!empty($args['agent_type']) && ('user' == $args['agent_type']) && !empty($args['agent_id']))
            $query_users = array_merge($query_users, (array)$args['agent_id']);

        $agents_clause = [];

        if (!empty($args['agent_type']) && ('user' != $args['agent_type']))
            $agents_clause[] = "( e.agent_type = '" . $args['agent_type'] . "' )";

        if ($query_users)
            $agents_clause[] = "( e.agent_type != 'user' OR e.agent_id IN ('" . implode("','", $query_users) . "') )";

        $agents_clause = ($agents_clause) ? Arr::implode(' OR ', $agents_clause) : '1=1';

        //$_assignment_modes = ($hierarchical) ? [ 'item', 'children' ] : [ 'item' ];  // this function is not currently used to retrieve propagation records 
        $_assignment_modes = ['item'];

        // Populate only for wp roles, groups and users with stored exceptions.  Will query for additional individual users as needed.
        foreach ($_assignment_modes as $_assign_for) {
            $results = $wpdb->get_results(
                "SELECT DISTINCT e.agent_type, e.agent_id, e.operation, e.for_item_type FROM $wpdb->ppc_exceptions AS e"
                . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                . " WHERE $agents_clause AND i.assign_for = '$_assign_for' AND e.mod_type = 'include' $where"
            );

            foreach ($results as $row) {
                if (('pp_group' == $row->agent_type) && in_array($row->agent_id, array_keys($this->agent_info['wp_role'])))
                    $_agent_type = 'wp_role';
                else
                    $_agent_type = $row->agent_type;

                Arr::setElem(
                    $this->inclusions_active,
                    [$row->for_item_type, $row->operation, $_agent_type, $row->agent_id, $_assign_for]
                );

                $this->inclusions_active[$row->for_item_type][$row->operation][$_agent_type][$row->agent_id][$_assign_for] = true;
            }
        }
    }
}
