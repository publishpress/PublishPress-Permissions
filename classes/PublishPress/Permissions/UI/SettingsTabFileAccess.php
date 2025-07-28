<?php

namespace PublishPress\Permissions\UI;

/**
 * Settings Tab: File Access
 * 
 * This tab demonstrates how to add configurable badges to tab titles.
 * 
 * HOW TO ADD BADGES TO OTHER TABS:
 * ================================
 * 
 * 1. In your tab class constructor, add the badge filter:
 *    add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
 * 
 * 2. Create the optionTabBadges method using the helper:
 *    public function optionTabBadges($badges) {
 *        $badges['your_tab_key'] = SettingsTabFileAccess::createTabBadge('pro');
 *        return $badges;
 *    }
 * 
 * 3. Or create manually:
 *    public function optionTabBadges($badges) {
 *        $badges['your_tab_key'] = [
 *            'text' => 'PRO',           // Badge text
 *            'color' => 'white',        // Text color (optional)
 *            'bg_color' => '#8B5CF6',   // Background color (optional)
 *            'class' => 'your-class'    // CSS class (optional)
 *        ];
 *        return $badges;
 *    }
 * 
 * BADGE HELPER EXAMPLES:
 * =====================
 * createTabBadge('pro')                    // Purple PRO badge
 * createTabBadge('new')                    // Green NEW badge
 * createTabBadge('beta')                   // Orange BETA badge
 * createTabBadge('hot')                    // Red HOT badge
 * createTabBadge('premium')                // Gold PREMIUM badge
 * createTabBadge('pro', 'PLUS')            // Purple PLUS badge
 * createTabBadge('new', '', '#FF0000')     // Red NEW badge
 * 
 * MANUAL BADGE EXAMPLES:
 * =====================
 * PRO Badge (Purple):   ['text' => 'PRO', 'bg_color' => '#8B5CF6']
 * NEW Badge (Green):    ['text' => 'NEW', 'bg_color' => '#10B981']
 * BETA Badge (Orange):  ['text' => 'BETA', 'bg_color' => '#F59E0B']
 * HOT Badge (Red):      ['text' => 'HOT', 'bg_color' => '#EF4444']
 * PREMIUM Badge (Gold): ['text' => 'PREMIUM', 'bg_color' => '#D97706']
 * 
 * EXAMPLE: Adding a badge to SettingsTabModules.php
 * ================================================
 * 1. Add filter in constructor:
 *    add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
 * 
 * 2. Add method:
 *    public function optionTabBadges($badges) {
 *        $badges['modules'] = SettingsTabFileAccess::createTabBadge('new');
 *        return $badges;
 *    }
 * 
 * That's it! The Modules tab will now show a green "NEW" badge.
 */
