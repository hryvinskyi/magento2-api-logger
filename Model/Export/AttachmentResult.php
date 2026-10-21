<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Export;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

/**
 * A file sent to the browser to be saved, straight from memory
 *
 * Logged calls hold secrets even after masking (bodies, ids, addresses), so the file is never written to disk and
 * neither the browser nor a proxy may keep a copy.
 */
class AttachmentResult
{
    private const NO_CACHE = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
        'X-Content-Type-Options' => 'nosniff',
    ];

    /**
     * @param RawFactory $rawFactory
     */
    public function __construct(
        private readonly RawFactory $rawFactory
    ) {
    }

    /**
     * The file as a result to answer with
     *
     * @param string $filename A name of letters, digits, dots and dashes; anything else is replaced by a dash
     * @param string $contentType
     * @param string $content
     * @return Raw
     */
    public function create(string $filename, string $contentType, string $content): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', $contentType, true);
        $result->setHeader(
            'Content-Disposition',
            sprintf('attachment; filename="%s"', preg_replace('/[^A-Za-z0-9._-]/', '-', $filename)),
            true
        );
        $result->setHeader('Content-Length', (string) strlen($content), true);
        foreach (self::NO_CACHE as $name => $value) {
            $result->setHeader($name, $value, true);
        }

        return $result->setContents($content);
    }
}
