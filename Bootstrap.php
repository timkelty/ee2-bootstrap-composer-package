<?php

/**
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
    private $valid_keys = array(
        'db_config',
        'config_vars',
        'global_vars',
    );

    public $app_name               = 'ExpressionEngine 2 Boilerplate';
    public $system_path            = './system/';
    public $debug                  = 0;
    public $min_php_version        = '5.3.14';
    public $default_template_group = 'home';
    public $global_var_prefix      = 'gv_';
    public $defaults               = array();
    public $db_config              = array();
    public $global_vars            = array();
    public $config_vars            = array(
        'app_version'               => '255',
        'license_number'            => '',
        'index_page'                => '',
        'cookie_expiration_in_days' => 30,
        'upload_preferences'        => array(),
        'lang'                      => array(
            'no_results' => 'No results found.',
            'ajax_fail' => 'There was a problem with your request. Please try again.',
        ),
    );

    public function __construct()
    {
        // PHP version
        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            throw new \RuntimeException(
                sprintf('PHP Version %s detected. This ExpressionEngine 2.x Boilerplate requires PHP %s or greater.', PHP_VERSION, $this->min_php_version)
            );
        }

        // Global vars
        // Set this by reference so it changes with our property
        global $assign_to_config;
        if(!isset($assign_to_config['global_vars'])) {
            $assign_to_config['global_vars'] = array();
        }
        $this->global_vars = $this->mergeRecursively($assign_to_config['global_vars'], $this->global_vars);
        $assign_to_config['global_vars'] =& $this->global_vars;

        // Base Vars
        $this->environment               = getenv('APP_ENV') ?: 'development';
        $this->protocol                  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
        $this->host                      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

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
        $this->ee_cache_path     = $this->system_path . 'cache/';
        $this->public_cache_url  = $this->base_url . 'cache/';
        $this->public_cache_path = $this->base_path . 'cache/';

        // Config var defaults
        // We set in the constructor so we can reference them later.
        // This allows us to override one var and change many.
        $this->config_vars = array_merge($this->config_vars, array(
            'server_timezone'           => $this->getTimeZoneCode(),
            'daylight_savings'          => ((bool) date('I')) ? 'y' : 'n',
            'cookie_domain'             => '.' . $this->removeWww($this->host),
            'google_analytics_id'       => ($this->environment == 'production') ? 'UA-XXXXXXX-XX' : '',
            'encryption_key'            => base64_encode(str_rot13($this->app_name)),
            'cookie_expiration'         => time() + (60 * 60 * 24 * $this->config_vars['cookie_expiration_in_days'])
        ));
    }

    /**
     * Get class instance
     * @return obj Class instance
     */
    public static function getInstance()
    {
        if (self::$instance === false) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get property value
     * @param str $name Property name
     */
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
            case 'global_vars':
                $keys = array_keys($this->global_vars);
                foreach ($keys as &$key) {
                    if (strpos($key, $this->global_var_prefix) !== 0) {
                        $key = $this->global_var_prefix . $key;
                    }
                }
                $this->global_vars = array_combine($keys, $this->global_vars);
                break;
            case 'defaults':
                $this->defaults = array(
                    'db_config'   => array(
                        'password' => 'password', # Setting this ensures EE's DB error is shown
                        'dbdriver' => 'mysql',
                        'pconnect' => false,
                        'dbprefix' => 'exp_',
                        'swap_pre' => 'exp_',
                        'db_debug' => true,
                        'cache_on' => false,
                        'autoinit' => false,
                        'char_set' => 'utf8',
                        'dbcollat' => 'utf8_general_ci',
                        'cachedir' => $this->ee_cache_path . 'db_cache/',
                    ),
                    'config_vars' => array(

                        /**
                         * ExpressionEngine/CodeIgniter
                         * @see http://ellislab.com/expressionengine/user-guide/general/hidden_configuration_variables.html
                         * @see http://devot-ee.com/ee-config-vars
                         */

                        // Path/URL settings
                        'index_page'          => $this->config_vars['index_page'],
                        'site_index'          => $this->config_vars['index_page'],
                        'base_url'            => $this->base_url,
                        'site_url'            => $this->base_url,
                        'cp_url'              => $this->base_url . 'cp/index.php',
                        'theme_folder_path'   => $this->base_path   . 'themes/',
                        'theme_folder_url'    => $this->base_url    . 'themes/',
                        'emoticon_path'       => $this->ee_images_url  . 'smileys/',
                        'emoticon_url'        => $this->ee_images_url  . 'smileys/',
                        'captcha_path'        => $this->ee_images_path . 'captchas/',
                        'captcha_url'         => $this->ee_images_url  . 'captchas/',
                        'avatar_path'         => $this->ee_images_path . 'avatars/',
                        'avatar_url'          => $this->ee_images_url  . 'avatars/',
                        'photo_path'          => $this->ee_images_path . 'member_photos/',
                        'photo_url'           => $this->ee_images_url  . 'member_photos/',
                        'sig_img_path'        => $this->ee_images_path . 'signature_attachments/',
                        'sig_img_url'         => $this->ee_images_url  . 'signature_attachments/',
                        'prv_msg_upload_path' => $this->ee_images_path . 'pm_attachments/',
                        'third_party_path'    => $this->vendor_path . 'third_party/',
                        'tmpl_file_basepath'  => $this->template_path . 'ee_templates/',

                        // Debugging settings
                        'is_system_on'       => 'y',
                        'allow_extensions'   => 'y',
                        'email_debug'        => ($this->debug) ? 'y' : 'n',
                        'show_profiler'      => (!$this->debug || (isset($_GET['D']) && $_GET['D'] == 'cp')) ? 'n' : 'y',
                        'template_debugging' => ($this->debug) ? 'y' : 'n',
                        'debug'              => ($this->debug) ? '2' : '1', # 0: no PHP/SQL errors shown. 1: Errors shown to Super Admins. 2: Errors shown to everyone.

                        // Tracking & performance
                        'disable_all_tracking'        => 'y', # If set to 'y' some of the below settings are disregarded
                        'enable_sql_caching'          => 'n',
                        'disable_tag_caching'         => 'n',
                        'enable_online_user_tracking' => 'n',
                        'dynamic_tracking_disabling'  => '500',
                        'enable_hit_tracking'         => 'n',
                        'enable_entry_view_tracking'  => 'n',
                        'log_referrers'               => 'n',
                        'gzip_output'                 => 'n',

                        // Cookies & session
                        'cookie_domain'      => $this->config_vars['cookie_domain'],
                        'cookie_path'        => '',
                        'user_session_type'  => 'c',
                        'admin_session_type' => 'cs',

                        // Localization
                        'daylight_savings'          => $this->config_vars['daylight_savings'],
                        'server_timezone'           => $this->config_vars['server_timezone'],
                        'default_site_dst'          => $this->config_vars['daylight_savings'],
                        'default_site_timezone'     => $this->config_vars['server_timezone'],
                        'time_format'               => 'us',
                        'server_offset'             => '',
                        'allow_member_localization' => 'n',

                        // Member settings
                        'profile_trigger'           => rand(0, time()),
                        'enable_emoticons'          => 'n',
                        'enable_avatars'            => 'n',
                        'enable_photos'             => 'n',
                        'sig_allow_img_upload'      => 'n',
                        'captcha_require_members'   => 'n',
                        'allow_member_registration' => 'n',

                        // URL/Template settings
                        'use_category_name'         => 'y',
                        'reserved_category_word'    => 'category',
                        'word_separator'            => 'dash', # dash|underscore
                        'strict_urls'               => 'y',
                        'site_404'                  => $this->default_template_group . '/404',
                        'save_tmpl_files'           => 'y',
                        'hidden_template_indicator' => '_',
                        'uri_protocol'              => 'PATH_INFO', # AUTO|PATH_INFO|QUERY_STRING|REQUEST_URI|ORIG_PATH_INFO
                        'enable_query_strings'      => TRUE,
                        'permitted_uri_chars'       => 'a-z 0-9~%.:_\\-',

                        // Other
                        'site_label'                => $this->app_name,
                        'encryption_key'            => $this->config_vars['encryption_key'], # random 32 characater string
                        'save_tmpl_revisions'       => 'n',
                        'new_version_check'         => 'n', # no slowing my CP homepage down with this
                        'protect_javascript'        => 'y', # prevents the advanced conditionals parser from processing anything in tags
                        'autosave_interval_seconds' => '0', # 0: disables entry autosave
                        'password_lockout'          => 'n',
                        'cp_theme'                  => 'republic', # Republic CP
                        'install_lock'              => '',
                        'doc_url'                   => "http://ellislab.com/expressionengine/user-guide/",

                        // CodeIgniter
                        'url_suffix'           => '',
                        'language'             => 'english',
                        'charset'              => 'UTF-8',
                        'enable_hooks'         => FALSE,
                        'subclass_prefix'      => 'EE_',
                        'directory_trigger'    => 'D',
                        'controller_trigger'   => 'C',
                        'function_trigger'     => 'M',
                        'log_threshold'        => 0,
                        'log_path'             => '',
                        'log_date_format'      => 'Y-m-d H:i:s',
                        'cache_path'           => '',
                        'global_xss_filtering' => FALSE,
                        'csrf_protection'      => FALSE,
                        'compress_output'      => FALSE,
                        'time_reference'       => 'local',
                        'rewrite_short_tags'   => TRUE,
                        'proxy_ips'            => '',

                        /**
                         * Third party
                         */

                        // CE Image
                        'ce_image_document_root'     => $this->base_path,
                        'ce_image_cache_dir'         => '/cache/made/',
                        'ce_image_remote_dir'        => '/cache/remote/',
                        'ce_image_memory_limit'      => 64,
                        'ce_image_remote_cache_time' => 1440,
                        'ce_image_quality'           => 90,
                        'ce_image_disable_xss_check' => 'no',

                        // Playa
                        'playa_site_index' => $this->base_url,

                        // Minimee
                        'minimee_cache_path'  => $this->public_cache_path,
                        'minimee_cache_url'   => $this->public_cache_url,
                        'minimee_base_path'   => $this->base_path,
                        'minimee_base_url'    => $this->base_url,
                        'minimee_debug'       => 'n',
                        'minimee_disable'     => 'n',
                        'minimee_remote_mode' => 'auto', # auto/curl/fgc
                        'minimee_minify_html' => 'yes',

                        // Assets
                        'assets_site_url' => '/index.php',
                        'assets_cp_path'  => $this->system_path,

                        // Low Variables
                        'low_variables_save_as_files' => 'y',
                        'low_variables_file_path'     => $this->template_path . 'low_variables/',

                        // Stash
                        'stash_file_basepath' => $this->template_path . 'stash_templates/',
                        'stash_file_sync'     => ($this->environment == 'production') ? false : true,

                        /**
                         * Custom
                         */
                        'google_analytics_id'       => $this->config_vars['google_analytics_id'],
                        'cookie_expiration_in_days' => $this->config_vars['cookie_expiration_in_days'],
                        'cookie_expiration'         => $this->config_vars['cookie_expiration'],
                        'lang'                      => $this->config_vars['lang'],
                        'json'               => array(
                            'env'               => $this->environment,
                            'encryptionKey'     => $this->config_vars['encryption_key'],
                            'googleAnalyticsId' => $this->config_vars['google_analytics_id'],
                            'lang'              => $this->camelCaseKeys($this->config_vars['lang']),
                            'cookieSettings'    => array(
                                'domain'           => $this->config_vars['cookie_domain'],
                                'expirationInDays' => $this->config_vars['cookie_expiration_in_days'],
                                'expiration'       => $this->config_vars['cookie_expiration'],
                            ),
                        ),
                    ),
                    'global_vars' => array(
                        'base_url'      => $this->base_url, # because site_url is parsed late
                        'date_fmt'      => '%F %j, %Y',
                        'date_fmt_time' => '%g:%i %a',
                        'date_fmt_full' => '%F %j %Y, %g:%i %a',
                    ),
                );
                break;
        }

        // Return name or error
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            throw new \InvalidArgumentException(
                sprintf('The property "%s" does not exist.', $name)
            );
        }
    }

    /**
     * Read config file
     * @param  string  $file    Full path to config file
     * @param  boolean $require Throw exception if file does not exist
     * @return array            Config values
     */
    private function readConfigFile($file, $require = false)
    {
        if (!file_exists($file)) {
            return array();
        }
        $file_parts = pathinfo($file);
        switch ($file_parts['extension']) {
            case 'yml':
            case 'yaml':
                $vars = Yaml::parse($file) ?: $vars;
                break;
            case 'ini':
                $vars = parse_ini_file($file, true) ?: $vars;
                break;
            case 'json':
                $json = file_get_contents($file);
                $json = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $vars = $json;
                } else {
                    throw new \RuntimeException(
                        sprintf('Invalid JSON (error code: "%s") in "%s".', json_last_error(), $file)
                    );
                }
                break;
            case 'php':
                require $file;
                break;
        }

        // Only return environmental vars
        return isset($vars[$this->environment]) ? $vars[$this->environment] : array();
    }

    /**
     * Apply config files
     *
     * Although config file values take precedence, we apply them first so
     * that we can reference their values in our defaults.
     *
     * @param array $files Full paths of config files
     */
    public function applyConfig($files)
    {
        // Files
        foreach ($files as $file) {
            $this->toProps($this->readConfigFile($file));
        }

        // Defaults
        $this->toProps($this->get('defaults'), true);

        // Global vars
        $this->global_vars = $this->get('global_vars');
    }

    /**
     * Merge valid array values to properties
     * @param array   $array
     * @param boolean $keep_existing If properties are set, to not overwrite them.
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
     * Remove "www." from a URL string
     * @param string $url
     */
    private function removeWww($url)
    {
        $url = preg_replace('#^(http(s)?://)?w{$3}\.(\w+\.\w+)#', '$1$3', $url);

        return $url;
    }

    /**
     * Get EE/CI Timezone code
     * @return string timezone code (e.g. UM5)
     */
    private function getTimeZoneCode()
    {
        $date = new \DateTime('now');
        $offset = $date->getOffset() / 60 / 60;
        $dst = (bool) date('I');
        $code = 'UTC';

        if ($dst) {
            $offset -= 1;
        }
        if ($offset < 0) {
            $code = 'UM';
        } elseif ($offset > 0) {
            $code = 'UP';
        }
        if ($offset !== 0) {
            $code .= str_replace('.', '', abs($offset));
        }

        return $code;
    }

    /**
     * Convert under_score type array's keys to camelCase type array's keys
     * @param  array $array           array to convert
     * @param  array $arrayHolder     parent array holder for recursive array
     * @return array
     */
    private function camelCaseKeys($array, $arrayHolder = array())
    {
        $camelCaseArray = !empty($arrayHolder) ? $arrayHolder : array();
        foreach ($array as $key => $val) {
            $newKey = @explode('_', $key);
            array_walk($newKey, create_function('&$v', '$v = ucwords($v);'));
            $newKey = @implode('', $newKey);
            $newKey{0} = strtolower($newKey{0});
            if (!is_array($val)) {
                $camelCaseArray[$newKey] = $val;
            } else {
                $camelCaseArray[$newKey] = $this->camelCaseKeys($val, $camelCaseArray[$newKey]);
            }
        }

        return $camelCaseArray;
    }
}

