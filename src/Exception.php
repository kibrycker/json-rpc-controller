<?php

namespace JsonRpc;

/**
 * Исключения для JSON RPC
 */
class Exception extends \Exception
{
    /** @var int Invalid JSON was received by the server. An error occurred on the server while parsing the JSON text */
    const PARSE_ERROR = -32700;

    /** @var int The JSON sent is not a valid Request object */
    const INVALID_REQUEST = -32600;

    /** @var  int The method does not exist / is not available */
    const METHOD_NOT_FOUND = -32601;

    /** @var int Invalid method parameter(s) */
    const INVALID_PARAMS = -32602;

    /** @var int Internal JSON-RPC error. */
    const INTERNAL_ERROR = -32603;

    /** @var int INTEGRITY error */
    const INTEGRITY_ERROR = -32020;
}