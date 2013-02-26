<?php

/*
 * Fusionary Bootstrap for ExpressionEngine 2
 *
 * @see https://github.com/focuslabllc/ee-master-config
 * @see http://eeinsider.com/articles/multi-server-setup-for-ee-2/
 */

namespace Fusionary\ExpressionEngine2\Bootstrap;
use Symfony\Component\Yaml\Yaml;

class Bootstrap
{
    private static $instance = false;
    private $valid_config_keys = array(
        'environment',
        'debug',
        'system_path',
        'config_vars',
        'global_vars',
        'db_config'
    );

    public $min_php_version = '5.3.3';
    public $environment     = 'development';
    public $debug           = 0;
    public $global_vars     = array();
    public $config_vars = array(
        'app_version' => 255,
        'license_number' => '',
        'upload_preferences' => array()
    );
    public $db_config = array(
        'dbdriver' => 'mysql',
        'pconnect' => false,
        'dbprefix' => 'exp_',
        'swap_pre' => 'exp_',
        'db_debug' => true,
        'cache_on' => false,
        'autoinit' => false,
        'char_set' => 'utf8',
        'dbcollat' => 'utf8_general_ci',
    );

    /**
     * Constructor
     */

    public function __construct()
    {
        global $assign_to_config;
        $this->global_vars    = isset($assign_to_config['global_vars']) ? $assign_to_config['global_vars'] : $this->global_vars;
        $this->protocol       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
        $this->host           = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        $this->bootstrap_root = defined("BOOTSTRAP_ROOT") ? BOOTSTRAP_ROOT : $_SERVER['DOCUMENT_ROOT'];
        $this->bootstrap_root = rtrim($this->bootstrap_root, '/') . '/';

        // Base URLs
        // All non-file URLs should have trailing slashes.
        $this->base_url         = $this->protocol . $this->host . '/';
        $this->uploads_url      = $this->base_url . 'uploads/';
        $this->ee_images_url    = $this->uploads_url . 'members/';
        $this->public_cache_url = $this->base_url . 'cache/';
        $this->public_dir_name  = 'public';

        // Base paths
        // All paths/dirs should have trailing slashes.
        $this->system_path           = $this->bootstrap_root . 'system/';
        $this->base_path             = $this->bootstrap_root . $this->public_dir_name . '/';
        $this->vendor_path           = $this->bootstrap_root . 'vendor/';
        $this->config_path           = $this->bootstrap_root . 'config/';
        $this->template_path         = $this->bootstrap_root . 'templates/';
        $this->ee_path               = $this->system_path . 'expressionengine/';
        $this->uploads_path          = $this->base_path . 'uploads/';
        $this->ee_images_path        = $this->uploads_path . 'members/';
        $this->public_cache_path     = $this->base_path . 'cache/';
        $this->db_config['cachedir'] = $this->system_path . 'cache/db_cache/';

        // Set defaults
        $this->setConfigVars(false, true);
        $this->setGlobalVars(false, true);

    }

