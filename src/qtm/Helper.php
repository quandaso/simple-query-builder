<?php

namespace Qtm;

class Helper
{
    /**
     * Dumps var
     * @param $var
     */
    public static function pr() {
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
    public static function dd() {
        call_user_func_array('self::pr', func_get_args());
        die;
    }

    /**
     * Converts a string to camelCase
     * @param $string
     * @param string $delimiter
     * @return mixed|string
     */
    public static function strCamelCase($string, $delimiter = '-')
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
    public static function strRandom ($length = 10, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {

        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * @param array $args
     * @return array
     */
    public static function flattenArray($args)
    {
        if (!is_array($args)) {
            $args = [$args];
        }

        $result = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach (self::flattenArray($arg) as $v) {
                    $result[] = $v;
                }
            } else {
                $result[] = $arg;
            }
        }

        return $result;
    }

    /**
     * Converts a string to from camelCase to underscore_case
     * @param $input
     * @return string
     */
    public static function camelCaseToUnderscore($input)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    /**
     * Gets current time
     * @return bool|string
     */
    public static function now()
    {
        return date('Y-m-d H:i:s');
    }
}