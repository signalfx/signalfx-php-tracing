<?php

namespace DDTrace\Util;

final class HexConversion
{
    public static function idToHex($id)
    {
        $hex = dd_trace_dec_hex((string) $id);
        return str_pad($hex, 16, "0", STR_PAD_LEFT);
    }

    public static function hexToInt($hex)
    {
        if (strlen($hex) > 16) {
            throw new \InvalidArgumentException(
                '\HexConversion::hexToInt() arg must be hexstring with <= 16 characters'
            );
        }

        if (strlen($hex) == 16 && strpos("89abcdef", $hex[0]) !== false) {
            // if the first character of a 16 char long hex string is 8-f,
            // then it's over PHP_INT_MAX, so needs to be bit shifted right once
            // per original dd_trace_generate_id implementation:
            // ```return (long long)(genrand64_int64() >> 1);```
            $bit_rep = "";
            for ($i = 0; $i < strlen($hex); $i++) {
                $bit_rep .= str_pad(base_convert($hex[$i], 16, 2), 4, "0", STR_PAD_LEFT);
            }
            $bit_rep = "0" . substr($bit_rep, 0, 63);
            return (int) base_convert($bit_rep, 2, 10);
        }

        return (int) dd_trace_hex_dec($hex);
    }
}
