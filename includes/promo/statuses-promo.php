<div class="wrap publishpress-caps-manage pressshack-admin-wrapper pp-conditions pp-permissions-menus-wrapper-promo">
    <header>
    <div class="pp-icon"><?php echo '<img src="' . PRESSPERMIT_URLPATH . '/common/img/publishpress-logo-icon.png" alt="" />';?></div>
    <h1>
    <?php _e('Configure PublishPress Workflow Statuses', 'press-permit-core');?>
    </h1>
    </header>

    <table id="akmin">
        <tr>
            <td class="content">
                <div id="pp-permissions-menu-wrapper" class="postbox" style="box-shadow: none; background: none;">
                    <div class="pp-permissions-menus-promo">
                        <div class="pp-permissions-menus-promo-inner">
                            <img src="<?php echo PRESSPERMIT_URLPATH . '/includes/promo/permissions-statuses-desktop.jpg';?>" class="pp-permissions-desktop" />
                            <img src="<?php echo PRESSPERMIT_URLPATH . '/includes/promo/permissions-statuses-mobile.jpg';?>" class="pp-permissions-mobile" />
                            <div class="pp-permissions-menus-promo-content">
                                <p>
                                    <?php _e('Control access to custom post statuses. This workflow feature is available in PublishPress Permissions Pro.', 'press-permit-core'); ?>
                                </p>
                                <p>
                                    <a href="https://publishpress.com/links/permissions-statuses-screen" target="_blank">
                                        <?php _e('Upgrade to Pro', 'capsman-enhanced'); ?>
                                    </a>
                                </p>
                            </div>
                            <div class="pp-permissions-menus-promo-gradient"></div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <?php
    presspermit()->admin()->publishpressFooter();
    ?>
</div>

<?php
