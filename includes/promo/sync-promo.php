<div class="wrap publishpress-caps-manage pressshack-admin-wrapper pp-conditions pp-permissions-menus-wrapper-promo">
    <header>
    <h1>
    <?php esc_html_e('Sync User Posts', 'press-permit-core');?>
    </h1>
    </header>

    <table id="akmin">
        <tr>
            <td class="content">
                <div id="pp-permissions-menu-wrapper" class="postbox" style="box-shadow: none; background: none;">
                    <div class="pp-permissions-menus-promo">
                        <div class="pp-permissions-menus-promo-inner">
                            <img src="<?php echo esc_url(PRESSPERMIT_URLPATH . '/includes/promo/permissions-sync-desktop.jpg');?>" class="pp-permissions-desktop" />
                            <img src="<?php echo esc_url(PRESSPERMIT_URLPATH . '/includes/promo/permissions-sync-mobile.jpg');?>" class="pp-permissions-mobile" />
                            <div class="pp-permissions-menus-promo-content">
                                <p>
                                    <?php esc_html_e('Automatically generate a personal page for each user. This feature is available in PublishPress Permissions Pro.', 'press-permit-core'); ?>
                                </p>
                                <p>
                                    <a href="https://publishpress.com/links/permissions-sync-screen" target="_blank">
                                        <?php esc_html_e('Upgrade to Pro', 'capsman-enhanced'); ?>
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
