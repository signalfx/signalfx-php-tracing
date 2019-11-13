<?php

namespace DDTrace\Util;

final class HexConversion
{
    public static function idToHex($id)
    {
        return str_pad(dd_trace_dec_hex($id), 16, "0", STR_PAD_LEFT);
    }
}
