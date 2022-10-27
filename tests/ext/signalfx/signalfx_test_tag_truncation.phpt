--TEST--
Test DD span to SignalFX conversion
--ENV--
SIGNALFX_MODE=1
SIGNALFX_SERVICE=sfx_service
SIGNALFX_RECORDED_VALUE_MAX_LENGTH=20
SIGNALFX_ERROR_STACK_MAX_LENGTH=25
--FILE--
<?php

use function DDTrace\Testing\sfxtrace_ddspan_to_sfx_array;

$tags = sfxtrace_ddspan_to_sfx_array(array(
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
        "random" => "this_is_longer_than_allowed",
        "error.stack" => "stack_trace_can_be_a_bit_longer",
        "_dd." => "dd_internal"
    ),
    "metric" => array(
        "metricone" => 1,
        "metrictwo" => 2
    )
))[0]["tags"];

echo $tags["random"] . "\n" . $tags["sfx.error.stack"];

?>
--EXPECTF--
this_is_longer_than_
stack_trace_can_be_a_bit_
