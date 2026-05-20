<?php

namespace App\Support;

final class ApiRequestAttributes
{
    public const REQUEST_ID = '_app_api_request_id';
    public const TOKEN_HASH = '_app_api_token_hash';
    public const ASSINANTE = '_app_api_assinante';
    public const DESIRED_STATUS_CODE = '_app_api_desired_status_code';
    public const ASYNC_ACCEPTED_PAYLOAD = '_app_api_async_accepted_payload';
    public const INTERNAL_WORKER = '_app_api_internal_worker';
    public const BYPASS_QUEUE = '_app_api_bypass_queue';
    public const OPERATION_NAME = '_app_api_operation_name';

    private function __construct()
    {
    }
}
