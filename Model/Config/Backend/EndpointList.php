<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Backend model for endpoint list configuration
 *
 * Handles conversion between comma-separated string (from frontend)
 * and serialized array (for database storage)
 */
class EndpointList extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param SerializerInterface $serializer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly SerializerInterface $serializer,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Process data before saving
     *
     * Converts comma-separated string from frontend to serialized array for database
     *
     * @return $this
     */
    public function beforeSave(): self
    {
        $value = $this->getValue();

        if (is_string($value) && !empty($value)) {
            // Split comma-separated string into array
            $endpoints = array_filter(array_map('trim', explode(',', $value)));
            // Serialize for database storage
            $this->setValue($this->serializer->serialize($endpoints));
        } elseif (empty($value)) {
            // Empty value should be stored as empty serialized array
            $this->setValue($this->serializer->serialize([]));
        }

        return parent::beforeSave();
    }

    /**
     * Process data after loading
     *
     * Converts serialized array from database to comma-separated string for frontend
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();
        if (!is_array($value)) {
            try {
                $this->setValue(empty($value) ? false : $this->serializer->unserialize($value));
            } catch (\Exception $e) {
                $this->_logger->critical(
                    sprintf(
                        'Failed to unserialize %s config value. The error is: %s',
                        $this->getPath(),
                        $e->getMessage()
                    )
                );
                $this->setValue([]);
            }
        }
    }
}