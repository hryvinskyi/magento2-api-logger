<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Block\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Log entry detail view block
 */
class View extends Template
{
    private ?LogEntryInterface $logEntry = null;

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param SerializerInterface $serializer
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get log entry
     *
     * @return LogEntryInterface|null
     */
    public function getLogEntry(): ?LogEntryInterface
    {
        if ($this->logEntry === null) {
            $id = (int)$this->getRequest()->getParam('id');

            if ($id) {
                try {
                    $this->logEntry = $this->logEntryRepository->getById($id);
                } catch (NoSuchEntityException $e) {
                    return null;
                }
            }
        }

        return $this->logEntry;
    }

    /**
     * Format JSON for display
     *
     * @param string|null $json
     * @return string
     */
    public function formatJson(?string $json): string
    {
        if (empty($json)) {
            return '';
        }

        try {
            $data = $this->serializer->unserialize($json);
            return $this->serializer->serialize($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            return $json;
        }
    }

    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    /**
     * Get delete URL
     *
     * @return string
     */
    public function getDeleteUrl(): string
    {
        $id = (int)$this->getRequest()->getParam('id');
        return $this->getUrl('*/*/delete', ['id' => $id]);
    }

    /**
     * Get status badge class
     *
     * @param int|null $responseCode
     * @return string
     */
    public function getStatusBadgeClass(?int $responseCode): string
    {
        if ($responseCode === null) {
            return 'warning';
        }

        if ($responseCode >= 200 && $responseCode < 300) {
            return 'success';
        }

        if ($responseCode >= 300 && $responseCode < 400) {
            return 'info';
        }

        if ($responseCode >= 400 && $responseCode < 500) {
            return 'warning';
        }

        return 'error';
    }
}