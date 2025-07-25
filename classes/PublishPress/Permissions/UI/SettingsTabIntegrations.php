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
        if (!empty($ui->form_options[$tab][$section])): ?>
            <tr>
                <td>
                    <div class="pp-integrations-container">
                        <!-- Pro Banner -->
                        <?php if (presspermit()->isPro()): ?>
                            <div class="pp-pro-banner">
                                <div>
                                    <h2><?php esc_html_e('Premium Integrations Active', 'press-permit-core'); ?></h2>
                                    <p><?php esc_html_e('You\'re using the Pro version with access to all premium features', 'press-permit-core'); ?>
                                    </p>
                                </div>
                                <div class="pp-pro-badge-banner"><?php esc_html_e('PRO VERSION', 'press-permit-core'); ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Category Filters -->
                        <div class="pp-category-labels">
                            <div class="pp-category-label active" data-category="all">
                                <?php esc_html_e('All', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="authors">
                                <?php esc_html_e('Authors', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="builder">
                                <?php esc_html_e('Builders', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="community">
                                <?php esc_html_e('Community', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="ecommerce">
                                <?php esc_html_e('E-Commerce', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="events">
                                <?php esc_html_e('Events', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="multilingual">
                                <?php esc_html_e('Multilingual', 'press-permit-core'); ?>
                            </div>
                            <div class="pp-category-label" data-category="seo"><?php esc_html_e('SEO', 'press-permit-core'); ?>
                            </div>
                        </div>

                        <div class="pp-integrations-grid">
                            <?php
                            // Get integrations from registry
                            if (class_exists('\\PublishPress\\Permissions\\CompatibilityRegistry')) {
                                $integrations = \PublishPress\Permissions\CompatibilityRegistry::getIntegrations();
                                foreach ($integrations as $integration) {
                                    $this->renderCompatibilityPackFromRegistry($integration);
                                }
                            } else {
                                // Fallback to hardcoded integrations if registry not available
                                $this->renderFallbackIntegrations();
                            }
                            ?>
                        </div>

                        <?php if (!presspermit()->isPro()): ?>
                            <div class="pp-integrations-upgrade-cta">
                                <div class="pp-upgrade-cta-content">
                                    <h3><?php esc_html_e('Unlock Premium Integrations', 'press-permit-core'); ?></h3>
                                    <p><?php esc_html_e('Upgrade to the Pro version to get access to all these powerful integrations and more. Take your site\'s permissions to the next level with advanced controls and compatibility.', 'press-permit-core'); ?>
                                    </p>
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
                            if (category === "all" || categories.includes(category)) {
                                $(this).removeClass("pp-hidden");
                            } else {
                                $(this).addClass("pp-hidden");
                            }
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

                    $('.pp-toggle-switch input').on('change', function () {
                        if ($(this).prop('disabled')) return;

                        const $toggle = $(this);
                        const integrationId = $toggle.attr('id');
                        const enabled = $toggle.prop('checked');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'pp_toggle_integration',
                                integration_id: integrationId,
                                enabled: enabled ? 1 : 0,
                                nonce: '<?php echo wp_create_nonce('pp_toggle_integration'); ?>'
                            },
                            success: function (response) {
                                if (response.success) {
                                    const status = $toggle.closest('.pp-integration-card').find('.pp-integration-status');
                                    if (enabled) {
                                        status.removeClass('inactive disabled').addClass('active')
                                            .css({ 'background-color': '#e8f5e9', 'color': '#4caf50' })
                                            .text('<?php esc_html_e('Active', 'press-permit-core'); ?>');
                                    } else {
                                        status.removeClass('active').addClass('inactive')
                                            .css({ 'background-color': '#ffebee', 'color': '#f44336' })
                                            .text('<?php esc_html_e('Inactive', 'press-permit-core'); ?>');
                                    }
                                }
                            },
                            error: function () {
                                // Revert toggle on error
                                $toggle.prop('checked', !enabled);
                            }
                        });
                    });
                });
            </script>
        <?php endif;
    }

    private function renderCompatibilityPack($id, $title, $description, $plugin_slug, $categories = ['all'], $features = [], $is_enabled = false, $is_disabled = false, $learn_more_url = '')
    {
        $is_pro = presspermit()->isPro();
        $is_checked = $is_enabled;
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

        // Use provided learn_more_url or fallback to default URLs
        if (empty($learn_more_url)) {
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
            $learn_more_url = isset($learn_more_urls[$id]) ? $learn_more_urls[$id] : '';
        }
        ?>
        <div class="<?php echo esc_attr($card_class); ?>" data-categories="<?php echo esc_attr($categories_string); ?>">
            <div class="pp-integration-icon <?php echo esc_attr($plugin_slug); ?>"></div>
            <div class="pp-integration-content">
                <h3 class="pp-integration-title">
                    <?php echo esc_html($title); ?>
                    <?php echo $category_tag; ?>
                    <?php if (!$is_pro): ?>
                        <span class="pp-pro-badge">Pro</span>
                    <?php else: ?>
                        <?php if ($is_disabled): ?>
                            <span class="pp-pro-badge"
                                style="background: #9e9e9e;"><?php esc_html_e('Unavailable', 'press-permit-core'); ?></span>
                        <?php else: ?>
                            <span class="pp-pro-badge"
                                style="background: #4caf50;"><?php esc_html_e('Available', 'press-permit-core'); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </h3>
                <p class="pp-integration-description"><?php echo esc_html($description); ?></p>

                <?php if (!empty($features)): ?>
                    <div class="pp-integration-features">
                        <ul>
                            <?php foreach ($features as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="pp-settings-toggle">
                    <label class="pp-toggle-switch">
                        <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" value="1"
                            <?php checked($is_checked); ?>         <?php disabled($is_disabled); ?> />
                        <span class="pp-slider"></span>
                    </label>
                    <span
                        class="pp-toggle-label"><?php echo esc_html(sprintf(__('Enable %s Integration', 'press-permit-core'), $title)); ?></span>
                </div>

                <?php if ($is_enabled && $is_pro): ?>
                    <div class="pp-integration-status active"><?php esc_html_e('Active', 'press-permit-core'); ?></div>
                <?php elseif ($is_pro): ?>
                    <div class="pp-integration-status inactive"><?php esc_html_e('Inactive', 'press-permit-core'); ?></div>
                <?php else: ?>
                    <div class="pp-integration-status disabled"><?php esc_html_e('Disabled', 'press-permit-core'); ?></div>
                <?php endif; ?>
            </div>

            <?php if (!$is_pro): ?>
                <div class="pp-upgrade-overlay">
                    <h4><?php esc_html_e('Premium Feature', 'press-permit-core'); ?></h4>
                    <p><?php echo esc_html(sprintf(__('Unlock %s integration to enhance your permissions system.', 'press-permit-core'), $title)); ?>
                    </p>
                    <div class="pp-upgrade-buttons">
                        <?php if (!empty($learn_more_url)): ?>
                            <a href="<?php echo esc_url($learn_more_url); ?>" target="_blank" class="pp-upgrade-btn-secondary">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(self::UPGRADE_PRO_URL); ?>" target="_blank" class="pp-upgrade-btn-primary">
                            <?php esc_html_e('Upgrade Now', 'press-permit-core'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderCompatibilityPackFromRegistry($integration)
    {
        $is_pro = presspermit()->isPro();
        $is_available = \PublishPress\Permissions\CompatibilityRegistry::isAvailable($integration['id']);
        $is_enabled = \PublishPress\Permissions\CompatibilityRegistry::isEnabled($integration['id']);
        $is_disabled = !$is_pro || !$is_available;

        $this->renderCompatibilityPack(
            $integration['id'],
            $integration['title'],
            $integration['description'],
            $integration['icon_class'],
            $integration['categories'],
            $integration['features'],
            $is_enabled,
            $is_disabled,
            $integration['learn_more_url']
        );
    }

    private function renderFallbackIntegrations()
    {
        // Fallback integrations when CompatibilityRegistry is not available
        $fallback_integrations = [
            [
                'id' => 'acf_compatibility',
                'title' => esc_html__('Advanced Custom Fields', 'press-permit-core'),
                'description' => esc_html__('Full compatibility with ACF field groups and taxonomies for granular permission control.', 'press-permit-core'),
                'icon_class' => 'acf',
                'categories' => ['all'],
                'features' => [
                    esc_html__('Control access to custom fields', 'press-permit-core'),
                    esc_html__('Taxonomy-based permissions', 'press-permit-core'),
                    esc_html__('Field group restrictions', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('acf'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/acf-publishpress-permissions/'
            ],
            [
                'id' => 'bbpress_compatibility',
                'title' => esc_html__('bbPress Forums', 'press-permit-core'),
                'description' => esc_html__('Forum-specific permissions for bbPress with detailed control over discussions.', 'press-permit-core'),
                'icon_class' => 'bbpress',
                'categories' => ['all', 'community'],
                'features' => [
                    esc_html__('Forum-specific permissions', 'press-permit-core'),
                    esc_html__('Topic creation restrictions', 'press-permit-core'),
                    esc_html__('Reply moderation controls', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('bbp_get_version'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/bbpress-permissions/'
            ],
            [
                'id' => 'buddypress_compatibility',
                'title' => esc_html__('BuddyPress', 'press-permit-core'),
                'description' => esc_html__('Assign post and term permissions to BuddyPress groups for community-driven content.', 'press-permit-core'),
                'icon_class' => 'buddypress',
                'categories' => ['all', 'community'],
                'features' => [
                    esc_html__('Group-based permissions', 'press-permit-core'),
                    esc_html__('Activity stream controls', 'press-permit-core'),
                    esc_html__('Member directory restrictions', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('buddypress'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/buddypress-content-permissions/'
            ],
            [
                'id' => 'coauthors_compatibility',
                'title' => esc_html__('Co-Authors Plus', 'press-permit-core'),
                'description' => esc_html__('Support for multiple authors per post with permission controls.', 'press-permit-core'),
                'icon_class' => 'coauthors',
                'categories' => ['all', 'authors'],
                'features' => [
                    esc_html__('Multi-author support', 'press-permit-core'),
                    esc_html__('Author permission controls', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => defined('COAUTHORS_PLUS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/links/permissions-integrations/'
            ],
            [
                'id' => 'events_calendar_compatibility',
                'title' => esc_html__('The Events Calendar', 'press-permit-core'),
                'description' => esc_html__('Event permissions and calendar access controls.', 'press-permit-core'),
                'icon_class' => 'events-calendar',
                'categories' => ['all', 'events'],
                'features' => [
                    esc_html__('Event permissions', 'press-permit-core'),
                    esc_html__('Calendar access controls', 'press-permit-core'),
                ],
                'enabled' => false,
                'available' => defined('EVENTS_CALENDAR_PRO_FILE') || class_exists('Tribe__Events__Pro__Main'),
                'learn_more_url' => 'https://publishpress.com/links/permissions-integrations/'
            ],
            [
                'id' => 'pagebuilders_compatibility',
                'title' => esc_html__('Page Builders', 'press-permit-core'),
                'description' => esc_html__('Compatible with Elementor, Beaver Builder, Divi, and other popular page builders.', 'press-permit-core'),
                'icon_class' => 'pagebuilders',
                'categories' => ['all', 'builder'],
                'features' => [
                    esc_html__('Elementor compatibility', 'press-permit-core'),
                    esc_html__('Beaver Builder support', 'press-permit-core'),
                    esc_html__('Divi theme integration', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => (
                    defined('ELEMENTOR_VERSION') ||
                    class_exists('FLBuilder') ||
                    function_exists('et_divi_load_scripts_styles')
                ),
                'learn_more_url' => 'https://publishpress.com/links/permissions-integrations/'
            ],
            [
                'id' => 'peepso_compatibility',
                'title' => esc_html__('PeepSo', 'presspermit-pro'),
                'description' => esc_html__('Social network permissions and community controls.', 'presspermit-pro'),
                'icon_class' => 'peepso',
                'categories' => ['all', 'community'],
                'features' => [
                    esc_html__('Social network permissions', 'presspermit-pro'),
                    esc_html__('Community access controls', 'presspermit-pro'),
                ],
                'enabled' => false,
                'available' => class_exists('PeepSo'),
                'learn_more_url' => 'https://publishpress.com/links/permissions-integrations/'
            ],
            [
                'id' => 'relevanssi_compatibility',
                'title' => esc_html__('Relevanssi', 'press-permit-core'),
                'description' => esc_html__('Filter search results based on View Permissions for secure content discovery.', 'press-permit-core'),
                'icon_class' => 'relevanssi',
                'categories' => ['all', 'seo'],
                'features' => [
                    esc_html__('Search result filtering', 'press-permit-core'),
                    esc_html__('Permission-aware indexing', 'press-permit-core'),
                    esc_html__('Secure content discovery', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => function_exists('relevanssi_init'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/relevanssi-and-presspermit-pro/'
            ],
            [
                'id' => 'woocommerce_compatibility',
                'title' => esc_html__('WooCommerce', 'press-permit-core'),
                'description' => esc_html__('Advanced permissions for products, orders, and customer data.', 'press-permit-core'),
                'icon_class' => 'woocommerce',
                'categories' => ['all', 'ecommerce'],
                'features' => [
                    esc_html__('Product permissions', 'press-permit-core'),
                    esc_html__('Order management controls', 'press-permit-core'),
                    esc_html__('Customer data access', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => class_exists('WooCommerce'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/woocommerce-publishpress-permissions/'
            ],
            [
                'id' => 'wpml_compatibility',
                'title' => esc_html__('WPML', 'press-permit-core'),
                'description' => esc_html__('Full multilingual support with permission synchronization across translations.', 'press-permit-core'),
                'icon_class' => 'wpml',
                'categories' => ['all', 'multilingual'],
                'features' => [
                    esc_html__('Translation permissions', 'press-permit-core'),
                    esc_html__('Language-specific access', 'press-permit-core'),
                    esc_html__('Synchronized roles across languages', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => defined('ICL_SITEPRESS_VERSION'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/wpml-and-presspermit-pro/'
            ],
            [
                'id' => 'yoast_seo_compatibility',
                'title' => esc_html__('Yoast SEO', 'press-permit-core'),
                'description' => esc_html__('Exclude restricted posts from sitemaps and control SEO visibility.', 'press-permit-core'),
                'icon_class' => 'yoast',
                'categories' => ['all', 'seo'],
                'features' => [
                    esc_html__('Sitemap filtering', 'press-permit-core'),
                    esc_html__('SEO meta controls', 'press-permit-core'),
                    esc_html__('Search engine visibility', 'press-permit-core')
                ],
                'enabled' => false,
                'available' => defined('WPSEO_VERSION'),
                'learn_more_url' => 'https://publishpress.com/knowledge-base/publishpress-permissions-yoast-seo/'
            ],
        ];

        // Get enabled integrations from options (fallback method)
        $enabled_integrations = get_option('presspermit_enabled_integrations', []);

        // Render each fallback integration
        foreach ($fallback_integrations as $integration) {
            $is_pro = presspermit()->isPro();
            $is_available = $integration['available'];
            $is_enabled = isset($enabled_integrations[$integration['id']]) && $enabled_integrations[$integration['id']];
            $is_disabled = !$is_pro || !$is_available;

            $this->renderCompatibilityPack(
                $integration['id'],
                $integration['title'],
                $integration['description'],
                $integration['icon_class'],
                $integration['categories'],
                $integration['features'],
                $is_enabled,
                $is_disabled,
                $integration['learn_more_url']
            );
        }
    }
}
