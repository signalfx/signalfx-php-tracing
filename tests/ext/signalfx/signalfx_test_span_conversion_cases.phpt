--TEST--
Test DD span to SignalFX conversion
--ENV--
SIGNALFX_MODE=1
SIGNALFX_SERVICE=sfx_service
--FILE--
<?php

use function DDTrace\Testing\sfxtrace_ddspan_to_sfx_array;

$base = array(
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
        "error.msg" => "dd_error_msg",
        "error.stack" => "dd_error_stack",
        "component" => "dd_component",
        "random" => "dd_random",
        "_dd." => "dd_internal"
    ),
    "metric" => array(
        "metricone" => 1,
        "metrictwo" => 2
    )
);

echo "PARENT_ID\n";
$span = $base;

$span["parent_id"] = "0";
echo (array_key_exists("parentId", sfxtrace_ddspan_to_sfx_array($span)[0]) ? "present" : "missing") . "\n";

$span["parent_id"] = "1";
echo (array_key_exists("parentId", sfxtrace_ddspan_to_sfx_array($base)[0]) ? "present" : "missing") . "\n";

echo "\nERROR TAG\n";

$span = $base;
echo sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]["error"] . "\n";

unset($span["meta"]["error.type"]);
unset($span["meta"]["error.msg"]);
unset($span["meta"]["error.stack"]);
echo (array_key_exists("error", sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]) ? "present" : "missing") . "\n";

echo "\nCOMPONENT TAG\n";

$span = $base;

echo sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]["component"] . "\n";

unset($span["meta"]["component"]);
echo sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]["component"] . "\n";

unset($span["service"]);
echo (array_key_exists("component", sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]) ? "present" : "missing") . "\n";

echo "\nRESOURCE.NAME TAG\n";
$span = $base;

$span["meta"]["resource.name"] = "explicit";
echo sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]["resource.name"] . "\n";

unset($span["meta"]["resource.name"]);
echo sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]["resource.name"] . "\n";

unset($span["resource"]);
echo (array_key_exists("resource.name", sfxtrace_ddspan_to_sfx_array($span)[0]["tags"]) ? "present" : "missing") . "\n";

?>
--EXPECTF--
PARENT_ID
missing
present

ERROR TAG
1
missing

COMPONENT TAG
dd_component
dd_service
missing

RESOURCE.NAME TAG
explicit
dd_resource
missing