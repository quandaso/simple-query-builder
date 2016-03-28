<?php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}


/**
 * Dumps var
 * @param $var
 */
function pr() {
    $args = func_get_args();
    $inCommandline = (php_sapi_name() === 'cli');

    if (!$inCommandline) {
        echo '<pre>' . "\n";
    }

    $argsCount = func_num_args();

    foreach ($args as $i => $var) {

        if ($argsCount > 1) {
            echo ($i + 1) . ".";
        }

        if (is_array($var)) {
            echo '(array)' . json_encode($var, JSON_PRETTY_PRINT);
        } else if (is_object($var)) {
            echo '(' . get_class($var).')' . json_encode($var, JSON_PRETTY_PRINT);
        } else {
            var_dump($var);
        }

        echo "\n";
    }

    if (!$inCommandline) {
        echo '</pre>';
    }

}

/**
 * Dumps vars then die
 */
function dd() {
    call_user_func_array('pr', func_get_args());
    die;
}


/**
 * VD:
 * $array = [
 *      [ 'id' => 1, 'name' => 'A', 'email' => 'test1@mail.com'],
 *      [ 'id' => 2, 'name' => 'B', 'email' => 'test2@mail.com'],
 *      [ 'id' => 3, 'name' => 'C', 'email' => 'test3@mail.com'],
 *      [ 'id' => 4, 'name' => 'D', 'email' => 'test4@mail.com']
 *  ];
 *
 * $lists = array_to_list($array, 'id', 'email');
 * Kết quả $lists : [1 => 'test1@gmail.com', 2 => 'test2@gmail.com', 3 => 'test3@gmail.com',4 => 'test4@gmail.com']
 *
 * $lists = array_to_list($array, 'id', true);
 * Kết quả $lists : [1 => true, 2 => true, 3 => true, 4 => true]
 *
 * $lists = array_to_list($array, 'id', function($e) { return $e['id'] * 2; });
 * Kết quả $lists : [1 => 1, 2 => 4, 3 => 6, 4 => 8]
 *
 * @param array $array
 * @param callable | string $key
 * @param callable | string $value
 * @return array
 */
function array_to_list(array $array, $key, $value = null)
{
    $lists = [];

    foreach ($array as $i => $e) {
        $k = $key;
        $v = $value;

        if (is_string ($key)) {
            $k = $e[$key];
        } else if (is_callable($key)) {
            $k = $key($e, $i);
        }

        if (is_string ($value)) {
            $v = $e[$value];
        } else if (is_callable($value)) {
            $v = $value($e, $i);
        } else if ($value === null) {
            $v = $e;
        }

        $lists[$k] = $v;

    }

    return $lists;

}

/**
 * Gets current MySQL timestamp
 * @return string
 */
function current_timestamp()
{
    return date('Y-m-d H:i:s');
}

/**
 * Gets current MySQL date
 * @return string
 */
function current_date()
{
    return date('Y-m-d');
}

/**
 * Converts a string to camelCase
 * @param $string
 * @param string $delimiter
 * @return mixed|string
 */
function str_camel_case($string, $delimiter = '-')
{
    $str = str_replace(' ', '', ucwords(str_replace($delimiter, ' ', $string)));
    $str = lcfirst($str);
    return $str;
}


/**
 * Gets random string with given length
 * @param int $length
 * @param $characters
 * @return string
 */
function str_random ($length = 10, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {

    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}


/**
 * Gets client os
 * @return string
 */
function get_client_os()
{
    $ua = $_SERVER['HTTP_USER_AGENT'];

    if (strpos($ua, "iPhone")){
        return 'ios';
    } elseif(strpos($ua, "Android")){
        return 'android';
    }

    return '';
}

/**
 * Generates unique id v4
 * @return string
 */
function uuid_v4 () {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}


/**
 * @param array $args
 * @return array
 */
function flat_array(array $args)
{
    $result = [];
    foreach ($args as $arg) {
        if (is_array($arg)) {
            foreach (flat_array($arg) as $v) {
                $result[] = $v;
            }
        } else {
            $result[] = $arg;
        }
    }

    return $result;
}

function camel_case_to_underscore($input) {
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
        $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);
}