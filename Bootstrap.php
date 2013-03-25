<?php

/*
 * Fusionary Bootstrap for ExpressionEngine 2
 *
 * @see https://github.com/focuslabllc/ee-master-config
 * @see http://eeinsider.com/articles/multi-server-setup-for-ee-2/
 */

namespace Fusionary\ExpressionEngine2\Bootstrap;
use Symfony\Component\Yaml\Yaml;
use Fusionary\UtilityBelt;

class Bootstrap
{
    private static $instance = false;
    private $valid_keys = array(
        'db_config',
        'config_vars',
        'global_vars',
    );

    public $system_path;
    public $debug           = 0;
    public $db_config       = array();
    public $global_vars     = array();
    public $config_vars     = array(
        'app_version'        => '255',
        'license_number'     => '',
        'index_page'         => '',
        'upload_preferences' => array(),
    );

    private $defaults        = array();
    public $min_php_version = '5.3.14';


    /**
     * Constructor
     */
    public function __construct()
    {
        // PHP version
        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            throw new \RuntimeException(sprintf('PHP Version %s detected. This ExpressionEngine 2.x Boilerplate requires PHP %s or greater.', PHP_VERSION, $this->min_php_version));
        }

        global $assign_to_config;
        $this->environment = getenv('APP_ENV') ?: 'development';
        $this->global_vars = isset($assign_to_config['global_vars']) ? $assign_to_config['global_vars'] : $this->global_vars;
        $this->protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
        $this->host        = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

        // Base paths & URLs
        // All paths/dirs and non-file URLs should have trailing slashes.
        $this->app_root          = defined("APP_ROOT") ? APP_ROOT : $_SERVER['DOCUMENT_ROOT'];
        $this->app_root          = rtrim($this->app_root, '/') . '/';
        $this->system_path       = $this->app_root . 'system/';
        $this->vendor_path       = $this->app_root . 'vendor/';
        $this->config_path       = $this->app_root . 'config/';
        $this->template_path     = $this->app_root . 'templates/';
        $this->base_url          = $this->protocol . $this->host . '/';
        $this->base_path         = $this->app_root . 'public/';
        $this->uploads_url       = $this->base_url . 'uploads/';
        $this->uploads_path      = $this->base_path . 'uploads/';
        $this->ee_images_url     = $this->uploads_url . 'members/';
        $this->ee_images_path    = $this->uploads_path . 'members/';
        $this->public_cache_url  = $this->base_url . 'cache/';
        $this->public_cache_path = $this->base_path . 'cache/';

        $this->defaults['config_vars']['encryption_key']            = 'foo'; # random 32 characater string
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

