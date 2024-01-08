<?php

namespace Clerk\Clerk\Block\Adminhtml\System\Config\Field;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class MultiselectFacetAttributes extends Field
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
        parent::__construct($context, $data);
    }

    /**
     * Get element html if facet attributes are configured
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        if ($this->config->get(Config::XML_PATH_FACETED_SEARCH_ATTRIBUTES, $this->ctx)) {
            return parent::render($element);
        }

        return '';
    }
}
