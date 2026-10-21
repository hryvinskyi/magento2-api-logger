<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Export;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;

/**
 * Turns a logged call into a file another tool reads; registered in the export pool under its code
 */
interface ExporterInterface
{
    /**
     * Name shown in the export menu: "HAR (.har)"
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The call as a file, named after the log entry id (a call logged before it was journaled has no activity)
     *
     * @param LogEntryInterface $entry
     * @return array{filename: string, contentType: string, content: string}
     */
    public function export(LogEntryInterface $entry): array;
}
