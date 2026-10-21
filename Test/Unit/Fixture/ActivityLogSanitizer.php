<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Fixture;

use Hryvinskyi\ActivityLog\Api\ConfigInterface as ActivityLogConfigInterface;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\FormBodyMasker;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Body\JsonBodyMasker;
use Hryvinskyi\ActivityLog\Model\Sanitizer\Sanitizer;

/**
 * The activity log's own sanitizer, with the secret names it always masks and its JSON and form-encoded rules
 */
trait ActivityLogSanitizer
{
    /**
     * A real sanitizer; the configured list adds nothing to the names that are always secret
     *
     * @return SanitizerInterface
     */
    private function activityLogSanitizer(): SanitizerInterface
    {
        $config = $this->createStub(ActivityLogConfigInterface::class);
        $config->method('getSecretFields')->willReturn([]);

        return new Sanitizer(
            $config,
            ['password', 'token', 'secret', 'authorization', 'cookie', 'api_key', 'apikey', 'private_key'],
            ['json' => new JsonBodyMasker(), 'form' => new FormBodyMasker()]
        );
    }
}
