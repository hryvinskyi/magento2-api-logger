<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Export;

/**
 * Exporters registered through the "exporters" argument, by format code
 */
class ExporterPool
{
    /**
     * @param array<string,ExporterInterface> $exporters
     */
    public function __construct(
        private readonly array $exporters = []
    ) {
    }

    /**
     * The exporter of a format, or null
     *
     * @param string $format
     * @return ExporterInterface|null
     */
    public function get(string $format): ?ExporterInterface
    {
        return $this->exporters[$format] ?? null;
    }

    /**
     * Every exporter by format code, in their order
     *
     * @return array<string, ExporterInterface>
     */
    public function all(): array
    {
        return $this->exporters;
    }
}
