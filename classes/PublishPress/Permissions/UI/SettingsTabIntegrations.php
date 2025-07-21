<?php

namespace PublishPress\Permissions\UI;

class SettingsTabIntegrations
{
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
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'compatibility_packs' => ['acf_compatibility', 'bbpress_compatibility'],
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
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <div class="pp-integrations-container">
                        <div class="pp-integrations-grid">
                            <?php $this->renderCompatibilityPack(
                                'acf_compatibility',
                                esc_html__('Advanced Custom Fields', 'press-permit-core'),
                                esc_html__('Compatibility with field groups, taxonomies', 'press-permit-core'),
                                'acf',
                                [
                                    esc_html__('Control access to custom fields', 'press-permit-core'),
                                    esc_html__('Taxonomy-based permissions', 'press-permit-core'),
                                    esc_html__('Field group restrictions', 'press-permit-core')
                                ]
                            ); ?>
                            
                            <?php $this->renderCompatibilityPack(
                                'bbpress_compatibility',
                                esc_html__('bbPress Forums', 'press-permit-core'),
                                esc_html__('Forum-specific permissions', 'press-permit-core'),
                                'bbpress',
                                [
                                    esc_html__('Forum-specific permissions', 'press-permit-core'),
                                    esc_html__('Topic creation restrictions', 'press-permit-core'),
                                    esc_html__('Reply moderation controls', 'press-permit-core')
                                ]
                            ); ?>
                        </div>
                        
                        <?php if (!presspermit()->isPro()) : ?>
                            <div class="pp-integrations-upgrade-cta">
                                <div class="pp-upgrade-cta-content">
                                    <h3><?php esc_html_e('Unlock Premium Integrations', 'press-permit-core'); ?></h3>
                                    <p><?php esc_html_e('Upgrade to the Pro version to get access to all these powerful integrations and more. Take your site\'s permissions to the next level with advanced controls and compatibility.', 'press-permit-core'); ?></p>
                                    <a href="https://publishpress.com/links/permissions-integrations" target="_blank" class="pp-upgrade-btn">
                                        <?php esc_html_e('Upgrade to Pro Now', 'press-permit-core'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            
            <style>
                .pp-integrations-container {
                    max-width: 100%;
                    margin: 0;
                }

                .pp-integrations-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }

                .pp-integration-card {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                    padding: 20px;
                    position: relative;
                    overflow: hidden;
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                    border: 1px solid #e0e0e0;
                }

