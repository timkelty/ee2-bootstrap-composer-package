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
    private $allowed_keys    = array('database_config', 'config', 'global_vars', 'debug');
    private $debug           = 0;

    // Other public properties are set in setDefaults
    public $environment = 'development';
    public $config      = array('upload_preferences' => array());
    public $database    = array();
    public $global_vars = array();

    public function __construct()
    {
        if (getenv('APP_ENV')) {
            $this->environment = getenv('APP_ENV');
        }

        // Set this by reference so it changes with our property
        global $assign_to_config;
        if(isset($assign_to_config['global_vars'])) {
            $this->global_vars = $assign_to_config['global_vars'];
        }
        $assign_to_config['global_vars'] =& $this->global_vars;
    }

    /**
     * Normalize properties on get
     */
    public function __get($property) {
        if (property_exists($this, $property)) {
            switch ($property) {
                case 'debug':
                    $this->$property = preg_match('/^n|0/', $this->$property) ? 0 : 1;
                    break;
            }
            return $this->$property;
        }
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

    public function getDatabaseConfig()
    {
        // Rails/Capistrano database.yml compatibility
        if (isset($this->database_config['host'])) {
            $this->database_config['hostname'] = $this->database_config['host'];
            unset($this->database_config['host']);
        }

        return $this->database_config;
    }

    public function getConfig()
    {
        // Upload preferences
        // If not an array, we assume path and url relative to base_path
        foreach ($this->config['upload_preferences'] as $key => &$dir) {
            if (!is_array($dir)) {
                $dir = array(
                    'server_path' => $this->config['base_path'] . $dir,
                    'url' => $dir,
                );
            }
            $dir['server_path'] = $this->createPath($dir['server_path']);

            // Prefix with site url or root-relative slash and ensure trailing slash
            $prefix = $this->config['root_relative_dirs'] ? '/' : $this->config['base_url'];
            $dir['url'] = trim($dir['url'], '/');
            if (!parse_url($dir['url'], PHP_URL_SCHEME)) {
                $dir['url'] = $prefix . $dir['url'];
            }
            $dir['url'] = $dir['url'] . '/';
        }

        // Normalize to string to avoid version error
        $this->config['debug'] = (string) $this->config['debug'];

        if (isset($this->config['app_version'])) {
            $this->config['app_version'] = (string) $this->config['app_version'];
        }

        return $this->config;
    }

    public function getGlobalVars()
    {
        $prefix = isset($this->config['global_var_prefix']) ? $this->config['global_var_prefix'] : false;
        foreach ($this->global_vars as $var_name => &$var) {

            // Arrays to json
            if (is_array($var)) {
                $var = json_encode($var);
            }

            // Prefix var names
            if ($prefix && strpos($var_name, $prefix) !== 0) {
                $this->global_vars[$prefix . $var_name] = $var;
                unset($this->global_vars[$var_name]);
            }
        }

        return $this->global_vars;
    }

    /**
     * Apply config files
     *
     * Although config file values take precedence, we apply them first so
     * that we can reference their values in our defaults.
     *
     * @param array $files Full paths of config files
     */
    public function setConfig()
    {
        // File or array
        $config_items = func_get_args();
        foreach ($config_items as $config) {
            if (is_array($config)) {
                $this->set($config);
            } else {
                $this->set($this->readConfigFile($config));
            }
        }

        // Defaults
        $this->setDefaults();

        // Global vars
        $this->global_vars = $this->getGlobalVars();
    }

    /**
     * Merge array values to properties
     * @param array   $array
     * @param boolean $overwrite Existing properties will be overwritten
     */
    private function set($array, $overwrite = true)
    {
        foreach ($array as $key => $value) {

            // Prevent settings arbitrary properties
            // Check for null, empty YAML nodes will return null
            if ($value !== null && in_array($key, $this->allowed_keys)) {
                if (isset($this->$key) && is_array($this->$key) && is_array($value)) {
                    $this->$key = $overwrite ? $this->merge($this->$key, $value) : $this->merge($value, $this->$key);
                } elseif ($overwrite) {
                    $this->$key = $value;
                }
            }
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
        $vars = array();
        switch ($file_parts['extension']) {
            case 'yml':
            case 'yaml':
                $vars = Yaml::parse($file);
                break;
            case 'ini':
                $vars = parse_ini_file($file, true);
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

        // Merge environmental vars into defaults
        $env_vars = isset($vars[$this->environment]) ? $vars[$this->environment] : array();
        $vars = array_intersect_key($vars, array_flip($this->allowed_keys));

        return $this->merge($vars, $env_vars);
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
    private function merge()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        foreach($arrays as $array) {
            reset($base);
            while(list($key, $value) = @each($array)) {

                if(is_array($value) && @is_array($base[$key])) {
                    $base[$key] = $this->merge($base[$key], $value);
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
     * Get PHP Timezone code
     * @param  string $datetime A date/time string
     * @return string           timezone code (e.g. America/Detroit)
     */
    private function getTimeZoneCode($datetime = 'now')
    {
        $date = new \DateTime($datetime);
        $tz = $date->getTimezone();
        return $tz->getName();
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

    private function createPath($path, $remove_base_path = false)
    {
        $path = $this->removeDoubleSlashes($path);

        // Expand path
        if (realpath($path)) {
            $path = realpath($path);
        }

        // Trim off the base path off
        if ($remove_base_path && isset($this->config['base_path'])) {
            $pattern = '@^' . preg_quote($this->config['base_path'], '/') . '@';
            $path = preg_replace($pattern, '', $path);
            $path = '/' . ltrim($path, '/');
        }

        return rtrim($path, '/') . '/';
    }

    /**
     * Removes double slashes, except when they are preceded by ':', so that 'http://', etc are preserved.
     *
     * @param string $str The string from which to remove the double slashes.
     * @return string The string with double slashes removed.
     */
    private function removeDoubleSlashes($str)
    {
        return preg_replace( '#(?<!:)//+#', '/', $str );
    }

    /**
     * Get last deployed date, by looking for the Capistrano release directory
     */
    private function getLastDeployDate()
    {
        $realpath = realpath($_SERVER['DOCUMENT_ROOT']);
        $date = preg_match('@\d{14}@', $realpath, $matches);
        $date = !empty($matches) ? $matches[0] : time();

        return $date;
    }

    /**
     * Set defaults, but do not overwrite exiting values.
     * Order is important here, since we can't use properties until they're set.
     * Saving properties in stages lets us use the results in subsequent values.
     * @see http://ellislab.com/expressionengine/user-guide/general/hidden_configuration_variables.html
     * @see http://devot-ee.com/ee-config-vars
     */
    private function setDefaults()
    {
        $this->set(array(
            'config' => array(
                'dev_mode'                  => $this->environment != "production",
                'project_path'              => $this->createPath(realpath($_SERVER['DOCUMENT_ROOT'] . '/..')),
                'protocol'                  => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://',
                'host'                      => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'],
                'debug'                     => 1, # 0: no PHP/SQL errors shown. 1: Errors shown to Super Admins. 2: Errors shown to everyone.
                'index_page'                => '',
                'server_timezone'           => $this->getTimeZoneCode(),
                'daylight_savings'          => ((bool) date('I')) ? 'y' : 'n',
                'default_template_group'    => 'pages',
                'global_var_prefix'         => 'gv_',
                'reserved_category_word'    => 'category',
                'upload_dir_name'           => 'storage',
                'root_relative_dirs'        => true,
                'site_name'                 => 'ExpressionEngine 2 Boilerplate',
                'cookie_expiration_in_days' => 30,
                'min_php_version'           => '5.3.3',
                'license_number'            => '',
                'last_deploy_date'          => $this->getLastDeployDate(),
                'upload_preferences'        => array(),
                'lang'                      => array(
                    'no_results' => 'No results found.',
                    'ajax_fail'  => 'There was a problem with your request. Please try reload and try again.',
                ),
            ),
        ), false);

        $this->set(array(
            'config' => array(
                'app_path'          => $this->config['project_path'] . 'app/',
                'vendor_path'       => $this->config['project_path'] . 'vendor/',
                'config_path'       => $this->config['project_path'] . 'config/',
                'base_path'         => $this->config['project_path'] . 'public/',
                'base_url'          => $this->config['protocol'] . $this->config['host'] . '/',
                'encryption_key'    => base64_encode(str_rot13($this->config['site_name'])),
                'cookie_expiration' => time() + (60 * 60 * 24 * $this->config['cookie_expiration_in_days']),
                'cookie_domain'     => '.' . $this->removeWww($this->config['host']),
                'enable_analytics'  => !$this->config['dev_mode'],
                'enable_indexing'   => !$this->config['dev_mode'],
            ),
        ), false);

        $this->set(array(
            'config' => array(
                'system_path'         => $this->config['vendor_path'] . 'ee/system/',
                'public_storage_path' => $this->config['base_path'] . $this->config['upload_dir_name'] . '/',
                'public_storage_url'  => $this->config['base_url'] . $this->config['upload_dir_name'] . '/',
            ),
        ), false);

        $this->set(array(
            'config' => array(
                'ee_cache_path'     => $this->config['system_path'] . 'cache/',
                'public_cache_path' => $this->config['public_storage_path'] . 'cache/',
                'public_cache_url'  => $this->config['public_storage_url'] . 'cache/',
                'ee_images_path'    => $this->config['public_storage_path'] . 'members/',
                'ee_images_url'     => $this->config['public_storage_url'] . 'members/',
            ),
        ), false);

        $this->set(array(
            'database_config'   => array(
                'password' => 'password', # Setting this ensures EE's DB error is shown if no database is set
                'dbdriver' => 'mysql',
                'pconnect' => false,
                'dbprefix' => 'exp_',
                'swap_pre' => 'exp_',
                'db_debug' => true,
                'cache_on' => false,
                'autoinit' => false,
                'char_set' => 'utf8',
                'dbcollat' => 'utf8_general_ci',
                'cachedir' => $this->config['ee_cache_path'] . 'db_cache/',
            ),
            'config' => array(

                // Path/URL settings
                'site_index'          => $this->config['index_page'],
                'site_url'            => $this->config['base_url'],
                'cp_url'              => $this->config['base_url'] . 'cp/index.php',
                'theme_folder_path'   => $this->config['base_path']   . 'themes/',
                'theme_folder_url'    => $this->config['base_url']    . 'themes/',
                'emoticon_path'       => $this->config['ee_images_path']  . 'smileys/',
                'emoticon_url'        => $this->config['ee_images_url']  . 'smileys/',
                'captcha_path'        => $this->config['ee_images_path'] . 'captchas/',
                'captcha_url'         => $this->config['ee_images_url']  . 'captchas/',
                'avatar_path'         => $this->config['ee_images_path'] . 'avatars/',
                'avatar_url'          => $this->config['ee_images_url']  . 'avatars/',
                'photo_path'          => $this->config['ee_images_path'] . 'member_photos/',
                'photo_url'           => $this->config['ee_images_url']  . 'member_photos/',
                'sig_img_path'        => $this->config['ee_images_path'] . 'signature_attachments/',
                'sig_img_url'         => $this->config['ee_images_url']  . 'signature_attachments/',
                'prv_msg_upload_path' => $this->config['ee_images_path'] . 'pm_attachments/',
                'prv_msg_upload_url'  => $this->config['ee_images_url'] . 'pm_attachments/',
                'third_party_path'    => $this->config['vendor_path'] . 'ee/third_party/',
                'tmpl_file_basepath'  => $this->config['app_path'] . 'templates/',

                // Debugging settings
                'is_system_on'       => 'y',
                'allow_extensions'   => 'y',
                'email_debug'        => ($this->__get('debug')) ? 'y' : 'n',
                'show_profiler'      => (!$this->__get('debug') || (isset($_GET['D']) && $_GET['D'] == 'cp')) ? 'n' : 'y',
                'template_debugging' => ($this->__get('debug')) ? 'y' : 'n',

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
                'cookie_path'        => '',
                'user_session_type'  => 'c',
                'admin_session_type' => 'c',

                // Localization
                'default_site_dst'          => $this->config['daylight_savings'],
                'default_site_timezone'     => $this->config['server_timezone'],
                'tz_country'                => 'us',
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
                'word_separator'            => 'dash', # dash|underscore
                'strict_urls'               => 'y',
                'site_404'                  => $this->config['default_template_group'] . '/404',
                'save_tmpl_files'           => 'y',
                'hidden_template_indicator' => '_',
                'uri_protocol'              => 'PATH_INFO', # AUTO|PATH_INFO|QUERY_STRING|REQUEST_URI|ORIG_PATH_INFO
                'enable_query_strings'      => TRUE,
                'permitted_uri_chars'       => 'a-z 0-9~%.:_\\-',

                // Other
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

                // CE Image
                'ce_image_document_root'     => $this->config['base_path'],
                'ce_image_cache_dir'         => $this->createPath($this->config['public_cache_path'] . 'made/', true),
                'ce_image_remote_dir'        => $this->createPath($this->config['public_cache_path'] . 'remote/', true),
                'ce_image_memory_limit'      => 64,
                'ce_image_remote_cache_time' => 1440,
                'ce_image_quality'           => 90,
                'ce_image_interlace'         => 'yes',
                'ce_image_disable_xss_check' => 'no',

                // Playa
                'playa_site_index' => $this->config['base_url'],

                // Minimee
                'minimee' => array(
                    'base_path'   => $this->config['base_path'],
                    'cache_path'  => $this->config['public_cache_path'],
                    'cache_url'   => $this->config['public_cache_url'],
                    'minify_html' => 'yes',
                    'disable'     => $this->config['dev_mode'] ? 'yes' : 'no',
                ),

                // Assets
                'assets_site_url' => '/index.php',
                'assets_cp_path'  => $this->config['system_path'],

                // Low Variables
                'low_variables_save_as_files' => 'y',
                'low_variables_file_path'     => $this->config['app_path'] . 'low_variables/',

                // Stash
                'stash_file_basepath' => $this->config['app_path'] . 'stash_templates/',
                'stash_file_sync'     => $this->config['dev_mode'],

            ),
            'global_vars' => array(
                'environment'            => $this->environment,
                'dev_mode'               => $this->config['dev_mode'],
                'enable_analytics'       => $this->config['enable_analytics'],
                'enable_indexing'        => $this->config['enable_indexing'],
                'base_url'               => $this->config['base_url'], # because site_url is parsed late
                'reserved_category_word' => $this->config['reserved_category_word'],
                'default_template_group' => $this->config['default_template_group'],
                'date_fmt'               => '%F %j, %Y',
                'date_fmt_time'          => '%g:%i %a',
                'date_fmt_full'          => '%F %j %Y, %g:%i %a',
                'json'                   => array(
                    'environment'     => $this->environment,
                    'devMode'         => $this->config['dev_mode'],
                    'enableAnalytics' => $this->config['enable_analytics'],
                    'enableIndexing'  => $this->config['enable_indexing'],
                    'encryptionKey'   => $this->config['encryption_key'],
                    'lang'            => $this->camelCaseKeys($this->config['lang']),
                    'lastDeployDate'  => $this->config['last_deploy_date'],
                    'cookieSettings'  => array(
                        'domain'           => $this->config['cookie_domain'],
                        'expirationInDays' => $this->config['cookie_expiration_in_days'],
                        'expiration'       => $this->config['cookie_expiration'],
                    ),
                ),
            )
        ), false);
    }
}

