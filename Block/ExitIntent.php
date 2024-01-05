<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class ExitIntent extends Template
{
    /**
     * @throws NoSuchEntityException
     */
    public function __construct(
        Template\Context $context,
        Settings         $settingsHelper,
        ContextHelper    $contextHelper,
        array            $data = []
    )
    {
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
        parent::__construct($context, $data);
    }

    /**
     * Get exit intent template
     *
     * @return array
     */
    public function getExitIntentTemplate(): array
    {
        $template_contents = $this->config->get(Config::XML_PATH_EXIT_INTENT_TEMPLATE, $this->ctx);
        return $template_contents ? explode(',', $template_contents) : [0 => ''];
    }
}
