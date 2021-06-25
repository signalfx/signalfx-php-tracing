<?php

namespace DDTrace\Integrations\Drupal;

class DrupalCommon
{
    private static $drupalLangCodes = array(
        'af' => true,
        'am' => true,
        'ar' => true,
        'ast' => true,
        'az' => true,
        'be' => true,
        'bg' => true,
        'bn' => true,
        'bo' => true,
        'bs' => true,
        'ca' => true,
        'cs' => true,
        'cy' => true,
        'da' => true,
        'de' => true,
        'dz' => true,
        'el' => true,
        'en' => true,
        'eo' => true,
        'es' => true,
        'et' => true,
        'eu' => true,
        'fa' => true,
        'fi' => true,
        'fil' => true,
        'fo' => true,
        'fr' => true,
        'fy' => true,
        'ga' => true,
        'gd' => true,
        'gl' => true,
        'gsw-berne' => true,
        'gu' => true,
        'he' => true,
        'hi' => true,
        'hr' => true,
        'ht' => true,
        'hu' => true,
        'hy' => true,
        'id' => true,
        'is' => true,
        'it' => true,
        'ja' => true,
        'jv' => true,
        'ka' => true,
        'kk' => true,
        'km' => true,
        'kn' => true,
        'ko' => true,
        'ku' => true,
        'ky' => true,
        'lo' => true,
        'lt' => true,
        'lv' => true,
        'mg' => true,
        'mk' => true,
        'ml' => true,
        'mn' => true,
        'mr' => true,
        'ms' => true,
        'my' => true,
        'ne' => true,
        'nl' => true,
        'nb' => true,
        'nn' => true,
        'oc' => true,
        'pa' => true,
        'pl' => true,
        'pt-pt' => true,
        'pt-br' => true,
        'ro' => true,
        'ru' => true,
        'sco' => true,
        'se' => true,
        'si' => true,
        'sk' => true,
        'sl' => true,
        'sq' => true,
        'sr' => true,
        'sv' => true,
        'sw' => true,
        'ta' => true,
        'ta-lk' => true,
        'te' => true,
        'th' => true,
        'tr' => true,
        'tyv' => true,
        'ug' => true,
        'uk' => true,
        'ur' => true,
        'vi' => true,
        'zh-hans' => true,
        'zh-hant' => true
    );

    public static function normalizeRoute($route)
    {
        $route = trim($route);

        if ($route == '') {
            return '/';
        }

        if ($route[0] == '/') {
            $route = substr($route, 1);
        }

        $parts = explode('/', $route);
        $partCount = count($parts);

        if (self::isLanguage($parts[0])) {
            $parts[0] = '{lang}';
        }

        if ($partCount >= 3) {
            $parts[$partCount - 1] = '*';
        }

        return '/' . implode('/', $parts);
    }

    private static function isLanguage($lang)
    {
        return isset(self::$drupalLangCodes[$lang]);
    }
}
