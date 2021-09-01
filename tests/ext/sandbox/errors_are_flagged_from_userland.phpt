--TEST--
Errors from userland will be flagged on span
--SKIPIF--
<?php if (PHP_VERSION_ID < 70000) die('skip: Test requires internal spans'); ?>
--ENV--
DD_TRACE_GENERATE_ROOT_SPAN=0
--FILE--
<?php
use DDTrace\SpanData;

function testErrorFromUserland()
{
    echo "testErrorFromUserland()\n";
}

DDTrace\trace_function('testErrorFromUserland', function (SpanData $span) {
    $span->name = 'testErrorFromUserland';
    $span->meta = ['sfx.error.message' => 'Foo error'];
});

testErrorFromUserland();

var_dump(dd_trace_serialize_closed_spans());
?>
--EXPECTF--
testErrorFromUserland()
array(1) {
  [0]=>
  array(9) {
    ["trace_id"]=>
    string(%d) "%d"
    ["span_id"]=>
    string(%d) "%d"
    ["start"]=>
    int(%d)
    ["duration"]=>
    int(%d)
    ["name"]=>
    string(21) "testErrorFromUserland"
    ["resource"]=>
    string(21) "testErrorFromUserland"
    ["error"]=>
    int(1)
    ["meta"]=>
    array(2) {
      ["sfx.error.message"]=>
      string(9) "Foo error"
      ["system.pid"]=>
      string(%d) "%d"
    }
    ["metrics"]=>
    array(1) {
      ["php.compilation.total_time_ms"]=>
      float(%f)
    }
  }
}
