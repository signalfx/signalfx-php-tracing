<?php

namespace DDTrace;

class Tag
{
    // Generic
    const ENV = 'env';
    const SPAN_TYPE = 'span.type';
    const SPAN_KIND = 'span.kind';
    const SERVICE_NAME = 'service.name';
    const MANUAL_KEEP = 'manual.keep';
    const MANUAL_DROP = 'manual.drop';
    const PID = 'process_id';
    const RESOURCE_NAME = 'resource.name';
    // SIGNALFX: component tag is for SFX zipkin, cannot reliably deduce in serializer, thus set explicitly
    const COMPONENT = 'component';
    const DB_STATEMENT = 'sql.query';
    const ERROR = 'error';
    const ERROR_MSG = 'error.message'; // string representing the error message
    const ERROR_TYPE = 'error.type'; // string representing the type of the error
    const ERROR_STACK = 'error.stack'; // human readable version of the stack
    const HTTP_METHOD = 'http.method';
    const HTTP_STATUS_CODE = 'http.status_code';
    const HTTP_URL = 'http.url';
    const LOG_EVENT = 'event';
    const LOG_ERROR = 'error';
    const LOG_ERROR_OBJECT = 'error.object';
    const LOG_MESSAGE = 'message';
    const LOG_STACK = 'stack';
    const TARGET_HOST = 'out.host';
    const TARGET_PORT = 'out.port';
    const BYTES_OUT = 'net.out.bytes';
    const ANALYTICS_KEY = '_dd1.sr.eausr';
    const HOSTNAME = '_dd.hostname';
    const ORIGIN = '_dd.origin';
    const VERSION = 'version';
    const SERVICE_VERSION = 'service.version'; // OpenTelemetry compatible tag

    // Elasticsearch
    const ELASTICSEARCH_BODY = 'elasticsearch.body';
    const ELASTICSEARCH_METHOD = 'elasticsearch.method';
    const ELASTICSEARCH_PARAMS = 'elasticsearch.params';
    const ELASTICSEARCH_URL = 'elasticsearch.url';

    // MongoDB
    const MONGODB_BSON_ID = 'mongodb.bson.id';
    const MONGODB_COLLECTION = 'mongodb.collection';
    const MONGODB_DATABASE = 'mongodb.db';
    const MONGODB_PROFILING_LEVEL = 'mongodb.profiling_level';
    const MONGODB_READ_PREFERENCE = 'mongodb.read_preference';
    const MONGODB_SERVER = 'mongodb.server';
    const MONGODB_TIMEOUT = 'mongodb.timeout';
    const MONGODB_QUERY = 'mongodb.query';

    // REDIS
    const REDIS_RAW_COMMAND = 'redis.raw_command';
}
