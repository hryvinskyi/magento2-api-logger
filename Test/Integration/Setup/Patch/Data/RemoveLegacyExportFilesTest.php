<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Setup\Patch\Data;

use Hryvinskyi\ApiLogger\Setup\Patch\Data\RemoveLegacyExportFiles;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The upgrade removes the export files 1.x left in var/ and nothing else, against the real var directory
 */
class RemoveLegacyExportFilesTest extends TestCase
{
    private const ACCESS_FILE = '.htaccess';

    /**
     * @var WriteInterface
     */
    private WriteInterface $var;

    /**
     * Files this test wrote, removed again whatever the outcome
     *
     * @var list<string>
     */
    private array $written = [];

    /**
     * A directory this test made, removed again whatever the outcome
     *
     * @var string
     */
    private string $subdirectory = '';

    /**
     * Whether this test put a ".htaccess" into var/, as a deployed store has one
     *
     * @var bool
     */
    private bool $wroteAccessFile = false;

    protected function setUp(): void
    {
        $filesystem = Bootstrap::getObjectManager()->get(Filesystem::class);
        self::assertInstanceOf(Filesystem::class, $filesystem);
        $this->var = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if ($this->var->isExist($path)) {
                $this->var->delete($path);
            }
        }
        if ($this->wroteAccessFile) {
            unlink($this->var->getAbsolutePath() . self::ACCESS_FILE);
        }
        if ($this->subdirectory !== '' && $this->var->isExist($this->subdirectory)) {
            $this->var->delete($this->subdirectory);
        }
    }

    public function testTheHarAndTextExportsOf1xAreRemovedAndUnrelatedFilesStay(): void
    {
        $id = random_int(100000000, 999999999);
        $this->subdirectory = 'api-logger-test-' . $id;
        $har = $this->write(sprintf('api_log_%d.har', $id));
        $text = $this->write(sprintf('api_log_%d.txt', $id + 1));
        $kept = [
            $this->write(sprintf('api_log_%d.json', $id)),
            $this->write(sprintf('api_log_%da.har', $id)),
            $this->write(sprintf('my_api_log_%d.txt', $id)),
            $this->write(sprintf('rest-call-%d.har', $id)),
            $this->write(sprintf('%s/api_log_%d.har', $this->subdirectory, $id)),
        ];

        $accessFile = $this->var->getAbsolutePath() . self::ACCESS_FILE;
        if (!file_exists($accessFile)) {
            file_put_contents($accessFile, 'Require all denied');
            $this->wroteAccessFile = true;
        }

        $this->patch()->apply();

        self::assertFalse($this->var->isExist($har), $har . ' was left');
        self::assertFalse($this->var->isExist($text), $text . ' was left');
        foreach ($kept as $path) {
            self::assertTrue($this->var->isExist($path), $path . ' was removed');
            self::assertSame('kept', $this->var->readFile($path));
        }
    }

    /**
     * @return RemoveLegacyExportFiles
     */
    private function patch(): RemoveLegacyExportFiles
    {
        $patch = Bootstrap::getObjectManager()->create(RemoveLegacyExportFiles::class);
        self::assertInstanceOf(RemoveLegacyExportFiles::class, $patch);

        return $patch;
    }

    /**
     * Write a file into var/ and remember it for clean-up
     *
     * @param string $path
     * @return string
     */
    private function write(string $path): string
    {
        $this->var->writeFile($path, 'kept');
        $this->written[] = $path;

        return $path;
    }
}
