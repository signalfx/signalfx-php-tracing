--TEST--
dd_trace_dec_hex() converts 64 uint strings to hexadecimal strings
--FILE--
<?php
echo dd_trace_dec_hex('0') . "\n";
echo dd_trace_dec_hex('100') . "\n";
echo dd_trace_dec_hex('1152921504606846976') . "\n";
echo dd_trace_dec_hex('9223372036854775807') . "\n";
echo dd_trace_dec_hex('9223372036854775808') . "\n";
echo dd_trace_dec_hex('18446744073709551614') . "\n";
echo dd_trace_dec_hex('18446744073709551615') . "\n";
echo dd_trace_dec_hex('NOT A VALID DEC') . "\n";
echo dd_trace_dec_hex('') . "\n";
?>
--EXPECT--
0
64
1000000000000000
7fffffffffffffff
8000000000000000
fffffffffffffffe
ffffffffffffffff
0
0