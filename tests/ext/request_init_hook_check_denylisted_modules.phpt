--TEST--
Do not prepend request hook if offending module has been detected
--INI--
ddtrace.request_init_hook=tests/ext/simple_sanity_check.phpt
ddtrace.internal_denylisted_modules_list=signalfx_tracing,some_other_module
--FILE--
<?php
echo "Request start" . PHP_EOL;

?>
--EXPECT--
Found denylisted module: signalfx_tracing, disabling conflicting functionality
Request start
