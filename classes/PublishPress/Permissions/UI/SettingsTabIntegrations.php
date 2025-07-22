<?php

namespace PublishPress\Permissions\UI;

class SettingsTabIntegrations
{
    private const UPGRADE_PRO_URL = 'https://publishpress.com/permissions/';

    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 5);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_integrations_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['integrations'] = esc_html__('Integrations', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'compatibility_packs' => esc_html__('Compatibility Packs', 'press-permit-core'),
        ];

        $key = 'integrations';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'acf_compatibility' => esc_html__('Advanced Custom Fields', 'press-permit-core'),
            'bbpress_compatibility' => esc_html__('bbPress Forums', 'press-permit-core'),
            'buddypress_compatibility' => esc_html__('BuddyPress', 'press-permit-core'),
            'wpml_compatibility' => esc_html__('WPML', 'press-permit-core'),
            'yoast_seo_compatibility' => esc_html__('Yoast SEO', 'press-permit-core'),
            'woocommerce_compatibility' => esc_html__('WooCommerce', 'press-permit-core'),
            'relevanssi_compatibility' => esc_html__('Relevanssi', 'press-permit-core'),
            'pagebuilders_compatibility' => esc_html__('Page Builders', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'compatibility_packs' => [
                'acf_compatibility', 
                'bbpress_compatibility', 
                'buddypress_compatibility',
                'wpml_compatibility',
                'yoast_seo_compatibility', 
                'woocommerce_compatibility',
                'relevanssi_compatibility',
                'pagebuilders_compatibility'
            ],
        ];

        $key = 'integrations';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();
        $ui = SettingsAdmin::instance();
        $tab = 'integrations';

        $section = 'compatibility_packs';
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <td>
                    <div class="pp-integrations-container">
                        <!-- Pro Banner -->
                        <?php if (presspermit()->isPro()) : ?>
                            <div class="pp-pro-banner">
                                <div>
                                    <h2><?php esc_html_e('Premium Integrations Active', 'press-permit-core'); ?></h2>
                                    <p><?php esc_html_e('You\'re using the Pro version with access to all premium features', 'press-permit-core'); ?></p>
                                </div>
                                <div class="pp-pro-badge-banner"><?php esc_html_e('PRO VERSION', 'press-permit-core'); ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Category Filters -->
                        <div class="pp-category-labels">
                            <div class="pp-category-label active" data-category="all"><?php esc_html_e('All', 'press-permit-core'); ?></div>
                            <div class="pp-category-label" data-category="builder"><?php esc_html_e('Builders', 'press-permit-core'); ?></div>
                            <div class="pp-category-label" data-category="seo"><?php esc_html_e('SEO', 'press-permit-core'); ?></div>
                            <div class="pp-category-label" data-category="ecommerce"><?php esc_html_e('E-Commerce', 'press-permit-core'); ?></div>
                            <div class="pp-category-label" data-category="multilingual"><?php esc_html_e('Multilingual', 'press-permit-core'); ?></div>
                            <div class="pp-category-label" data-category="community"><?php esc_html_e('Community', 'press-permit-core'); ?></div>
                        </div>

                        <div class="pp-integrations-grid">
                            <?php $this->renderCompatibilityPack(
                                'acf_compatibility',
                                esc_html__('Advanced Custom Fields', 'press-permit-core'),
                                esc_html__('Full compatibility with ACF field groups and taxonomies for granular permission control.', 'press-permit-core'),
                                'acf',
                                ['all'],
                                [
                                    esc_html__('Control access to custom fields', 'press-permit-core'),
                                    esc_html__('Taxonomy-based permissions', 'press-permit-core'),
                                    esc_html__('Field group restrictions', 'press-permit-core')
                                ]
                            ); ?>
                            
                            <?php $this->renderCompatibilityPack(
                                'bbpress_compatibility',
                                esc_html__('bbPress Forums', 'press-permit-core'),
                                esc_html__('Forum-specific permissions for bbPress with detailed control over discussions.', 'press-permit-core'),
                                'bbpress',
                                ['all', 'community'],
                                [
                                    esc_html__('Forum-specific permissions', 'press-permit-core'),
                                    esc_html__('Topic creation restrictions', 'press-permit-core'),
                                    esc_html__('Reply moderation controls', 'press-permit-core')
                                ]
                            ); ?>

                            <?php $this->renderCompatibilityPack(
                                'buddypress_compatibility',
                                esc_html__('BuddyPress', 'press-permit-core'),
                                esc_html__('Assign post and term permissions to BuddyPress groups for community-driven content.', 'press-permit-core'),
                                'buddypress',
                                ['all', 'community'],
                                [
                                    esc_html__('Group-based permissions', 'press-permit-core'),
                                    esc_html__('Activity stream controls', 'press-permit-core'),
                                    esc_html__('Member directory restrictions', 'press-permit-core')
                                ]
                            ); ?>

                            <?php $this->renderCompatibilityPack(
                                'wpml_compatibility',
                                esc_html__('WPML', 'press-permit-core'),
                                esc_html__('Full multilingual support with permission synchronization across translations.', 'press-permit-core'),
                                'wpml',
                                ['all', 'multilingual'],
                                [
                                    esc_html__('Translation permissions', 'press-permit-core'),
                                    esc_html__('Language-specific access', 'press-permit-core'),
                                    esc_html__('Synchronized roles across languages', 'press-permit-core')
                                ]
                            ); ?>

                            <?php $this->renderCompatibilityPack(
                                'yoast_seo_compatibility',
                                esc_html__('Yoast SEO', 'press-permit-core'),
                                esc_html__('Exclude restricted posts from sitemaps and control SEO visibility.', 'press-permit-core'),
                                'yoast',
                                ['all', 'seo'],
                                [
                                    esc_html__('Sitemap filtering', 'press-permit-core'),
                                    esc_html__('SEO meta controls', 'press-permit-core'),
                                    esc_html__('Search engine visibility', 'press-permit-core')
                                ]
                            ); ?>

                            <?php $this->renderCompatibilityPack(
                                'woocommerce_compatibility',
                                esc_html__('WooCommerce', 'press-permit-core'),
                                esc_html__('Advanced permissions for products, orders, and customer data.', 'press-permit-core'),
                                'woocommerce',
                                ['all', 'ecommerce'],
                                [
                                    esc_html__('Product permissions', 'press-permit-core'),
                                    esc_html__('Order management controls', 'press-permit-core'),
                                    esc_html__('Customer data access', 'press-permit-core')
                                ]
                            ); ?>

                            <?php $this->renderCompatibilityPack(
                                'relevanssi_compatibility',
                                esc_html__('Relevanssi', 'press-permit-core'),
                                esc_html__('Filter search results based on View Permissions for secure content discovery.', 'press-permit-core'),
                                'relevanssi',
                                ['all', 'seo'],
                                [
                                    esc_html__('Search result filtering', 'press-permit-core'),
                                    esc_html__('Permission-aware indexing', 'press-permit-core'),
                                    esc_html__('Secure content discovery', 'press-permit-core')
                                ]
                            ); ?>

                            <?php $this->renderCompatibilityPack(
                                'pagebuilders_compatibility',
                                esc_html__('Page Builders', 'press-permit-core'),
                                esc_html__('Compatible with Elementor, Beaver Builder, Divi, and other popular page builders.', 'press-permit-core'),
                                'pagebuilders',
                                ['all', 'builder'],
                                [
                                    esc_html__('Elementor compatibility', 'press-permit-core'),
                                    esc_html__('Beaver Builder support', 'press-permit-core'),
                                    esc_html__('Divi theme integration', 'press-permit-core')
                                ]
                            ); ?>
                        </div>
                        
                        <?php if (!presspermit()->isPro()) : ?>
                            <div class="pp-integrations-upgrade-cta">
                                <div class="pp-upgrade-cta-content">
                                    <h3><?php esc_html_e('Unlock Premium Integrations', 'press-permit-core'); ?></h3>
                                    <p><?php esc_html_e('Upgrade to the Pro version to get access to all these powerful integrations and more. Take your site\'s permissions to the next level with advanced controls and compatibility.', 'press-permit-core'); ?></p>
                                    <a href="<?php echo self::UPGRADE_PRO_URL; ?>" target="_blank" class="pp-upgrade-btn">
                                        <?php esc_html_e('Upgrade to Pro Now', 'press-permit-core'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <script type="text/javascript">
                jQuery(function ($) {
                    // Category filtering
                    $(".pp-category-label").on("click", function () {
                        $(".pp-category-label").removeClass("active");
                        $(this).addClass("active");
                        const category = $(this).data("category");
                        $(".pp-integration-card").each(function () {
                        const categories = ($(this).data("categories") || "all")
                            .toString()
                            .split(",");
                        $(this)
                            .toggle(category === "all" || categories.includes(category))
                            .toggleClass(
                            "pp-hidden",
                            !(category === "all" || categories.includes(category))
                            );
                        });
                    });

                    // Disabled checkbox upgrade message
                    $('.pp-integration-card.pp-disabled input[type="checkbox"]').on(
                        "click",
                        function (e) {
                        e.preventDefault();
                        const card = $(this).closest(".pp-integration-card");
                        card
                            .find(".pp-upgrade-overlay")
                            .css("opacity", "1")
                            .delay(3000)
                            .animate({ opacity: "0" }, 500);
                        if (!card.find(".pp-temp-message").length) {
                            $(
                            '<div class="pp-temp-message" style="position:absolute;top:10px;right:10px;background:#ff5722;color:white;padding:5px 10px;border-radius:3px;font-size:12px;z-index:999;">Pro Feature</div>'
                            )
                            .appendTo(card)
                            .delay(2000)
                            .fadeOut(500, function () {
                                $(this).remove();
                            });
                        }
                        }
                    );

                    // Toggle switch
                    $(".pp-toggle-switch input").on("change", function () {
                        if ($(this).prop("disabled")) return;
                        const status = $(this)
                        .closest(".pp-integration-card")
                        .find(".pp-integration-status");
                        if ($(this).prop("checked")) {
                        status
                            .removeClass("inactive disabled")
                            .addClass("active")
                            .css({ "background-color": "#e8f5e9", color: "#4caf50" })
                            .text("Active");
                        } else {
                        status
                            .removeClass("active")
                            .addClass("inactive")
                            .css({ "background-color": "#ffebee", color: "#f44336" })
                            .text("Inactive");
                        }
                    });

                    // Button hover effect
                    $(".pp-upgrade-btn-primary, .pp-upgrade-btn-secondary").hover(
                        function () {
                            $(this).css("transform", "translateY(-1px)");
                        },
                        function () {
                            $(this).css("transform", "translateY(0)");
                        }
                    );
                });
            </script>
        <?php endif;
    }
    
    private function renderCompatibilityPack($id, $title, $description, $plugin_slug, $categories = ['all'], $features = [])
    {
        $is_pro = presspermit()->isPro();
        $is_checked = $is_pro ? true : false;
        $is_disabled = !$is_pro;
        $card_class = $is_disabled ? 'pp-integration-card pp-disabled' : 'pp-integration-card';
        $icon_class = 'pp-integration-icon ' . $plugin_slug;
        $categories_string = implode(',', $categories);

        // Determine category tag
        $category_tag = '';
        if (in_array('builder', $categories)) {
            $category_tag = '<span class="pp-category-tag pp-tag-builder">' . esc_html__('Builder', 'press-permit-core') . '</span>';
        } elseif (in_array('seo', $categories)) {
            $category_tag = '<span class="pp-category-tag pp-tag-seo">' . esc_html__('SEO', 'press-permit-core') . '</span>';
        } elseif (in_array('ecommerce', $categories)) {
            $category_tag = '<span class="pp-category-tag pp-tag-ecommerce">' . esc_html__('E-Commerce', 'press-permit-core') . '</span>';
        } elseif (in_array('multilingual', $categories)) {
            $category_tag = '<span class="pp-category-tag pp-tag-multilingual">' . esc_html__('Multilingual', 'press-permit-core') . '</span>';
        } elseif (in_array('community', $categories)) {
            $category_tag = '<span class="pp-category-tag pp-tag-community">' . esc_html__('Community', 'press-permit-core') . '</span>';
        }

        $learn_more_urls = [
            'acf_compatibility' => esc_url('https://publishpress.com/knowledge-base/acf-publishpress-permissions/'),
            'bbpress_compatibility' => esc_url('https://publishpress.com/knowledge-base/bbpress-permissions/'),
            'buddypress_compatibility' => esc_url('https://publishpress.com/knowledge-base/buddypress-content-permissions/'),
            'pagebuilders_compatibility' => esc_url('https://publishpress.com/links/permissions-integrations/'),
            'relevanssi_compatibility' => esc_url('https://publishpress.com/knowledge-base/relevanssi-and-presspermit-pro/'),
            'woocommerce_compatibility' => esc_url('https://publishpress.com/knowledge-base/woocommerce-publishpress-permissions/'),
            'wpml_compatibility' => esc_url('https://publishpress.com/knowledge-base/wpml-and-presspermit-pro/'),
            'yoast_seo_compatibility' => esc_url('https://publishpress.com/knowledge-base/publishpress-permissions-yoast-seo/'),
        ];
        ?>
        <div class="<?php echo esc_attr($card_class); ?>" data-categories="<?php echo esc_attr($categories_string); ?>">
            <div class="pp-integration-icon <?php echo esc_attr($plugin_slug); ?>"></div>
            <div class="pp-integration-content">
                <h3 class="pp-integration-title">
                    <?php echo esc_html($title); ?>
                    <?php echo $category_tag; ?>
                    <?php if (!$is_pro) : ?>
                        <span class="pp-pro-badge">Pro</span>
                    <?php else : ?>
                        <span class="pp-pro-badge" style="background: #4caf50;"><?php esc_html_e('Available', 'press-permit-core'); ?></span>
                    <?php endif; ?>
                </h3>
                <p class="pp-integration-description"><?php echo esc_html($description); ?></p>
                
                <?php if (!empty($features)) : ?>
                    <div class="pp-integration-features">
                        <ul>
                            <?php foreach ($features as $feature) : ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="pp-settings-toggle">
                    <label class="pp-toggle-switch">
                        <input type="checkbox" 
                               id="<?php echo esc_attr($id); ?>" 
                               name="<?php echo esc_attr($id); ?>" 
                               value="1" 
                               <?php checked($is_checked); ?>
                               <?php disabled($is_disabled); ?> />
                        <span class="pp-slider"></span>
                    </label>
                    <span class="pp-toggle-label"><?php echo esc_html(sprintf(__('Enable %s Integration', 'press-permit-core'), $title)); ?></span>
                </div>
                
                <?php if ($is_pro) : ?>
                    <div class="pp-integration-status"><?php esc_html_e('Active', 'press-permit-core'); ?></div>
                <?php else : ?>
                    <div class="pp-integration-status disabled"><?php esc_html_e('Disabled', 'press-permit-core'); ?></div>
                <?php endif; ?>
            </div>
            
            <?php if (!$is_pro) : ?>
                <div class="pp-upgrade-overlay">
                    <h4><?php esc_html_e('Premium Feature', 'press-permit-core'); ?></h4>
                    <p><?php echo esc_html(sprintf(__('Unlock %s integration to enhance your permissions system.', 'press-permit-core'), $title)); ?></p>
                    <div class="pp-upgrade-buttons">
                        <a href="<?php echo esc_url($learn_more_urls[$id]); ?>" 
                           target="_blank" 
                           class="pp-upgrade-btn-secondary">
                            <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                        </a>
                        <a href="<?php echo esc_url(self::UPGRADE_PRO_URL); ?>" 
                           target="_blank" 
                           class="pp-upgrade-btn-primary">
                            <?php esc_html_e('Upgrade Now', 'press-permit-core'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
