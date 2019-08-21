<?php
namespace PublishPress\Permissions;

class ErrorNotice
{
    private $notices = [];

    public function __construct($err = '', $args = [])
    {
        global $pagenow;

        if (!is_admin()) {
            return;
        }

        if (!$err) { // constructor argument is optional; addNotice() may be called directly
            return;
        }

        $defaults = [
            'module_title' => 'PressPermit',
            'module_slug' => '',
            'module_folder' => '',
            'min_version' => '',
            'version' => '',
        ];
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (
            isset($pagenow) && ('update.php' != $pagenow)
            && in_array($err, ['old_pp', 'old_wp', 'old_extension', 'duplicate_module'], true)
        ) {
            return;  // @todo: review which messages to limit to update.php
        }

        switch ($err) {
            case 'multiple_pp':
                if (is_admin() && ('plugins.php' == $pagenow) && !strpos(urldecode($_SERVER['REQUEST_URI']), 'deactivate')) {
                    $message = sprintf(
                        '<strong>Error:</strong> Multiple copies of %1$s activated. Only the copy in folder "%2$s" is functional.',
                        'PressPermit',
                        dirname(plugin_basename(PRESSPERMIT_FILE))
                    );

                    $this->addNotice($message, ['style' => "color: black"]);
                }
                break;

            case 'pp_core_active':
                // buffer current extension plugin activations so active PP extensions can be default-enabled as Pro modules
                if (!get_option('ppc_migration_buffer_active_plugins')) {
                    update_option('ppc_migration_buffer_active_plugins', get_option('active_plugins'));
                }

                $this->addNotice('PressPermit cannot operate until Press Permit Core and PP extension plugins are deactivated.');
                break;

            case 'rs_active':
                define('PRESSPERMIT_DISABLE_QUERYFILTERS', true);
            
                $args = (isset($_REQUEST['page']) && (0 === strpos($_REQUEST['page'], 'presspermit-')))
                    ? ['style' => 'margin-top:30px;color:black']
                    : [];

                $this->addNotice(
                    'PressPermit is running in configuration only mode. Access filtering will not be applied until Role Scoper is deactivated.',
                    $args
                );

                define('PP_DISABLE_MENU_TWEAK', true);
                break;

            case 'pp_legacy_active':
                $this->addNotice('PressPermit cannot operate with an older version of Press Permit active.');   // Press Permit 1.x beta, circa 2011
                break;

            case 'old_php':
                $this->addNotice(
                    sprintf(
                        __('%1$s won&#39;t work until you upgrade PHP to version %2$s or later. Current version: %3$s', 'press-permit-core'),
                        $module_title,
                        $min_version,
                        $version
                    )
                );
                break;

            case 'old_pp':
                $this->addNotice(
                    sprintf(
                        __('%1$s won&#39;t work until you upgrade PressPermit to version %2$s or later.', 'press-permit-core'),
                        $module_title,
                        $min_version
                    )
                );
                break;

            case 'old_wp':
                $this->addNotice(
                    sprintf(
                        __('%1$s won&#39;t work until you upgrade WordPress to version %2$s or later.', 'press-permit-core'),
                        $module_title,
                        $min_version
                    )
                );
                break;

            case 'old_extension':
                $this->addNotice(
                    sprintf(
                        __('This version of %1$s cannot work with your current PressPermit version. Please upgrade it to %2$s or later.', 'press-permit-core'),
                        $module_title,
                        $min_version
                    )
                );
                break;

            case 'duplicate_module':
                $this->addNotice(
                    sprintf(
                        __('Duplicate PressPermit module activated (%1$s in folder %2$s).', 'press-permit-core'),
                        $module_slug,
                        $module_folder
                    )
                );
                break;

            default:
                $default_switch = true;
        }

        if (empty($default_switch)) {
            do_action('presspermit_load_error', $err);         
        }

        return true;
    }

    public function addNotice($body, $args = [])
    {
        if (!$this->notices) {
            add_action('all_admin_notices', [$this, 'actDoNotices'], 5);
        }

        if (!empty($args['id'])) {
            $this->notices[$args['id']] = (object)array_merge(compact('body'), $args);
        } else {
            $this->notices[] = (object)array_merge(compact('body'), $args);
        }
    }

    public function actDoNotices()
    {
        global $pp_plugin_page;

        foreach ($this->notices as $msg_id => $msg) {
            $style = (!empty($msg->style)) ? "style='$msg->style'" : "style='color:black'";
            
            $class = 'pp-admin-notice';
            
            $class .= (!empty($msg->class)) ? "class='$msg->class'" : '';

		    if ( ! empty( $pp_plugin_page ) )
			    $class .= ' pp-admin-notice-plugin';

            if (is_numeric($msg_id)) :  // if no msg_id was provided, notice is not dismissible
                echo "<div id='message' class='error fade' $style $class>" . $msg->body . '</div>';
            else :?>
                <div class='updated' class='<?php echo $class;?>' class='pp_dashboard_message'><p><?php echo $msg->body ?>&nbsp;
                <a href="javascript:void(0);" class="presspermit-dismiss-notice" id="<?php echo $msg_id;?>"><?php _e("Dismiss", "pp") ?></a>
                </p></div>
        <?php endif;
        }
		?>
		<script type="text/javascript">
            jQuery(document).ready( function($) {
                $('a.presspermit-dismiss-notice').click(function(e) {
                    $(this).closest('div').slideUp();
                    jQuery.post(ajaxurl, {action:"pp_dismiss_msg", msg_id:$(this).attr('id'), cookie: encodeURIComponent(document.cookie)});
                });
            });
		</script>
		<?php
    }
}
