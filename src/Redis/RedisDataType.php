<?php

declare(strict_types=1);

namespace App\Redis;

enum RedisDataType: string
{
    case STRING = 'string';
    case JSON = 'json';
}