    public function get($name)
    {
        switch ($name) {
            case 'db_config':
                // Rails/Capistrano database.yml compatibility
                if (isset($this->db_config['host'])) {
                    $this->db_config['hostname'] = $this->db_config['host'];
                    unset($this->db_config['host']);
                }
                break;
            case 'config_vars':
                // Upload preferences
                // If not an array, we assume path and url relative to base_path
                foreach ($this->config_vars['upload_preferences'] as $key => &$dir) {
                    if (!is_array($dir)) {
                        $dir = trim($dir, '/');
                        $dir = array(
                            'server_path' => $this->base_path . $dir . '/',
                            'url' => $dir . '/',
                        );
                    }
                }
                break;
        }

        // Return name or error
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            throw new \InvalidArgumentException(sprintf('The property "%s" does not exist.', $name));
        }
    }

    private function readConfigFile($file, $require = true)
    {
        if ($require && !file_exists($file)) {
            throw new \InvalidArgumentException(sprintf('The config file "%s" does not exist.', $file));
        }
        $file_parts = pathinfo($file);
        $vars = $env_vars = array();
        switch ($file_parts['extension']) {
            case 'yml':
            case 'yaml':
                $vars = Yaml::parse($file) ?: $vars;
                break;
            case 'ini':
                $vars = parse_ini_file($file, true) ?: $vars;
                break;
            case 'php':
                require $file;
                break;
        }

        // Split env vars and merge
        if (isset($vars[$this->environment])) {
            $env_vars = $vars[$this->environment];
            unset($vars[$this->environment]);
        }

        if (isset($env_vars['defaults'])) {
            $this->defaults = $this->mergeRecursively($this->defaults, $env_vars['defaults']);
        }

        return $this->mergeRecursively($vars, $env_vars);
    }

    /**
     * Apply config
     *
     * Although config file values take precedence, we apply them first so
     * that we can reference their values in our defaults.
     */
    public function applyConfig($files)
    {
        // Files
        foreach ($files as $file) {
            $this->toProps($this->readConfigFile($file));
        }

        // Defaults
        // exit( var_dump($this->defaults) );
        $this->toProps($this->getDefaults(), true);
    }

    /**
     * Apply defaults
     */
    private function getDefaults()
    {
        $defaults = array(
            'config_vars' => array(),
            'db_config'   => array(),
            'global_vars' => array(),
        );

        // -----------------------------------------------------------------------------

        /**
         * Hidden config vars
         * @see http://ellislab.com/expressionengine/user-guide/general/hidden_configuration_variables.html
         */

        // Path/URL settings
        $defaults['config_vars']['index_page']          = '';
        $defaults['config_vars']['site_index']          = $defaults['config_vars']['index_page'];
        $defaults['config_vars']['base_url']            = $this->base_url;
        $defaults['config_vars']['site_url']            = $this->base_url;
        $defaults['config_vars']['cp_url']              = $this->base_url . 'cp/index.php';
        $defaults['config_vars']['theme_folder_path']   = $this->base_path   . 'themes/';
        $defaults['config_vars']['theme_folder_url']    = $this->base_url    . 'themes/';
        $defaults['config_vars']['emoticon_path']       = $this->ee_images_url  . 'smileys/';
        $defaults['config_vars']['emoticon_url']        = $this->ee_images_url  . 'smileys/';
        $defaults['config_vars']['captcha_path']        = $this->ee_images_path . 'captchas/';
        $defaults['config_vars']['captcha_url']         = $this->ee_images_url  . 'captchas/';
        $defaults['config_vars']['avatar_path']         = $this->ee_images_path . 'avatars/';
        $defaults['config_vars']['avatar_url']          = $this->ee_images_url  . 'avatars/';
        $defaults['config_vars']['photo_path']          = $this->ee_images_path . 'member_photos/';
        $defaults['config_vars']['photo_url']           = $this->ee_images_url  . 'member_photos/';
        $defaults['config_vars']['sig_img_path']        = $this->ee_images_path . 'signature_attachments/';
        $defaults['config_vars']['sig_img_url']         = $this->ee_images_url  . 'signature_attachments/';
        $defaults['config_vars']['prv_msg_upload_path'] = $this->ee_images_path . 'pm_attachments/';
        $defaults['config_vars']['third_party_path']    = $this->vendor_path . 'third_party/';
        $defaults['config_vars']['tmpl_file_basepath']  = $this->template_path . 'ee_templates/';

        // Debugging settings
        $defaults['config_vars']['is_system_on']       = 'y';
        $defaults['config_vars']['allow_extensions']   = 'y';
        $defaults['config_vars']['email_debug']        = ($this->debug) ? 'y' : 'n';
        $defaults['config_vars']['show_profiler']      = (!$this->debug || (isset($_GET['D']) && $_GET['D'] == 'cp')) ? 'n' : 'y';
        $defaults['config_vars']['template_debugging'] = ($this->debug) ? 'y' : 'n';
        $defaults['config_vars']['debug']              = ($this->debug) ? '2' : '1'; # 0: no PHP/SQL errors shown. 1: Errors shown to Super Admins. 2: Errors shown to everyone.

        // Tracking & performance
        $defaults['config_vars']['disable_all_tracking']        = 'y'; # If set to 'y' some of the below settings are disregarded
        $defaults['config_vars']['enable_sql_caching']          = 'n';
        $defaults['config_vars']['disable_tag_caching']         = 'n';
        $defaults['config_vars']['enable_online_user_tracking'] = 'n';
        $defaults['config_vars']['dynamic_tracking_disabling']  = '500';
        $defaults['config_vars']['enable_hit_tracking']         = 'n';
        $defaults['config_vars']['enable_entry_view_tracking']  = 'n';
        $defaults['config_vars']['log_referrers']               = 'n';
        $defaults['config_vars']['gzip_output']                 = 'n';

        // Cookies & session
        $defaults['config_vars']['cookie_domain']      = '.' . UtilityBelt::removeWww($this->host);
        $defaults['config_vars']['cookie_path']        = '';
        $defaults['config_vars']['user_session_type']  = 'c';
        $defaults['config_vars']['admin_session_type'] = 'cs';

        // Localization
        $defaults['config_vars']['daylight_savings']          = 'UM5';
        $defaults['config_vars']['server_timezone']           = ((bool) date('I')) ? 'y' : 'n'; # Auto-detect DST;
        $defaults['config_vars']['default_site_dst']          = $defaults['config_vars']['daylight_savings'];
        $defaults['config_vars']['default_site_timezone']     = $defaults['config_vars']['server_timezone'];
        $defaults['config_vars']['time_format']               = 'us';
        $defaults['config_vars']['server_offset']             = '';
        $defaults['config_vars']['allow_member_localization'] = 'n';

        // Member settings
        $defaults['config_vars']['profile_trigger']           = rand(0, time());
        $defaults['config_vars']['enable_emoticons']          = 'n';
        $defaults['config_vars']['enable_avatars']            = 'n';
        $defaults['config_vars']['enable_photos']             = 'n';
        $defaults['config_vars']['sig_allow_img_upload']      = 'n';
        $defaults['config_vars']['captcha_require_members']   = 'n';
        $defaults['config_vars']['allow_member_registration'] = 'n';

        // URL/Template settings
        $defaults['config_vars']['use_category_name']         = 'y';
        $defaults['config_vars']['reserved_category_word']    = 'category';
        $defaults['config_vars']['word_separator']            = 'dash'; # dash|underscore
        $defaults['config_vars']['strict_urls']               = 'y';
        $defaults['config_vars']['site_404']                  = 'site/404';
        $defaults['config_vars']['save_tmpl_files']           = 'y';
        $defaults['config_vars']['hidden_template_indicator'] = '_';
        $defaults['config_vars']['uri_protocol']              = 'PATH_INFO'; # AUTO|PATH_INFO|QUERY_STRING|REQUEST_URI|ORIG_PATH_INFO
        $defaults['config_vars']['enable_query_strings']      = TRUE;
        $defaults['config_vars']['permitted_uri_chars']       = 'a-z 0-9~%.:_\\-';

        // Other
        $defaults['config_vars']['encryption_key']            = $this->config_vars['encryption_key']; # random 32 characater string
        $defaults['config_vars']['save_tmpl_revisions']       = 'n';
        $defaults['config_vars']['new_version_check']         = 'n'; # no slowing my CP homepage down with this
        $defaults['config_vars']['protect_javascript']        = 'y'; # prevents the advanced conditionals parser from processing anything in tags
        $defaults['config_vars']['autosave_interval_seconds'] = '0'; # 0: disables entry autosave
        $defaults['config_vars']['password_lockout']          = 'n';
        $defaults['config_vars']['cp_theme']                  = 'republic'; # Republic CP

        /**
         * Vars from system/expressionengine/config/config.php that we don't usually change
         */

        // ExpressionEngine
        $defaults['config_vars']['install_lock'] = '';
        $defaults['config_vars']['doc_url']      = "http://ellislab.com/expressionengine/user-guide/";
        $defaults['config_vars']['site_label']   = '';

        // CodeIgniter
        $defaults['config_vars']['url_suffix']           = '';
        $defaults['config_vars']['language']             = 'english';
        $defaults['config_vars']['charset']              = 'UTF-8';
        $defaults['config_vars']['enable_hooks']         = FALSE;
        $defaults['config_vars']['subclass_prefix']      = 'EE_';
        $defaults['config_vars']['directory_trigger']    = 'D';
        $defaults['config_vars']['controller_trigger']   = 'C';
        $defaults['config_vars']['function_trigger']     = 'M';
        $defaults['config_vars']['log_threshold']        = 0;
        $defaults['config_vars']['log_path']             = '';
        $defaults['config_vars']['log_date_format']      = 'Y-m-d H:i:s';
        $defaults['config_vars']['cache_path']           = '';
        $defaults['config_vars']['global_xss_filtering'] = FALSE;
        $defaults['config_vars']['csrf_protection']      = FALSE;
        $defaults['config_vars']['compress_output']      = FALSE;
        $defaults['config_vars']['time_reference']       = 'local';
        $defaults['config_vars']['rewrite_short_tags']   = TRUE;
        $defaults['config_vars']['proxy_ips']            = '';

        /**
         * Third-party config vars
         */

        // CE Image
        $defaults['config_vars']['ce_image_document_root']     = $this->base_path;
        $defaults['config_vars']['ce_image_cache_dir']         = '/cache/made/';
        $defaults['config_vars']['ce_image_remote_dir']        = '/cache/remote/';
        $defaults['config_vars']['ce_image_memory_limit']      = 64;
        $defaults['config_vars']['ce_image_remote_cache_time'] = 1440;
        $defaults['config_vars']['ce_image_quality']           = 90;
        $defaults['config_vars']['ce_image_disable_xss_check'] = 'no';

        // Playa
        $defaults['config_vars']['playa_site_index'] = $this->base_url;

        // Minimee
        $defaults['config_vars']['minimee_cache_path']  = $this->public_cache_path;
        $defaults['config_vars']['minimee_cache_url']   = $this->public_cache_url;
        $defaults['config_vars']['minimee_base_path']   = $this->base_path;
        $defaults['config_vars']['minimee_base_url']    = $this->base_url;
        $defaults['config_vars']['minimee_debug']       = 'n';
        $defaults['config_vars']['minimee_disable']     = 'n';
        $defaults['config_vars']['minimee_remote_mode'] = 'auto'; # auto/curl/fgc
        $defaults['config_vars']['minimee_minify_html'] = 'yes';

        // Assets
        $defaults['config_vars']['assets_site_url'] = '/index.php';
        $defaults['config_vars']['assets_cp_path']  = $this->system_path;

        // Low Variables
        $defaults['config_vars']['low_variables_save_as_files'] = 'y';
        $defaults['config_vars']['low_variables_file_path']     = $this->template_path . 'low_variables/';

        // Stash
        $defaults['config_vars']['stash_file_basepath'] = $this->template_path . 'stash_templates/';
        $defaults['config_vars']['stash_file_sync']     = ($this->environment == 'production') ? false : true;

        /**
         * Custom config vars
         */
        $defaults['config_vars']['google_analytics_id']    = ($this->environment == 'production') ? 'UA-XXXXXXX-XX' : '';
        $defaults['config_vars']['cookie_expire_days']     = 30; # in days
        $defaults['config_vars']['cookie_expire_from_now'] = time() + (60 * 60 * 24 * $defaults['config_vars']['cookie_expire_days']); # now +x days
        $defaults['config_vars']['global_json'] = array(
            'env'               => $this->environment,
            'salt'              => $this->defaults['config_vars']['encryption_key'],
            'googleAnalyticsId' => $defaults['config_vars']['google_analytics_id'],
            'cookieSettings'    => array(
                'path'    => $defaults['config_vars']['cookie_path'],
                'domain'  => $defaults['config_vars']['cookie_domain'],
                'expires' => $defaults['config_vars']['cookie_expire_days'],
            ),
            'html' => array(),
        );

        // -----------------------------------------------------------------------------

        $defaults['db_config'] = array(
            'dbdriver' => 'mysql',
            'pconnect' => false,
            'dbprefix' => 'exp_',
            'swap_pre' => 'exp_',
            'db_debug' => true,
            'cache_on' => false,
            'autoinit' => false,
            'char_set' => 'utf8',
            'dbcollat' => 'utf8_general_ci',
            'cachedir' => $this->system_path . 'cache/db_cache/',
        );

        // -----------------------------------------------------------------------------

        $defaults['global_vars'] = array(

        );

        return $defaults;
    }

    /**
     * Merge valid array values to properties
     */
    private function toProps($array, $keep_existing = false)
    {
        $valid_keys = $this->valid_keys;
        foreach ($array as $key => $value) {
            if (in_array($key, $valid_keys) && $value !== null) {
                if (isset($this->$key) && is_array($this->$key) && is_array($value)) {
                    $this->$key = $keep_existing ? $this->mergeRecursively($value, $this->$key) : $this->mergeRecursively($this->$key, $value);
                } else {
                    $this->$key = $keep_existing && isset($this->$key) ? $this->key : $value;
                }
            }
        }
    }

    /**
     * Version of array_merge_recursive without overwriting numeric keys
     *
     * @param  array $array1 Initial array to merge.
     * @param  array ...     Variable list of arrays to recursively merge.
     *
     * @link   http://www.php.net/manual/en/function.array-merge-recursive.php#106985
     * @author Martyniuk Vasyl <martyniuk.vasyl@gmail.com>
     */
    private function mergeRecursively()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        foreach($arrays as $array) {
            reset($base);
            while(list($key, $value) = @each($array)) {
                if(is_array($value) && @is_array($base[$key])) {
                    $base[$key] = $this->mergeRecursively($base[$key], $value);
                }
                else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }

    /**
     * Remove www from a URL string
     */
    private function removeWww($url)
    {
        $url = preg_replace('#^(http(s)?://)?w{$3}\.(\w+\.\w+)#', '$1$3', $url);
        return $url;
    }
}

