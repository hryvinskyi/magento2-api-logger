<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Block\Adminhtml\System\Config;

use Hryvinskyi\ApiLogger\Model\Config\Source\Endpoints;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Advanced Endpoint Selector Frontend Model
 *
 * Provides a sophisticated UI for selecting API endpoints to log
 * Features: grouped checkboxes, search, select all/none, expandable groups
 */
class EndpointSelector extends Field
{
    protected $_template = 'Hryvinskyi_ApiLogger::system/config/endpoint_selector.phtml';

    /**
     * @param Context $context
     * @param Endpoints $endpointsSource
     * @param SerializerInterface $serializer
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Endpoints $endpointsSource,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render element HTML
     *
     * Override parent to prevent automatic disabling when inherit checkbox is checked
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $this->setElement($element);

        // Do NOT call parent::render() because it sets $element->setDisabled(true)
        // when inherit checkbox is checked. We want our custom UI to remain interactive.

        $isCheckboxRequired = $this->_isInheritCheckboxRequired($element);

        // Build the table row HTML structure manually
        $html = '<td class="label"><label for="' .
            $element->getHtmlId() . '"><span' .
            $this->_renderScopeLabel($element) . '>' .
            $element->getLabel() .
            '</span></label></td>';

        // Render our custom template
        $html .= '<td class="value">' . $this->_toHtml() . '</td>';

        // Add inherit checkbox if required
        if ($isCheckboxRequired) {
            $html .= $this->_renderInheritCheckbox($element);
        }

        // Add hint
        $html .= $this->_renderHint($element);

        return $this->_decorateRowHtml($element, $html);
    }

    /**
     * Get element HTML ID
     *
     * @return string
     */
    public function getElementId(): string
    {
        return $this->getElement()->getHtmlId();
    }

    /**
     * Get element name
     *
     * @return string
     */
    public function getElementName(): string
    {
        return $this->getElement()->getName();
    }

    /**
     * Get selected endpoints
     *
     * @return array<string>
     */
    public function getSelectedEndpoints(): array
    {
        $value = $this->getElement()->getValue();

        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Get grouped endpoints
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    public function getGroupedEndpoints(): array
    {
        return $this->endpointsSource->getGroupedOptions();
    }

    /**
     * Get grouped endpoints as JSON
     *
     * @return string
     */
    public function getGroupedEndpointsJson(): string
    {
        return $this->serializer->serialize($this->getGroupedEndpoints());
    }

    /**
     * Get selected endpoints as JSON
     *
     * @return string
     */
    public function getSelectedEndpointsJson(): string
    {
        return $this->serializer->serialize($this->getSelectedEndpoints());
    }

    /**
     * Check if endpoint is selected
     *
     * @param string $endpoint
     * @return bool
     */
    public function isEndpointSelected(string $endpoint): bool
    {
        return in_array($endpoint, $this->getSelectedEndpoints(), true);
    }

    /**
     * Get total count of endpoints
     *
     * @return int
     */
    public function getTotalEndpointsCount(): int
    {
        $count = 0;
        foreach ($this->getGroupedEndpoints() as $group => $endpoints) {
            $count += count($endpoints);
        }
        return $count;
    }

    /**
     * Get count of selected endpoints
     *
     * @return int
     */
    public function getSelectedEndpointsCount(): int
    {
        return count($this->getSelectedEndpoints());
    }
}