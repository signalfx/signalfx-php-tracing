<?php

namespace DDTrace\Util;

final class HexConversion
{
    public static function idToHex($id)
    {
        return str_pad(dechex($id), 16, "0", STR_PAD_LEFT);
    }
}
