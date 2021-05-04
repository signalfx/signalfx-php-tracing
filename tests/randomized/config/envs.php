<?php

// Values in this array will always be set. Although, their values might be overwritten by corresponding values in
// the ENVS array.
const DEFAULT_ENVS = [
    'SIGNALFX_AGENT_HOST' => 'agent',
];

// Values from this array might be selected and set. When an environment variable from this list is selected,
// then there is an equal probability that any of the assigned values from this array can be set.
const ENVS = [
    'SIGNALFX_ENV' => ['some_env'],
    'SIGNALFX_SERVICE' => ['my_custom_service'],
    'SIGNALFX_TRACE_ENABLED' => ['false'],
    'SIGNALFX_TRACE_DEBUG' => ['true'],
    'SIGNALFX_AGENT_HOST' => [null, 'wrong_host'],
    'SIGNALFX_TRACE_AGENT_PORT' => ['9999'],
    'SIGNALFX_DISTRIBUTED_TRACING' => ['false'],
    'SIGNALFX_AUTOFINISH_SPANS' => ['true'],
    'SIGNALFX_PRIORITY_SAMPLING' => ['false'],
    'SIGNALFX_SERVICE_MAPPING' => ['pdo:pdo-changed,curl:curl-changed'],
    'SIGNALFX_TRACE_AGENT_CONNECT_TIMEOUT' => ['1'],
    'SIGNALFX_TRACE_AGENT_TIMEOUT' => ['1'],
    'SIGNALFX_TRACE_AUTO_FLUSH_ENABLED' => ['true'],
    'SIGNALFX_TAGS' => ['tag_1:hi,tag_2:hello'],
    'SIGNALFX_TRACE_HTTP_CLIENT_SPLIT_BY_DOMAIN' => ['true'],
    'SIGNALFX_TRACE_REDIS_CLIENT_SPLIT_BY_HOST' => ['true'],
    'SIGNALFX_TRACE_MEASURE_COMPILE_TIME' => ['false'],
    'SIGNALFX_TRACE_NO_AUTOLOADER' => ['true'],
    'SIGNALFX_TRACE_RESOURCE_URI_FRAGMENT_REGEX' => ['^aaabbbccc$'],
    'SIGNALFX_TRACE_RESOURCE_URI_MAPPING_INCOMING' => ['cities/*'],
    'SIGNALFX_TRACE_RESOURCE_URI_MAPPING_OUTGOING' => ['cities/*'],
    'SIGNALFX_TRACE_SAMPLE_RATE' => ['0.5', '0.0'],
    'SIGNALFX_TRACE_URL_AS_RESOURCE_NAMES_ENABLED' => ['false'],
    'SIGNALFX_VERSION' => ['1.2.3'],
    // Analytics
    'SIGNALFX_TRACE_SAMPLE_RATE' => ['0.3'],
    // Integrations
    'SIGNALFX_TRACE_CAKEPHP_ENABLED' => ['false'],
    'SIGNALFX_TRACE_CODEIGNITER_ENABLED' => ['false'],
    'SIGNALFX_TRACE_CURL_ENABLED' => ['false'],
    'SIGNALFX_TRACE_ELASTICSEARCH_ENABLED' => ['false'],
    'SIGNALFX_TRACE_ELOQUENT_ENABLED' => ['false'],
    'SIGNALFX_TRACE_GUZZLE_ENABLED' => ['false'],
    'SIGNALFX_TRACE_LARAVEL_ENABLED' => ['false'],
    'SIGNALFX_TRACE_LUMEN_ENABLED' => ['false'],
    'SIGNALFX_TRACE_MEMCACHED_ENABLED' => ['false'],
    'SIGNALFX_TRACE_MONGO_ENABLED' => ['false'],
    'SIGNALFX_TRACE_MYSQLI_ENABLED' => ['false'],
    'SIGNALFX_TRACE_PDO_ENABLED' => ['false'],
    'SIGNALFX_TRACE_PHPREDIS_ENABLED' => ['false'],
    'SIGNALFX_TRACE_PREDIS_ENABLED' => ['false'],
    'SIGNALFX_TRACE_SLIM_ENABLED' => ['false'],
    'SIGNALFX_TRACE_SYMFONY_ENABLED' => ['false'],
    'SIGNALFX_TRACE_WEB_ENABLED' => ['false'],
    'SIGNALFX_TRACE_WORDPRESS_ENABLED' => ['false'],
    'SIGNALFX_TRACE_YII_ENABLED' => ['false'],
    'SIGNALFX_TRACE_ZENDFRAMEWORK_ENABLED' => ['false'],
];
