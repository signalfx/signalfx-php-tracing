--TEST--
Test DD span to SignalFX conversion
--ENV--
SIGNALFX_MODE=1
SIGNALFX_SERVICE=sfx_service
--FILE--
<?php

use function DDTrace\Testing\sfxtrace_ddspan_to_sfx_array;

var_dump(sfxtrace_ddspan_to_sfx_array(array(
    "trace_id" => "123454654652120554",
    "span_id" => "9456312756545651",
    "parent_id" => "112654",
    "start" => "101202303404",
    "duration" => "303404505",
    "name" => "dd_name",
    "resource" => "dd_resource",
    "service" => "dd_service",
    "type" => "cli",
    "meta" => array(
        "error.type" => "dd_error_type",
        "error.message" => "dd_error_msg",
        "error.stack" => "dd_error_stack",
        "component" => "dd_component",
        "random" => "dd_random",
        "_dd." => "dd_internal"
    ),
    "metric" => array(
        "metricone" => 1,
        "metrictwo" => 2
    )
)));

?>
--EXPECTF--
array(1) {
  [0]=>
  array(9) {
    ["traceId"]=>
    string(32) "000000000000000001b6995ab4687dea"
    ["id"]=>
    string(16) "0021987762bd2873"
    ["parentId"]=>
    string(16) "000000000001b80e"
    ["timestamp"]=>
    int(101202303)
    ["duration"]=>
    int(303404)
    ["name"]=>
    string(7) "dd_name"
    ["tags"]=>
    array(9) {
      ["signalfx.tracing.library"]=>
      string(11) "php-tracing"
      ["signalfx.tracing.version"]=>
      string(%d) "%s"
      ["sfx.error.kind"]=>
      string(13) "dd_error_type"
      ["sfx.error.message"]=>
      string(12) "dd_error_msg"
      ["sfx.error.stack"]=>
      string(14) "dd_error_stack"
      ["component"]=>
      string(12) "dd_component"
      ["random"]=>
      string(9) "dd_random"
      ["error"]=>
      string(4) "true"
      ["resource.name"]=>
      string(11) "dd_resource"
    }
    ["kind"]=>
    string(6) "SERVER"
    ["localEndpoint"]=>
    array(1) {
      ["serviceName"]=>
      string(11) "sfx_service"
    }
  }
}