    /**
     * Get class instance
     */
    public static function getInstance()
    {
        if (self::$instance === false) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check requirements
     */
    public function checkRequirements()
    {

        // PHP version
        if (version_compare(PHP_VERSION, $this->min_php_version, '<=')) {
            exit('PHP Version ' . PHP_VERSION . ' detected. This ExpressionEngine 2.x Boilerplate requires PHP ' . $this->min_php_version . ' or greater.');
        }

        // Environment
        if (!$this->environment) {
            exit('No environment set.');
        }

        // Check for PHP version
        if (!isset($this->db_config['database']) || !$this->db_config['database']) {
            exit('No database specified.');
        }
    }

    /**
     * Set config variables
     */
    public function setConfigVars($array = false, $init = false)
    {
        $array = !is_array($array) ? array() : $array;

        if ($init === true) {

            /**
             * Hidden config vars
             * @see http://ellislab.com/expressionengine/user-guide/general/hidden_configuration_variables.html
             */

            // Path/URL settings
            $this->config_vars['index_page']          = '';
            $this->config_vars['site_index']          = $this->config_vars['index_page'];
            $this->config_vars['base_url']            = $this->base_url;
            $this->config_vars['site_url']            = $this->base_url;
            $this->config_vars['cp_url']              = $this->base_url . 'cp/index.php';
            $this->config_vars['theme_folder_path']   = $this->base_path   . 'themes/';
            $this->config_vars['theme_folder_url']    = $this->base_url    . 'themes/';
            $this->config_vars['emoticon_path']       = $this->ee_images_url  . 'smileys/';
            $this->config_vars['emoticon_url']        = $this->ee_images_url  . 'smileys/';
            $this->config_vars['captcha_path']        = $this->ee_images_path . 'captchas/';
            $this->config_vars['captcha_url']         = $this->ee_images_url  . 'captchas/';
            $this->config_vars['avatar_path']         = $this->ee_images_path . 'avatars/';
            $this->config_vars['avatar_url']          = $this->ee_images_url  . 'avatars/';
            $this->config_vars['photo_path']          = $this->ee_images_path . 'member_photos/';
            $this->config_vars['photo_url']           = $this->ee_images_url  . 'member_photos/';
            $this->config_vars['sig_img_path']        = $this->ee_images_path . 'signature_attachments/';
            $this->config_vars['sig_img_url']         = $this->ee_images_url  . 'signature_attachments/';
            $this->config_vars['prv_msg_upload_path'] = $this->ee_images_path . 'pm_attachments/';
            $this->config_vars['third_party_path']    = $this->vendor_path . 'third_party/';
            $this->config_vars['tmpl_file_basepath']  = $this->template_path . 'site_templates/';

            // Debugging settings
            $this->config_vars['is_system_on']       = 'y';
            $this->config_vars['allow_extensions']   = 'y';
            $this->config_vars['email_debug']        = ($this->debug) ? 'y' : 'n';
            $this->config_vars['show_profiler']      = (!$this->debug || (isset($_GET['D']) && $_GET['D'] == 'cp')) ? 'n' : 'y';
            $this->config_vars['template_debugging'] = ($this->debug) ? 'y' : 'n';
            $this->config_vars['debug']              = ($this->debug) ? '2' : '1'; # 0: no PHP/SQL errors shown. 1: Errors shown to Super Admins. 2: Errors shown to everyone.

            // Tracking & performance
            $this->config_vars['disable_all_tracking']        = 'y'; // If set to 'y' some of the below settings are disregarded
            $this->config_vars['enable_sql_caching']          = 'n';
            $this->config_vars['disable_tag_caching']         = 'n';
            $this->config_vars['enable_online_user_tracking'] = 'n';
            $this->config_vars['dynamic_tracking_disabling']  = '500';
            $this->config_vars['enable_hit_tracking']         = 'n';
            $this->config_vars['enable_entry_view_tracking']  = 'n';
            $this->config_vars['log_referrers']               = 'n';
            $this->config_vars['gzip_output']                 = 'n';

            // Cookies & session
            $this->config_vars['cookie_domain']      =  '.' . $this->removeWww($this->host);
            $this->config_vars['cookie_path']        =  '';
            $this->config_vars['user_session_type']  = 'c';
            $this->config_vars['admin_session_type'] = 'cs';

            // Localization
            $this->config_vars['daylight_savings']          = ((bool) date('I')) ? 'y' : 'n'; # Auto-detect DST
            $this->config_vars['server_timezone']           = 'UM5';
            $this->config_vars['default_site_dst']          = $this->config_vars['daylight_savings'];
            $this->config_vars['default_site_timezone']     = $this->config_vars['server_timezone'];
            $this->config_vars['time_format']               = 'us';
            $this->config_vars['server_offset']             = '';
            $this->config_vars['allow_member_localization'] = 'n';

            // Member settings
            $this->config_vars['profile_trigger']           = rand(0, time());
            $this->config_vars['enable_emoticons']          = 'n';
            $this->config_vars['enable_avatars']            = 'n';
            $this->config_vars['enable_photos']             = 'n';
            $this->config_vars['sig_allow_img_upload']      = 'n';
            $this->config_vars['captcha_require_members']   = 'n';
            $this->config_vars['allow_member_registration'] = 'n';

            // URL/Template settings
            $this->config_vars['use_category_name']         = 'y';
            $this->config_vars['reserved_category_word']    = 'category';
            $this->config_vars['word_separator']            = 'dash'; # dash|underscore
            $this->config_vars['strict_urls']               = 'y';
            $this->config_vars['site_404']                  = 'site/404';
            $this->config_vars['save_tmpl_files']           = 'y';
            $this->config_vars['hidden_template_indicator'] = '_';
            $this->config_vars['uri_protocol']              = 'PATH_INFO'; # AUTO|PATH_INFO|QUERY_STRING|REQUEST_URI|ORIG_PATH_INFO
            $this->config_vars['enable_query_strings']      = TRUE;
            $this->config_vars['permitted_uri_chars']       = 'a-z 0-9~%.:_\\-';

            // Other
            $this->config_vars['encryption_key']            = 'aU807G5kLzw2nwu43n0TC4C0W770z566'; # random 32 characater string
            $this->config_vars['save_tmpl_revisions']       = 'n';
            $this->config_vars['new_version_check']         = 'n'; # no slowing my CP homepage down with this
            $this->config_vars['protect_javascript']        = 'y'; # prevents the advanced conditionals parser from processing anything in tags
            $this->config_vars['autosave_interval_seconds'] = '0'; # 0: disables entry autosave
            $this->config_vars['password_lockout']          = 'n';
            $this->config_vars['cp_theme']                  = 'default';

            /**
             * Vars pulled from system/expressionengine/config/config.php that we don't usually change
             */

            // ExpressionEngine
            $this->config_vars['install_lock'] = '';
            $this->config_vars['doc_url'] = "http://ellislab.com/expressionengine/user-guide/";
            $this->config_vars['site_label'] = '';

            // CodeIgniter
            $this->config_vars['url_suffix'] = '';
            $this->config_vars['language'] = 'english';
            $this->config_vars['charset'] = 'UTF-8';
            $this->config_vars['enable_hooks'] = FALSE;
            $this->config_vars['subclass_prefix'] = 'EE_';
            $this->config_vars['directory_trigger'] = 'D';
            $this->config_vars['controller_trigger'] = 'C';
            $this->config_vars['function_trigger'] = 'M';
            $this->config_vars['log_threshold'] = 0;
            $this->config_vars['log_path'] = '';
            $this->config_vars['log_date_format'] = 'Y-m-d H:i:s';
            $this->config_vars['cache_path'] = '';
            $this->config_vars['global_xss_filtering'] = FALSE;
            $this->config_vars['csrf_protection'] = FALSE;
            $this->config_vars['compress_output'] = FALSE;
            $this->config_vars['time_reference'] = 'local';
            $this->config_vars['rewrite_short_tags'] = TRUE;
            $this->config_vars['proxy_ips'] = '';

            /**
             * Third-party config vars
             */

            // CE Image
            $this->config_vars['ce_image_document_root']     = $this->base_path;
            $this->config_vars['ce_image_cache_dir']         = '/cache/made/';
            $this->config_vars['ce_image_remote_dir']        = '/cache/remote/';
            $this->config_vars['ce_image_memory_limit']      = 64;
            $this->config_vars['ce_image_remote_cache_time'] = 1440;
            $this->config_vars['ce_image_quality']           = 90;
            $this->config_vars['ce_image_disable_xss_check'] = 'no';

            // Playa
            $this->config_vars['playa_site_index'] = $this->base_url;

            // Minimee
            $this->config_vars['minimee_cache_path']  = $this->public_cache_path;
            $this->config_vars['minimee_cache_url']   = $this->public_cache_url;
            $this->config_vars['minimee_base_path']   = $this->base_path;
            $this->config_vars['minimee_base_url']    = $this->base_url;
            $this->config_vars['minimee_debug']       = 'n';
            $this->config_vars['minimee_disable']     = 'n';
            $this->config_vars['minimee_remote_mode'] = 'auto'; # auto/curl/fgc
            $this->config_vars['minimee_minify_html'] = 'yes';

            // Assets
            $this->config_vars['assets_site_url'] = '/index.php';
            $this->config_vars['assets_cp_path']  = $this->system_path;

            // Low Variables
            $this->config_vars['low_variables_save_as_files'] = 'y';
            $this->config_vars['low_variables_file_path']     = $this->template_path . 'low_variables/';

            // Stash
            $this->config_vars['stash_file_basepath'] = $this->template_path . 'stash_templates/';
            $this->config_vars['stash_file_sync'] = ($this->environment == 'production') ? false : true;

            /**
             * Custom config vars
             */

            $this->config_vars['google_analytics_id']    = ($this->environment == 'production') ? 'UA-XXXXXXX-XX' : '';
            $this->config_vars['cookie_expire_days']     = 30; # in days
            $this->config_vars['cookie_expire_from_now'] = time() + (60 * 60 * 24 * $this->config_vars['cookie_expire_days']); # now +x days
            $this->config_vars['global_json'] = array(
                'env'               => $this->environment,
                'salt'              => $this->config_vars['encryption_key'],
                'googleAnalyticsId' => $this->config_vars['google_analytics_id'],
                'cookieSettings' => array(
                    'path'    => $this->config_vars['cookie_path'],
                    'domain'  => $this->config_vars['cookie_domain'],
                    'expires' => $this->config_vars['cookie_expire_days'],
                ),
                'html' => array(),
            );
        }

        /**
         * Upload preferences
         */
        if (isset($this->config_vars['upload_preferences'])) {
            foreach ($this->config_vars['upload_preferences'] as $key => &$dir) {
                if (!is_array($dir)) {
                    $dir = trim($dir, '/');
                    $pattern = '/\/?' . $this->public_dir_name . '\/?/';
                    $dir = array(
                        'server_path' => $this->bootstrap_root . $dir . '/',
                        'url' => '/' . preg_replace($pattern, '', $dir) . '/',
                    );
                }
            }
        }

        return array_merge($this->config_vars, $array);
    }

    /**
     * Set database vars
     */
    public function setDbVars($vars = array())
    {
        return array_merge(
            $this->db_config,
            $vars
        );
    }

    /**
     * Set global vars
     * Global vars should be prefixed with "gv_".
     */
    public function setGlobalVars($array = false, $init = false)
    {
        $array = !is_array($array) ? array() : $array;

        if ($init === true) {

            // Shortcuts
            $this->global_vars['gv_date_fmt']       = '%F %j, %Y';
            $this->global_vars['gv_date_fmt_time']  = '%g:%i %a';
            $this->global_vars['gv_date_fmt_full']  = '%F %j %Y, %g:%i %a';

            // Param shortcuts
            $this->global_vars['gv_param_no_limit']                 = 'limit="9999999999"';
            $this->global_vars['gv_param_structure_nav_defaults']   = 'show_depth="1" max_depth="1" current_class="active" css_id="none" channel:title="page:cf_page_title"';
            $this->global_vars['gv_param_low_title_entry_defaults'] = 'entry_id="{entry_id}" fallback="yes" custom_field="cf_{channel_short_name}_title"';
            $this->global_vars['gv_param_ce_img_defaults']          = 'fallback_src="/assets/styles/images/other/placeholder.png" allow_scale_larger="yes" crop="yes"';

            // Environment
            $this->global_vars['gv_env'] = $this->environment;

            // Paths/URLs
            $this->global_vars['gv_path_vendor'] = $this->vendor_path;
            $this->global_vars['gv_base_url'] = $this->base_url; # because site_url is late parsed

            // Other
            $this->global_vars['gv_salt'] = $this->config_vars['encryption_key'];
        }

        return array_merge($this->global_vars, $array);
    }

    /**
     * Apply config files
     */
    public function applyConfigFile($file, $parent_key = false)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'yml':
            case 'yaml':
                $yaml_array = Yaml::parse($file);

                // Top level Vars
                $this->arrayToProps($yaml_array, false, $parent_key);

                // Environment Vars
                if (isset($yaml_array[$this->environment])) {
                    $this->arrayToProps($yaml_array[$this->environment], false, $parent_key);
                }
                break;

            default:
                if (file_exists($file)) {
                    require_once $file;
                }
                break;
        }
    }

    /**
     * Remove www from a URL string
     */
    public function removeWww($url) {
        $url = preg_replace('#^(http(s)?://)?w{$3}\.(\w+\.\w+)#', '$1$3', $url);
        return $url;
    }

    /**
     * Merge valid array values to properties
     */
    private function arrayToProps($array, $valid_keys = false, $parent_key = false)
    {
        $valid_keys = ($valid_keys === false) ?  $this->valid_config_keys : $valid_keys;
        if (is_string($parent_key)) {
            $array = array($parent_key => $array);
        }
        foreach ($array as $key => $value) {
            if (in_array($key, $valid_keys) && $value !== null) {
                if (isset($this->$key) && is_array($this->$key) && is_array($value)) {
                    $this->$key = array_merge($this->$key, $value);
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

}
