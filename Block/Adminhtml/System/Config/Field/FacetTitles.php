<?php

namespace Clerk\Clerk\Block\Adminhtml\System\Config\Field;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class FacetTitles extends Field
{
    /**
     * FacetTitles constructor.
     * @param Context $context
     * @param Settings $settingsHelper
     * @param ContextHelper $contextHelper
     * @param array $data
     */
    public function __construct(
        Context       $context,
        Settings      $settingsHelper,
        ContextHelper $contextHelper,
        array         $data = []
    )
    {
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromParams();
        $this->setTemplate('Clerk_Clerk::facettitles.phtml');
        parent::__construct($context, $data);
    }

    /**
     * Get configured facet attributes
     * @return array
     */
    public function getConfiguredAttributes(): array
    {
        $attributes = $this->config->get(Config::XML_PATH_FACETED_SEARCH_ATTRIBUTES, $this->ctx);
        return is_string($attributes) ? explode(',', $attributes) : [];
    }

    /**
     * Get label for current scope
     *
     * @return string
     */
    public function getScopeLabel(): string
    {
        try {
            return $this->contextHelper->getStoreNameFromContext();
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Get html for element
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->setElement($element);

        return $this->toHtml();
    }
}
