<?php

namespace Clerk\Clerk\Block\Adminhtml\System\Config\Fieldset;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;

class FacetedSearch extends Fieldset
{
    /**
     * FacetedSearch constructor.
     *
     * @param Context $context
     * @param Session $authSession
     * @param Js $jsHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $authSession,
        Js      $jsHelper,
        array   $data = []
    )
    {
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    /**
     * Render fieldset
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $this->setElement($element);
        $header = $this->_getHeaderHtml($element);

        $elements = $this->_getChildrenElementsHtml($element);

        $footer = $this->_getFooterHtml($element);

        return $header . $elements . $footer;
    }

}
