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
 * Stores the chosen endpoints as a JSON list; the settings page posts and shows them as "METHOD|/route" values
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
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly SerializerInterface $serializer,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Turn the posted values into the stored JSON list
     *
     * @return $this
     */
    public function beforeSave(): self
    {
        $value = $this->getData('value');
        $endpoints = [];
        foreach (is_string($value) ? explode(',', $value) : [] as $endpoint) {
            if (trim($endpoint) !== '') {
                $endpoints[] = trim($endpoint);
            }
        }

        $this->setData('value', (string) $this->serializer->serialize($endpoints));

        return parent::beforeSave();
    }

    /**
     * Turn the stored JSON list back into the values the settings page shows
     *
     * @return $this
     */
    protected function _afterLoad(): self
    {
        $value = $this->getData('value');
        if (!is_string($value) || $value === '') {
            $this->setData('value', []);

            return $this;
        }

        try {
            $endpoints = $this->serializer->unserialize($value);
        } catch (\InvalidArgumentException $exception) {
            $this->_logger->warning(
                sprintf('The setting %s is not a JSON list.', $this->getPath()),
                ['exception' => $exception]
            );
            $endpoints = [];
        }

        $this->setData('value', is_array($endpoints) ? array_values($endpoints) : []);

        return $this;
    }
}
