--TEST--
Test SignalFX environment variable capturing
--ENV--
SIGNALFX_MODE=1
SIGNALFX_CAPTURE_ENV_VARS=ONE,TWO
ONE=sample
TWO=yes
THREE=no
--FILE--
<?php
var_dump(DDTrace\active_span()->meta);
?>
--EXPECTF--
array(4) {
  ["system.pid"]=>
  int(%d)
  ["component"]=>
  string(11) "web.request"
  ["php.env.one"]=>
  string(6) "sample"
  ["php.env.two"]=>
  string(3) "yes"
}
