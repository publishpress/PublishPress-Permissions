<?php

namespace PublishPress\Permissions\UI\Dashboard;

class PluginAdmin
{
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin_basename(PRESSPERMIT_FILE), [$this, 'fltPluginActionLinks'], 10, 2);

        add_action('after_plugin_row_' . plugin_basename(PRESSPERMIT_FILE), [$this, 'actCorePluginStatus'], 10, 3);

        if (!empty($_REQUEST['activate']) || !empty($_REQUEST['activate-multi'])) {
            if (get_option('presspermit_activation')) {
                delete_option('presspermit_activation');
                $this->activationNotice();
            }
        }

        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=')) {
            self::authorsVersionNotice();
        }
    }

    public function actCorePluginStatus($plugin_file, $plugin_data, $status)
    {
        $message = '';

        if (!$this->typeUsageStored()) {
            if (get_post_types(['public' => true, '_builtin' => false])) {
                $url = admin_url('admin.php?page=presspermit-settings');

                $message = sprintf(
                    __('PublishPress Permissions needs directions. Please go to %1$sPermissions > Settings%2$s and indicate which Post Types and Taxonomies should be filtered.', 'press-permit-core'),
                    '<a href="' . $url . '">',
                    '</a>'
                );
            }
        }

        if (presspermit()->isPro() && (is_network_admin() || !is_multisite())) {
            $key = presspermit()->getOption('edd_key');
            $keyStatus = isset($key['license_status']) ? $key['license_status'] : 'invalid';

            if (in_array($keyStatus, ['invalid', 'expired'])) {
                require_once PRESSPERMIT_CLASSPATH . '/PluginStatus.php';
                
                if ($message) {
                    $message .= '<br /><br />';
                }
                
                $message .= ('expired' == $keyStatus) 
                ? \PublishPress\Permissions\PluginStatus::renewalMsg() 
                : \PublishPress\Permissions\PluginStatus::buyMsg();
            }
        }

        if ($message) {
            $wp_list_table = _get_list_table('WP_Plugins_List_Table');

            echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count()
                . '" class="plugin-update"><div class="update-message">' . $message . '</div></td></tr>';
        }
    }

    // adds an Options link next to Deactivate, Edit in Plugins listing
    public function fltPluginActionLinks($links, $file)
    {
        if ($file == plugin_basename(PRESSPERMIT_FILE)) {
            if (!is_network_admin()) {
                $links[] = "<a href='admin.php?page=presspermit-settings'>" . PWP::__wp('Settings') . "</a>";
            }
        }

        return $links;
    }

    private function typeUsageStored()
    {
        $types_vals = get_option('presspermit_enabled_post_types');
        if (!is_array($types_vals)) {
            $txs_val = get_option('presspermit_enabled_taxonomies');
        }

        return is_array($types_vals) || is_array($txs_val);
    }

    private function activationNotice()
    {
        if (!$this->typeUsageStored() && !is_network_admin()) {
            $url = admin_url('admin.php?page=presspermit-settings');
			$plugin_title = (presspermit()->isPro()) ? 'PublishPress Permissions Pro' : 'PublishPress Permissions';            

            presspermit()->admin()->notice(
                sprintf(
                    __('Thanks for activating %1$s. Please go to %2$sPermissions > Settings%3$s to enable Post Types and Taxonomies for custom permissions.', 'press-permit-core'),
                    $plugin_title,
					'<a href="' . $url . '">',
                    '</a>'
                ), 'initial-activation'
            );
        }
    }

    public static function authorsVersionNotice($args = [])
    {
        $id = (!empty($args['ignore_dismissal'])) ? '' : 'authors-integration-version';

        presspermit()->admin()->notice(
            __('Please upgrade PublishPress Authors to version 3.8.0 or later for Permissions integration.', 'press-permit-core'),
            $id,
            $args
        );
    }
}
