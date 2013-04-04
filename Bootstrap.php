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

    public function __construct($app_root = null)
    {
        // PHP version
        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            throw new \RuntimeException(
                sprintf('PHP Version %s detected. This ExpressionEngine 2.x Boilerplate requires PHP %s or greater.', PHP_VERSION, $this->min_php_version)
            );
        }

    }

    /**
     * Get class instance
     * @return obj Class instance
     */
    public static function getInstance($app_root = null)
    {
        if (self::$instance === false) {
            self::$instance = new self($app_root);
        }

        return self::$instance;
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
        return isset($vars[$this->data['environment']]) ? $vars[$this->data['environment']] : array();
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
            $this->save($this->readConfigFile($file));
        }

        // Defaults
        $this->save($this->get('defaults'), false);

        // Global vars
        $this->global_vars = $this->get('global_vars');
    }

    /**
     * Merge valid array values to properties
     * @param array   $array
     * @param boolean $overwrite Existing properties will be overwritten
     */
    private function save($array, $overwrite = true)
    {
        foreach ($array as $key => $value) {

            // Check for null, empty YAML nodes will return null
            if ($value !== null) {
                if (isset($this->data[$key]) && is_array($this->data[$key]) && is_array($value)) {
                    $this->data[$key] = $overwrite ? $this->merge($this->data[$key], $value) : $this->merge($value, $this->data[$key]);
                } else {
                    $this->data[$key] = ($overwrite || !isset($this->data[$key])) ? $value : $this->data[$key];
                }
            }
        }
    }

    /**
     * Version of array_merge_recursive without overwriting numeric keys
     * Keys that begin with replace_key_prefix will be replaced, not merged.
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
        $replace_key_prefix = $this->replace_key_prefix;

        foreach($arrays as $array) {
            reset($base);
            while(list($key, $value) = @each($array)) {

                // Replace keys that start with replace_key_prefix, not merge
                $key_arr = str_split($key);
                $replace = array_shift($key_arr) == $replace_key_prefix;
                if ($replace_key_prefix !== false) {
                    if ($replace) {
                        $key = implode('', $key_arr);
                    }
                }

                if(!$replace && is_array($value) && @is_array($base[$key])) {
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

