<?php

if (isset($_GET["file_get_contents"])) {
    file_get_contents('/proc/self/exe');
}

if (isset($_GET["json_decode"])) {
    json_decode('{"test":"value"}');
}

if (isset($_GET["json_encode"])) {
    json_encode(['test' => 'value']);
}
