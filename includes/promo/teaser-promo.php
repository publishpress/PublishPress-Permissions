<div class="wrap publishpress-caps-manage pressshack-admin-wrapper pp-conditions pp-capability-menus-wrapper-promo">
    <header>
    <div class="pp-icon"><?php echo '<img src="' . PRESSPERMIT_URLPATH . '/common/img/publishpress-logo-icon.png" alt="" />';?></div>
    <h1>
    <?php _e('Content Teaser', 'press-permit-core');?>
    </h1>
    </header>

    <table id="akmin">
        <tr>
            <td class="content">
                <div id="pp-capability-menu-wrapper" class="postbox" style="box-shadow: none;">
                    <div class="pp-capability-menus-promo">
                        <div class="pp-capability-menus-promo-inner">
                            <img src="<?php echo PRESSPERMIT_URLPATH . '/includes/promo/permissions-teaser-desktop.jpg';?>" class="pp-capability-desktop" />
                            <img src="<?php echo PRESSPERMIT_URLPATH . '/includes/promo/permissions-teaser-mobile.jpg';?>" class="pp-capability-mobile" />
                            <div class="pp-capability-menus-promo-content">
                                <p>
                                    <?php _e('Show a teaser message or login prompt for protected content. Available in PublishPress Permissions Pro.', 'press-permit-core'); ?>
                                </p>
                                <p>
                                    <a href="https://publishpress.com/links/permissions-teaser-screen" target="_blank">
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
