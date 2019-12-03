--TEST--
dd_trace_hex_dec() converts 64 bit unsigned int represented as a hexadecimal string to decimal string representation.
--FILE--
<?php
echo dd_trace_hex_dec('0') . "\n";
echo dd_trace_hex_dec('64') . "\n";
echo dd_trace_hex_dec('0000000000000000') . "\n";
echo dd_trace_hex_dec('0000000000000001') . "\n";
echo dd_trace_hex_dec('1000000000000000') . "\n";
echo dd_trace_hex_dec('7fffffffffffffff') . "\n";
echo dd_trace_hex_dec('8000000000000000') . "\n";
echo dd_trace_hex_dec('fffffffffffffffe') . "\n";
echo dd_trace_hex_dec('ffffffffffffffff') . "\n";
echo dd_trace_hex_dec('111111111111111111111') . "\n";
echo dd_trace_hex_dec('NOT A VALID HEX') . "\n";
echo dd_trace_hex_dec('') . "\n";
?>
--EXPECT--
0
100
0
1
1152921504606846976
9223372036854775807
9223372036854775808
18446744073709551614
18446744073709551615
18446744073709551615
0
0
