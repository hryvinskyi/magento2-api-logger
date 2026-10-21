<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Block\Adminhtml\System\Config;

use Hryvinskyi\ActivityLog\Api\IconsInterface;
use Hryvinskyi\ApiLogger\Model\Config\Source\Endpoints;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\JsonHexTag;

/**
 * The endpoint picker of the REST logging settings: every declared route grouped by resource, with search
 *
 * The picker stays usable while the value is inherited; the script greys it out with the "Use default" box.
 *
 * @phpstan-import-type Endpoint from Endpoints
 */
class EndpointSelector extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Hryvinskyi_ApiLogger::system/config/endpoint_selector.phtml';

    /**
     * @var AbstractElement|null
     */
    private ?AbstractElement $field = null;

    /**
     * @param Context $context
     * @param Endpoints $endpointsSource
     * @param JsonHexTag $json
     * @param IconsInterface $icons
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Endpoints $endpointsSource,
        private readonly JsonHexTag $json,
        private readonly IconsInterface $icons,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the settings row with the picker in place of an input
     *
     * The parent's render is not used: it disables the field while the value is inherited, and the picker has to
     * stay interactive.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $this->field = $element;

        $html = '<td class="label"><label for="' . $this->escapeHtmlAttr($element->getHtmlId()) . '"><span'
            . $this->_renderScopeLabel($element) . '>' . $this->escapeHtml($this->text($element->getData('label')))
            . '</span></label></td>'
            . '<td class="value">' . $this->_toHtml() . '</td>';

        if ($this->_isInheritCheckboxRequired($element)) {
            $html .= $this->_renderInheritCheckbox($element);
        }

        return $this->_decorateRowHtml($element, $html . $this->_renderHint($element));
    }

    /**
     * Id of the hidden input that carries the value
     *
     * @return string
     */
    public function getElementId(): string
    {
        return $this->field === null ? '' : (string) $this->field->getHtmlId();
    }

    /**
     * Name the value is posted under
     *
     * @return string
     */
    public function getElementName(): string
    {
        return $this->field === null ? '' : $this->text($this->field->getName());
    }

    /**
     * Saved "METHOD|/route" values
     *
     * @return list<string>
     */
    public function getSelectedEndpoints(): array
    {
        $value = $this->field?->getData('value');
        $values = is_string($value) ? explode(',', $value) : (is_array($value) ? $value : []);

        $selected = [];
        foreach ($values as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $selected[] = trim((string) $item);
            }
        }

        return $selected;
    }

    /**
     * Routes grouped by resource
     *
     * @return array<string, list<Endpoint>>
     */
    public function getGroupedEndpoints(): array
    {
        return $this->endpointsSource->getGroupedOptions();
    }

    /**
     * How many routes the picker lists
     *
     * @return int
     */
    public function getTotalEndpointsCount(): int
    {
        return array_sum(array_map('count', $this->getGroupedEndpoints()));
    }

    /**
     * The icon of a group's expand and collapse toggle, as inline SVG
     *
     * @return string
     */
    public function getToggleIcon(): string
    {
        return $this->icons->render('Hryvinskyi_ApiLogger::chevron-down', 'endpoint-group-toggle__icon');
    }

    /**
     * Script config of the picker, for data-mage-init
     *
     * @return string
     */
    public function getScriptConfig(): string
    {
        return (string) $this->json->serialize([
            'Hryvinskyi_ApiLogger/js/view/endpoint-selector' => ['inputId' => $this->getElementId()],
        ]);
    }

    /**
     * Cast a scalar to its string form, anything else to an empty string
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