class SettingsTabFileAccess
{
    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 5);
        add_filter('presspermit_option_tab_badges', [$this, 'optionTabBadges'], 5);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_file_access_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['file_access'] = esc_html__('File Access', 'press-permit-core');
        return $tabs;
    }

    public function optionTabBadges($badges)
    {
        // Add PRO badge to File Access tab using the helper method
        // This is configurable - you can customize:
        // - Use helper: self::createTabBadge('pro') or self::createTabBadge('new')
        // - Custom text: self::createTabBadge('pro', 'CUSTOM')
        // - Custom color: self::createTabBadge('pro', '', '#FF0000')
        // - Manual: ['text' => 'TEXT', 'bg_color' => '#COLOR', 'class' => 'css-class']
        //
        // Examples:
        // $badges['my_tab'] = self::createTabBadge('new');           // Green NEW badge
        // $badges['my_tab'] = self::createTabBadge('beta');          // Orange BETA badge  
        // $badges['my_tab'] = self::createTabBadge('hot');           // Red HOT badge
        // $badges['my_tab'] = self::createTabBadge('pro', 'PLUS');   // Purple PLUS badge
        // $badges['my_tab'] = self::createTabBadge('new', '', '#FF0000'); // Red NEW badge
        
        $badges['file_access'] = self::createTabBadge('pro');
        return $badges;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'file_access' => esc_html__('File Access', 'press-permit-core'),
        ];

        $key = 'file_access';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'unattached_files_private' => esc_html__('Make Unattached Files Private', 'press-permit-core'),
            'small_thumbnails_unfiltered' => esc_html__('Small Thumbnails Unfiltered', 'press-permit-core'),
            'file_access_apply_redirect' => esc_html__('Compatibility Mode: Apply extra redirect', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'file_access' => ['unattached_files_private', 'small_thumbnails_unfiltered', 'file_access_apply_redirect'],
        ];

        $key = 'file_access';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();
        $ui = SettingsAdmin::instance();
        $tab = 'file_access';

        $section = 'file_access';
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    // Check if File Access module is available
                    $is_pro = presspermit()->isPro();
                    $file_access_available = defined('PRESSPERMIT_FILE_ACCESS_VERSION') || $pp->moduleActive('file-access');
                    
                    if (!$file_access_available) {
                        // Show promotional content for free version
                        $this->renderProPromo();
                    } else {
                        // Show actual file access settings for pro version
                        $this->renderFileAccessSettings($ui, $tab, $section);
                    }
                    ?>
                </td>
            </tr>
        <?php endif;
    }

    private function renderProPromo()
    {
        ?>
        <div class="pp-file-access-promo" style="--primary-color: #8B5CF6; --primary-hover: #7C3AED; --success-color: #10B981; --warning-color: #F59E0B; --danger-color: #EF4444; --border-color: #E5E7EB; --text-color: #374151; --bg-hover: #F9FAFB;">
            <!-- Feature Cards Grid -->
            <div class="pp-feature-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
                
                <!-- Core Protection Card -->
                <div class="pp-feature-card pp-feature-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;">
                    <div class="pp-feature-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div class="pp-feature-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">🛡️</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Core File Protection', 'press-permit-core'); ?></h4>
                    </div>
                    <ul style="margin: 0; padding: 0; list-style: none; color: #6B7280; font-size: 14px; line-height: 1.6;">
                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Block direct access based on post permissions', 'press-permit-core'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Automatic .htaccess file management', 'press-permit-core'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('File Access Reset Key generation', 'press-permit-core'); ?>
                        </li>
                    </ul>
                    
                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.95); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; backdrop-filter: blur(2px);">
                        <h4 style="color: var(--primary-color); font-weight: 600; margin: 0 auto 10px auto; font-size: 16px;">🔒 <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p style="color: #6B7280; margin-bottom: 15px !important; max-width: 200px; line-height: 1.4; font-size: 13px;">
                            <?php esc_html_e('Upgrade to Pro to unlock advanced file protection capabilities', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons" style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
                            <a href="https://publishpress.com/links/permissions-file-access" target="_blank" class="pp-upgrade-btn-primary" style="background: var(--primary-color); color: white !important; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px; transition: all 0.2s ease; display: inline-block;">
                                <?php esc_html_e('Upgrade Now', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/file-filtering-nginx/" target="_blank" class="pp-upgrade-btn-secondary" style="background: transparent; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 11px; transition: all 0.2s ease; display: inline-block;">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Performance Card -->
                <div class="pp-feature-card pp-feature-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;">
                    <div class="pp-feature-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div class="pp-feature-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--warning-color), #F97316); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">⚡</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Privacy & Performance', 'press-permit-core'); ?></h4>
                    </div>
                    <ul style="margin: 0; padding: 0; list-style: none; color: #6B7280; font-size: 14px; line-height: 1.6;">
                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Make unattached files private', 'press-permit-core'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Performance optimization for thumbnails', 'press-permit-core'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Compatibility mode with redirects', 'press-permit-core'); ?>
                        </li>
                    </ul>
                    
                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.95); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; backdrop-filter: blur(2px);">
                        <h4 style="color: var(--warning-color); font-weight: 600; margin: 0 auto 10px auto; font-size: 16px;">⚡ <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p style="color: #6B7280; margin-bottom: 15px !important; max-width: 200px; line-height: 1.4; font-size: 13px;">
                            <?php esc_html_e('Optimize your site with advanced privacy and performance controls', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons" style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
                            <a href="https://publishpress.com/links/permissions-file-access" target="_blank" class="pp-upgrade-btn-primary" style="background: var(--warning-color); color: white !important; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px; transition: all 0.2s ease; display: inline-block;">
                                <?php esc_html_e('Upgrade Now', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/file-filtering-nginx/" target="_blank" class="pp-upgrade-btn-secondary" style="background: transparent; border: 1px solid var(--warning-color); color: var(--warning-color); padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 11px; transition: all 0.2s ease; display: inline-block;">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Advanced Integration Card -->
                <div class="pp-feature-card pp-feature-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;">
                    <div class="pp-feature-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div class="pp-feature-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--success-color), #059669); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">🔧</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Advanced Integration', 'press-permit-core'); ?></h4>
                    </div>
                    <ul style="margin: 0; padding: 0; list-style: none; color: #6B7280; font-size: 14px; line-height: 1.6;">
                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Nginx integration support', 'press-permit-core'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('FTP uploaded files protection', 'press-permit-core'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: var(--success-color);">✓</span>
                            <?php esc_html_e('Attachments utility scanner', 'press-permit-core'); ?>
                        </li>
                    </ul>
                    
                    <!-- Upgrade Overlay -->
                    <div class="pp-upgrade-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.95); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; backdrop-filter: blur(2px);">
                        <h4 style="color: var(--success-color); font-weight: 600; margin: 0 auto 10px auto; font-size: 16px;">🔧 <?php esc_html_e('Pro Feature', 'press-permit-core'); ?></h4>
                        <p style="color: #6B7280; margin-bottom: 15px !important; max-width: 200px; line-height: 1.4; font-size: 13px;">
                            <?php esc_html_e('Get advanced integration features with Nginx, FTP, and more', 'press-permit-core'); ?>
                        </p>
                        <div class="pp-upgrade-buttons" style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
                            <a href="https://publishpress.com/links/permissions-file-access" target="_blank" class="pp-upgrade-btn-primary" style="background: var(--success-color); color: white !important; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px; transition: all 0.2s ease; display: inline-block;">
                                <?php esc_html_e('Upgrade Now', 'press-permit-core'); ?>
                            </a>
                            <a href="https://publishpress.com/knowledge-base/file-filtering-nginx/" target="_blank" class="pp-upgrade-btn-secondary" style="background: transparent; border: 1px solid var(--success-color); color: var(--success-color); padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 11px; transition: all 0.2s ease; display: inline-block;">
                                <?php esc_html_e('Learn More', 'press-permit-core'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="pp-cta-section" style="background: linear-gradient(135deg, #F8FAFC, #F1F5F9); border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; text-align: center;">
                <h4 style="margin: 0 0 12px 0; color: var(--text-color); font-size: 18px; font-weight: 600;">
                    <?php esc_html_e('Ready to secure your files?', 'press-permit-core'); ?>
                </h4>
                <p style="margin: 0 0 20px 0; color: #6B7280; font-size: 14px;">
                    <?php esc_html_e('Upgrade to Pro and get advanced file access control with all these features and more.', 'press-permit-core'); ?>
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; align-items: center; flex-wrap: wrap;">
                    <a href="https://publishpress.com/links/permissions-file-access" 
                       class="button button-primary button-large" 
                       target="_blank"
                       style="background: var(--primary-color); border-color: var(--primary-color); padding: 8px 24px; border-radius: 8px; font-weight: 500; transition: all 0.3s ease;">
                        <?php esc_html_e('Upgrade to Pro', 'press-permit-core'); ?>
                    </a>
                    <a href="https://publishpress.com/knowledge-base/file-filtering-nginx/" 
                       target="_blank"
                       style="color: var(--primary-color); text-decoration: none; font-size: 14px; font-weight: 500;">
                        <?php esc_html_e('Learn more →', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>

            <style>
            .pp-feature-card-hover:hover {
                border-color: var(--primary-color) !important;
                box-shadow: 0 8px 25px rgba(139, 92, 246, 0.15) !important;
                transform: translateY(-2px) !important;
            }
            
            .pp-feature-card-hover:hover .pp-feature-icon {
                transform: scale(1.1) !important;
            }

            .pp-feature-card-hover:hover .pp-upgrade-overlay {
                opacity: 1 !important;
                pointer-events: auto !important;
            }

            .pp-upgrade-btn-primary:hover {
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
                text-decoration: none !important;
            }

            .pp-upgrade-btn-secondary:hover {
                background: rgba(139, 92, 246, 0.1) !important;
                text-decoration: none !important;
            }

            .button-primary:hover {
                background: var(--primary-hover) !important;
                border-color: var(--primary-hover) !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25) !important;
            }
            </style>
        </div>
        <?php
    }

    private function renderFileAccessSettings($ui, $tab, $section)
    {
        ?>
        <div class="pp-file-access-settings" style="--primary-color: #8B5CF6; --primary-hover: #7C3AED; --success-color: #10B981; --warning-color: #F59E0B; --danger-color: #EF4444; --border-color: #E5E7EB; --text-color: #374151; --bg-hover: #F9FAFB;">
            
            <!-- Header Info -->
            <div class="pp-settings-header" style="background: linear-gradient(135deg, #F8FAFC, #F1F5F9); border: 2px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <span style="font-size: 24px;">🔒</span>
                    <h3 style="margin: 0; color: var(--text-color); font-size: 18px; font-weight: 600;">
                        <?php esc_html_e('File Access Configuration', 'press-permit-core'); ?>
                    </h3>
                </div>
                <p style="margin: 0; color: #6B7280; font-size: 14px; line-height: 1.6;">
                    <?php esc_html_e('This feature will control direct URL access to files in /uploads/ folder. By default, files are blocked if they are attached to a post which the user can\'t view.', 'press-permit-core'); ?>
                </p>
                
                <?php
                // Check if mod_rewrite is enabled
                if (!apache_mod_loaded('mod_rewrite') && !isset($_SERVER['HTTP_MOD_REWRITE'])) : ?>
                    <div style="margin-top: 12px; padding: 12px; background: #FEF3CD; border: 1px solid #F59E0B; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                        <span style="color: var(--warning-color); font-size: 16px;">⚠️</span>
                        <span style="color: #92400E; font-size: 14px; font-weight: 500;">
                            <?php esc_html_e('Note: Direct access to uploaded file attachments cannot be filtered because mod_rewrite is not enabled on your server.', 'press-permit-core'); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Settings Cards Grid -->
            <div class="pp-settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                
                <!-- Privacy Settings Card -->
                <div class="pp-setting-card pp-setting-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease;">
                    <div class="pp-setting-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                        <div class="pp-setting-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">🔐</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Privacy Controls', 'press-permit-core'); ?></h4>
                    </div>
                    
                    <?php if (in_array('unattached_files_private', $ui->form_options[$tab][$section], true)) : ?>
                        <div class="pp-toggle-setting" style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label for="unattached_files_private" style="color: var(--text-color); font-weight: 500; font-size: 14px; cursor: pointer;">
                                    <?php esc_html_e('Make Unattached Files Private', 'press-permit-core'); ?>
                                </label>
                                <div class="pp-toggle-switch">
                                    <?php $ui->optionCheckbox('unattached_files_private', $tab, $section, '', '', ['class' => 'pp-toggle-input']); ?>
                                </div>
                            </div>
                            <p style="margin: 0; color: #6B7280; font-size: 13px; line-height: 1.5;">
                                <?php esc_html_e('This extends the File Access feature to files that are not attached to any post. This will not apply to user who have the edit_private_files or pp_list_all_files capability.', 'press-permit-core'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Performance Settings Card -->
                <div class="pp-setting-card pp-setting-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease;">
                    <div class="pp-setting-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                        <div class="pp-setting-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--success-color), #059669); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">⚡</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Performance Options', 'press-permit-core'); ?></h4>
                    </div>
                    
                    <?php if (in_array('small_thumbnails_unfiltered', $ui->form_options[$tab][$section], true)) : ?>
                        <div class="pp-toggle-setting" style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label for="small_thumbnails_unfiltered" style="color: var(--text-color); font-weight: 500; font-size: 14px; cursor: pointer;">
                                    <?php esc_html_e('Small Thumbnails Unfiltered', 'press-permit-core'); ?>
                                </label>
                                <div class="pp-toggle-switch">
                                    <?php $ui->optionCheckbox('small_thumbnails_unfiltered', $tab, $section, '', '', ['class' => 'pp-toggle-input']); ?>
                                </div>
                            </div>
                            <p style="margin: 0; color: #6B7280; font-size: 13px; line-height: 1.5;">
                                <?php esc_html_e('Improve Media Library performance by disabling file filtering for thumbnails (size specified in Settings > Media).', 'press-permit-core'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('file_access_apply_redirect', $ui->form_options[$tab][$section], true)) : ?>
                        <div class="pp-toggle-setting">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label for="file_access_apply_redirect" style="color: var(--text-color); font-weight: 500; font-size: 14px; cursor: pointer;">
                                    <?php esc_html_e('Compatibility Mode', 'press-permit-core'); ?>
                                </label>
                                <div class="pp-toggle-switch">
                                    <?php $ui->optionCheckbox('file_access_apply_redirect', $tab, $section, '', '', ['class' => 'pp-toggle-input']); ?>
                                </div>
                            </div>
                            <p style="margin: 0; color: #6B7280; font-size: 13px; line-height: 1.5;">
                                <?php esc_html_e('On some sites, an additional redirect is required to correctly deliver protected files. Leave this disabled for better performance if possible.', 'press-permit-core'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- File Access Reset Key Card -->
            <div class="pp-key-management-card" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <div class="pp-setting-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <div class="pp-setting-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--warning-color), #F97316); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">🔑</div>
                    <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('File Access Reset Key', 'press-permit-core'); ?></h4>
                </div>
                
                <div style="background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <strong style="color: var(--text-color); font-size: 14px;"><?php esc_html_e('Current Key:', 'press-permit-core'); ?></strong>
                        <div style="flex: 1; display: flex; align-items: center; gap: 8px;">
                            <code id="file_filtering_regen_key" style="background: white; border: 1px solid var(--border-color); border-radius: 4px; padding: 6px 12px; font-family: monospace; font-size: 13px; color: var(--text-color); flex: 1;">
                                <?php echo esc_html($this->getFileAccessKey()); ?>
                            </code>
                            <button type="button" class="button button-small pp-copy-btn" id="copy-btn" onclick="copyFileFilteringKey()" style="background: var(--primary-color); color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;">
                                <?php esc_html_e('Copy', 'press-permit-core'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <p style="margin: 0 0 12px 0; color: #6B7280; font-size: 14px; line-height: 1.6;">
                        <?php
                        printf(
                            esc_html__('The File Access feature uses an %1$s.htaccess%2$s file located at %3$suploads/.htaccess%4$s. Normally no action is needed, but clicking the button below will update the URL keys that protect the files.', 'press-permit-core'),
                            '<strong>',
                            '</strong>',
                            '<strong>',
                            '</strong>'
                        );
                        ?>
                    </p>
                    <a href="<?php echo esc_url($this->getRegenerateUrl()); ?>" 
                       class="button button-secondary pp-action-btn" 
                       style="background: white; border: 2px solid var(--primary-color); color: var(--primary-color); border-radius: 8px; padding: 8px 16px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">🔄</span>
                        <?php esc_html_e('Regenerate Access File', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>

            <!-- Utilities Section -->
            <div class="pp-utilities-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                
                <!-- Attachments Utility Card -->
                <div class="pp-utility-card pp-utility-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease;">
                    <div class="pp-utility-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div class="pp-utility-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">📁</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Attachments Utility', 'press-permit-core'); ?></h4>
                    </div>
                    <p style="margin: 0 0 16px 0; color: #6B7280; font-size: 14px; line-height: 1.6;">
                        <?php esc_html_e('File Access can also protect files uploaded by FTP or other manual file copy, but does not detect them automatically. Scan your uploads folder for files to protect.', 'press-permit-core'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=presspermit-attachments_utility')); ?>" 
                       class="button button-secondary pp-action-btn" 
                       target="_blank"
                       style="background: white; border: 2px solid #3B82F6; color: #3B82F6; border-radius: 8px; padding: 8px 16px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">🔍</span>
                        <?php esc_html_e('Open Utility', 'press-permit-core'); ?>
                    </a>
                </div>

                <!-- Nginx Integration Card -->
                <div class="pp-utility-card pp-utility-card-hover" style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 24px; transition: all 0.3s ease;">
                    <div class="pp-utility-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div class="pp-utility-icon" style="width: 40px; height: 40px; background: linear-gradient(135deg, #059669, #047857); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">🌐</div>
                        <h4 style="margin: 0; color: var(--text-color); font-size: 16px; font-weight: 600;"><?php esc_html_e('Nginx Integration', 'press-permit-core'); ?></h4>
                    </div>
                    <p style="margin: 0 0 16px 0; color: #6B7280; font-size: 14px; line-height: 1.6;">
                        <?php esc_html_e('Using Nginx instead of Apache? Learn how to configure file access protection with Nginx server blocks and rewrite rules.', 'press-permit-core'); ?>
                    </p>
                    <a href="https://publishpress.com/knowledge-base/file-filtering-nginx/" 
                       target="_blank"
                       class="button button-secondary pp-action-btn" 
                       style="background: white; border: 2px solid #059669; color: #059669; border-radius: 8px; padding: 8px 16px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">📖</span>
                        <?php esc_html_e('View Documentation', 'press-permit-core'); ?>
                    </a>
                </div>
            </div>

            <!-- Enhanced Styling and Interactions -->
            <style>
            .pp-setting-card-hover:hover,
            .pp-utility-card-hover:hover {
                border-color: var(--primary-color) !important;
                box-shadow: 0 8px 25px rgba(139, 92, 246, 0.15) !important;
                transform: translateY(-2px) !important;
            }
            
            .pp-setting-card-hover:hover .pp-setting-icon,
            .pp-utility-card-hover:hover .pp-utility-icon {
                transform: scale(1.1) !important;
            }

            .pp-copy-btn:hover {
                background: var(--primary-hover) !important;
                transform: translateY(-1px) !important;
            }

            .pp-action-btn:hover {
                background: var(--primary-color) !important;
                color: white !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25) !important;
            }

            .pp-toggle-switch input[type="checkbox"] {
                appearance: none;
                width: 44px;
                height: 24px;
                background: #E5E7EB;
                border-radius: 12px;
                position: relative;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .pp-toggle-switch input[type="checkbox"]:checked {
                background: var(--primary-color);
            }

            .pp-toggle-switch input[type="checkbox"]:before {
                content: '';
                position: absolute;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background: white;
                top: 2px;
                left: 2px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .pp-toggle-switch input[type="checkbox"]:checked:before {
                transform: translateX(20px);
            }
            </style>

            <script>
            function copyFileFilteringKey() {
                var keyElem = document.getElementById('file_filtering_regen_key');
                var btnElem = document.getElementById('copy-btn');
                if (!keyElem || !btnElem) return;
                
                var text = keyElem.textContent || keyElem.innerText;
                var originalText = btnElem.innerHTML;
                var copiedText = '<?php esc_html_e('Copied!', 'press-permit-core'); ?>';
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text.trim()).then(function() {
                        btnElem.innerHTML = copiedText;
                        btnElem.style.background = 'var(--success-color)';
                        setTimeout(function() {
                            btnElem.innerHTML = originalText;
                            btnElem.style.background = 'var(--primary-color)';
                        }, 1500);
                    });
                } else {
                    // fallback for older browsers
                    var tempInput = document.createElement('input');
                    tempInput.value = text.trim();
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    btnElem.innerHTML = copiedText;
                    btnElem.style.background = 'var(--success-color)';
                    setTimeout(function() {
                        btnElem.innerHTML = originalText;
                        btnElem.style.background = 'var(--primary-color)';
                    }, 1500);
                }
            }
            </script>
        </div>
        <?php
    }

    private function getFileAccessKey()
    {
        // Generate or get stored file access key
        $key = get_option('presspermit_file_filtering_regen_key');
        if (!$key) {
            $key = wp_generate_password(16, false);
            update_option('presspermit_file_filtering_regen_key', $key);
        }
        return $key;
    }

    /**
     * Helper method for creating tab badges
     * Makes it easy for other developers to create consistent badges
     * 
     * @param string $type Badge type: 'pro', 'new', 'beta', 'hot', 'premium'
     * @param string $custom_text Optional custom text instead of type
     * @param string $custom_color Optional custom background color
     * @return array Badge configuration array
     */
    public static function createTabBadge($type = 'pro', $custom_text = '', $custom_color = '')
    {
        $badge_configs = [
            'pro' => [
                'text' => 'PRO',
                'bg_color' => '#8B5CF6',
                'class' => 'pp-pro-badge'
            ],
            'new' => [
                'text' => 'NEW', 
                'bg_color' => '#10B981',
                'class' => 'pp-new-badge'
            ],
            'beta' => [
                'text' => 'BETA',
                'bg_color' => '#F59E0B', 
                'class' => 'pp-beta-badge'
            ],
            'hot' => [
                'text' => 'HOT',
                'bg_color' => '#EF4444',
                'class' => 'pp-hot-badge'
            ],
            'premium' => [
                'text' => 'PREMIUM',
                'bg_color' => '#D97706',
                'class' => 'pp-premium-badge'
            ]
        ];

        $badge = isset($badge_configs[$type]) ? $badge_configs[$type] : $badge_configs['pro'];
        
        // Allow custom overrides
        if ($custom_text) {
            $badge['text'] = $custom_text;
        }
        if ($custom_color) {
            $badge['bg_color'] = $custom_color;
        }
        
        // Always include default properties
        $badge['color'] = 'white';
        
        return $badge;
    }

    private function getRegenerateUrl()
    {
        $key = $this->getFileAccessKey();
        return add_query_arg([
            'action' => 'presspermit-expire-file-rules',
            'key' => $key
        ], home_url('/'));
    }
}
