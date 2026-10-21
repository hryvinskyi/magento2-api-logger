<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Setup\Patch\Data;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * 2.0: removes the export files 1.x wrote into var/ and never deleted
 *
 * 1.x saved every exported call as var/api_log_<id>.har or var/api_log_<id>.txt and left it there, so stored
 * requests, responses and headers stayed readable on disk. Only files with exactly those names, directly in var/,
 * are removed; nothing else in the directory is touched.
 */
class RemoveLegacyExportFiles implements DataPatchInterface
{
    private const LEGACY_EXPORT_GLOB = 'api_log_*';
    private const LEGACY_EXPORT_FILE = '/^api_log_\d+\.(?:har|txt)$/';

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Delete the files
     *
     * @return $this
     */
    public function apply(): self
    {
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        if (!$var->isDirectory()) {
            return $this;
        }

        // Only the candidates are listed: reading the whole directory checks every entry, and a denied name such as
        // ".htaccess" would stop the patch.
        foreach ($var->search(self::LEGACY_EXPORT_GLOB) as $path) {
            if (is_string($path) && preg_match(self::LEGACY_EXPORT_FILE, $path) === 1 && $var->isFile($path)) {
                $var->delete($path);
            }
        }

        return $this;
    }

    /**
     * Runs after the 1.x calls are journaled
     *
     * @return list<class-string<DataPatchInterface>>
     */
    public static function getDependencies(): array
    {
        return [JournalExistingEntries::class];
    }

    /**
     * No other names
     *
     * @return list<string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
