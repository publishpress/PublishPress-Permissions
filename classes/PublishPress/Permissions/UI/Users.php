<?php
namespace PublishPress\Permissions\UI;

class Users
{
    public function __construct() {
        // called by Dashboard\DashboardFilters::actMenuHandler

        ?>
        <div class="wrap presspermit-groups">
            <?php PluginPage::icon(); ?>
            <h1>
                <?php
                _e('User Permissions', 'press-permit-core');
                ?>
            </h1>

            <div class="pp-bulk-user-perm">
                <h4>
                    <?php
                    _e('View / Edit User Permissions', 'press-permit-core');
                    ?>
                </h4>

                <div>
                    <?php
                    $url = "users.php";
                    printf(
                        __('To assign Supplemental Roles and Specific Permissions directly to a single user, click their "Site Role" cell on the %1$sUsers%2$s screen. The Users listing can be filtered by the following links:', 'press-permit-core'),
                        "<a href='$url'>",
                        '</a>'
                    );
                    ?>
                    <br/><br/>
                    <ul class="pp-notes">
                        <li><?php printf(__('%1$sAll Users%2$s', 'press-permit-core'), "<a href='$url'>", '</a>'); ?></li>
                    </ul>
                    <br/>
                    <ul class="pp-notes">
                        <li><?php printf(__('%1$sUsers who have no custom Permission Group membership%2$s', 'press-permit-core'), "<a href='$url?pp_no_group=1'>", '</a>'); ?></li>
                    </ul>
                    <br/>
                    <ul class="pp-notes">
                        <li><?php printf(__('%1$sUsers who have Supplemental Roles assigned directly%2$s', 'press-permit-core'), "<a href='$url?pp_user_roles=1'>", '</a>'); ?></li>
                        <li><?php printf(__('%1$sUsers who have Specific Permissions assigned directly%2$s', 'press-permit-core'), "<a href='$url?pp_user_exceptions=1'>", '</a>'); ?></li>
                        <li><?php printf(__('%1$sUsers who have Supplemental Roles or Specific Permissions directly%2$s', 'press-permit-core'), "<a href='$url?pp_user_perms=1'>", '</a>'); ?></li>
                    </ul>
                    <br/>
                    <ul class="pp-notes">
                        <li><?php printf(__('%1$sUsers who have Supplemental Roles (directly or via group)%2$s', 'press-permit-core'), "<a href='$url?pp_has_roles=1'>", '</a>'); ?></li>
                        <li><?php printf(__('%1$sUsers who have Specific Permissions (directly or via group)%2$s', 'press-permit-core'), "<a href='$url?pp_has_exceptions=1'>", '</a>'); ?></li>
                        <li><?php printf(__('%1$sUsers who have Supplemental Roles or Specific Permissions (directly or via group)%2$s', 'press-permit-core'), "<a href='$url?pp_has_perms=1'>", '</a>'); ?></li>
                    </ul>
                </div>

                <?php if (presspermit()->getOption('display_hints')) : ?>
                    <span class="pp-subtext">
                        <?php
                        printf(
                            __('%1$snote%2$s: If you don&apos;t see the Site Role column on the Users screen, make sure it is enabled in Screen Options. ', 'press-permit-core'),
                            '<strong>',
                            '</strong>'
                        );
                        ?>
                    </span>
                <?php endif; ?>

            </div>

        </div>
    <?php
    }
}
