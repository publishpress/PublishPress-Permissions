<div class="wrap publishpress-caps-manage pressshack-admin-wrapper pp-conditions pp-capability-menus-wrapper-promo">
    <header>
    <div class="pp-icon"><?php echo '<img src="' . PRESSPERMIT_URLPATH . '/common/img/publishpress-logo-icon.png" alt="" />';?></div>
    <h1>
    <?php _e('Sync User Posts', 'press-permit-core');?>
    </h1>
    </header>

    <table id="akmin">
        <tr>
            <td class="content">
                <div id="pp-capability-menu-wrapper" class="postbox" style="box-shadow: none;">
                    <div class="pp-capability-menus-promo">
                        <div class="pp-capability-menus-promo-inner">
                            <img src="<?php echo PRESSPERMIT_URLPATH . '/includes/promo/permissions-sync-desktop.jpg';?>" class="pp-capability-desktop" />
                            <img src="<?php echo PRESSPERMIT_URLPATH . '/includes/promo/permissions-sync-mobile.jpg';?>" class="pp-capability-mobile" />
                            <div class="pp-capability-menus-promo-content">
                                <p>
                                    <?php _e('Generate a page for each user in specified roles. This feature is available in PublishPress Permissions Pro.', 'press-permit-core'); ?>
                                </p>
                                <p>
                                    <a href="https://publishpress.com/links/permissions-sync-screen" target="_blank">
                                        <?php _e('Upgrade to Pro', 'capsman-enhanced'); ?>
                                    </a>
                                </p>
                            </div>
                            <div class="pp-capability-menus-promo-gradient"></div>
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
