<?php

namespace App\Constants;

final class ErrorCode
{
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';

    public const TOKEN_EXPIRED = 'TOKEN_EXPIRED';

    public const UNAUTHORIZED = 'UNAUTHORIZED';

    public const INVALID_TOKEN = 'INVALID_TOKEN';

    public const FORBIDDEN = 'FORBIDDEN';

    public const NOT_FOUND = 'NOT_FOUND';

    public const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';

    public const HTTP_ERROR = 'HTTP_ERROR';

    public const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';

    public const FILE_UPLOAD_ERROR = 'FILE_UPLOAD_ERROR';

    public const APPLICATION_STATUS_CONFLICT = 'APPLICATION_STATUS_CONFLICT';
}