                .pp-integration-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                }

                .pp-integration-card:hover .pp-upgrade-overlay {
                    opacity: 1;
                    pointer-events: auto;
                }

                .pp-integration-header {
                    display: flex;
                    align-items: flex-start;
                    margin-bottom: 15px;
                }

                .pp-integration-icon {
                    width: 48px;
                    height: 48px;
                    background: #f5f5f5;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                    flex-shrink: 0;
                    position: relative;
                }

                .pp-integration-icon::before {
                    font-size: 24px;
                    color: #757575;
                }

                .pp-integration-icon.acf::before {
                    content: "�";
                }

                .pp-integration-icon.bbpress::before {
                    content: "💬";
                }

                .pp-integration-icon.default::before {
                    content: "🔌";
                }

                .pp-integration-content {
                    flex: 1;
                }

                .pp-integration-title {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #1e1e1e;
                    display: flex;
                    align-items: center;
                }

                .pp-pro-badge {
                    background: #9c27b0;
                    color: white;
                    font-size: 11px;
                    padding: 3px 8px;
                    border-radius: 4px;
                    margin-left: 10px;
                    font-weight: 600;
                    text-transform: uppercase;
                }

                .pp-integration-description {
                    color: #757575;
                    font-size: 14px;
                    margin-bottom: 15px;
                    line-height: 1.5;
                }

                .pp-integration-features {
                    font-size: 13px;
                    color: #757575;
                }

                .pp-integration-features ul {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .pp-integration-features li {
                    position: relative;
                    padding-left: 20px;
                    margin-bottom: 8px;
                }

                .pp-integration-features li:before {
                    content: "•";
                    position: absolute;
                    left: 0;
                    color: #3858e9;
                    font-weight: bold;
                }

                .pp-upgrade-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.95);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    padding: 20px;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    pointer-events: none;
                    backdrop-filter: blur(2px);
                }

                .pp-upgrade-overlay h4 {
                    color: #ff5722;
                    font-weight: 600;
                    margin-bottom: 10px;
                    font-size: 16px;
                }

                .pp-upgrade-overlay p {
                    color: #757575;
                    margin-bottom: 20px;
                    max-width: 250px;
                    line-height: 1.4;
                }

                .pp-upgrade-overlay .pp-upgrade-buttons {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    align-items: center;
                }

                .pp-upgrade-btn-secondary {
                    background: transparent;
                    border: 1px solid #9c27b0;
                    color: #9c27b0;
                    padding: 8px 16px;
                    border-radius: 4px;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 13px;
                    transition: all 0.2s ease;
                    display: inline-block;
                }

                .pp-upgrade-btn-secondary:hover {
                    background: #f3e5f5;
                    text-decoration: none;
                }

                .pp-upgrade-btn-primary {
                    background: #9c27b0;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 4px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.2s ease;
                    display: inline-block;
                }

                .pp-upgrade-btn-primary:hover {
                    background: #7b1fa2;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                    text-decoration: none;
                    color: white;
                }

                .pp-integrations-upgrade-cta {
                    background: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%);
                    border-radius: 8px;
                    padding: 30px;
                    text-align: center;
                    margin-top: 30px;
                    color: white;
                }

                .pp-integrations-upgrade-cta h3 {
                    font-size: 24px;
                    margin-bottom: 15px;
                    font-weight: 600;
                }

                .pp-integrations-upgrade-cta p {
                    max-width: 600px;
                    margin: 0 auto 25px;
                    opacity: 0.9;
                    line-height: 1.6;
                }

                .pp-integrations-upgrade-cta .pp-upgrade-btn {
                    background: white;
                    color: #9c27b0;
                    padding: 12px 30px;
                    border-radius: 4px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 16px;
                    transition: all 0.2s ease;
                    display: inline-block;
                }

                .pp-integrations-upgrade-cta .pp-upgrade-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    text-decoration: none;
                    color: #9c27b0;
                }

                .pp-integration-card.pp-disabled {
                    opacity: 0.7;
                }

                .pp-integration-checkbox {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    z-index: 1;
                }

                .pp-integration-checkbox input[type="checkbox"] {
                    width: 18px;
                    height: 18px;
                    margin: 0;
                }

                .pp-integration-checkbox input[type="checkbox"]:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                @media (max-width: 768px) {
                    .pp-integrations-grid {
                        grid-template-columns: 1fr;
                    }

                    .pp-integration-header {
                        flex-direction: column;
                        text-align: center;
                    }

                    .pp-integration-icon {
                        margin-right: 0;
                        margin-bottom: 15px;
                    }

                    .pp-integrations-upgrade-cta {
                        padding: 20px;
                    }

                    .pp-integrations-upgrade-cta h3 {
                        font-size: 20px;
                    }
                }
            </style>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Add click handler for disabled checkboxes to show upgrade message
                    $('.pp-integration-card.pp-disabled input[type="checkbox"]').on('click', function(e) {
                        e.preventDefault();
                        var card = $(this).closest('.pp-integration-card');
                        var overlay = card.find('.pp-upgrade-overlay');
                        
                        // Flash the overlay to get user attention
                        overlay.css('opacity', '1').delay(3000).animate({opacity: '0'}, 500);
                        
                        // Show a temporary message
                        if (!card.find('.pp-temp-message').length) {
                            var message = $('<div class="pp-temp-message" style="position:absolute;top:10px;right:10px;background:#ff5722;color:white;padding:5px 10px;border-radius:3px;font-size:12px;z-index:999;">Pro Feature</div>');
                            card.append(message);
                            setTimeout(function() {
                                message.fadeOut(500, function() {
                                    message.remove();
                                });
                            }, 2000);
                        }
                    });
                    
                    // Add smooth hover effects for upgrade buttons
                    $('.pp-upgrade-btn-primary, .pp-upgrade-btn-secondary').hover(
                        function() {
                            $(this).css('transform', 'translateY(-1px)');
                        },
                        function() {
                            $(this).css('transform', 'translateY(0)');
                        }
                    );
                });
            </script>
        <?php endif;
    }
    
    private function renderCompatibilityPack($id, $title, $description, $plugin_slug, $features = [])
    {
        $is_pro = presspermit()->isPro();
        $is_checked = $is_pro ? true : false;
        $is_disabled = !$is_pro;
        $card_class = $is_disabled ? 'pp-integration-card pp-disabled' : 'pp-integration-card';
        $icon_class = 'pp-integration-icon ' . $plugin_slug;
        ?>
        <div class="<?php echo esc_attr($card_class); ?>">
            <div class="pp-integration-checkbox">
                <input type="checkbox" 
                       id="<?php echo esc_attr($id); ?>" 
                       name="<?php echo esc_attr($id); ?>" 
                       value="1" 
                       <?php checked($is_checked); ?>
                       <?php disabled($is_disabled); ?> />
            </div>
            
            <div class="pp-integration-header">
                <div class="<?php echo esc_attr($icon_class); ?>"></div>
                <div class="pp-integration-content">
                    <h3 class="pp-integration-title">
                        <?php echo esc_html($title); ?>
                        <?php if (!$is_pro) : ?>
                            <span class="pp-pro-badge">Pro</span>
                        <?php else : ?>
                            <span class="pp-pro-badge" style="background: #4caf50;"><?php esc_html_e('Available', 'press-permit-core'); ?></span>
                        <?php endif; ?>
                    </h3>
                    <p class="pp-integration-description"><?php echo esc_html($description); ?></p>
                </div>
            </div>
            
            <?php if (!empty($features)) : ?>
                <div class="pp-integration-features">
                    <ul>
                        <?php foreach ($features as $feature) : ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!$is_pro) : ?>
                <div class="pp-upgrade-overlay">
                    <h4><?php esc_html_e('Premium Feature', 'press-permit-core'); ?></h4>
                    <p><?php echo esc_html(sprintf(__('Unlock %s integration to enhance your permissions system.', 'press-permit-core'), $title)); ?></p>
                    <div class="pp-upgrade-buttons">
                        <a href="https://publishpress.com/links/permissions-integrations" 
                           target="_blank" 
                           class="pp-upgrade-btn-secondary">
                            <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                        </a>
                        <a href="https://publishpress.com/links/permissions-integrations" 
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
