<?php

namespace PublishPress\Permissions;

class ItemDelete
{
    public static function itemDeleted($item_source, $item_id)
    {
        global $wpdb;

        require_once(PRESSPERMIT_CLASSPATH . '/DB/PermissionsUpdate.php');

        // delete role assignments for deleted term
        if ($eitem_ids = $wpdb->get_col(
            "SELECT eitem_id FROM $wpdb->ppc_exception_items AS i"
            . " INNER JOIN $wpdb->ppc_exceptions AS e ON e.exception_id = i.exception_id WHERE e.via_item_source = '$item_source'"
            . " AND i.item_id = '$item_id'"
        )) {
            DB\PermissionsUpdate::removeExceptionItemsById($eitem_ids);

            // Propagated roles will be converted to direct-assigned roles if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
            $id_in = "'" . implode("', '", $eitem_ids) . "'";
            $wpdb->query("UPDATE $wpdb->ppc_exception_items SET inherited_from = '0' WHERE inherited_from IN ($id_in)");
        }
    }
}
