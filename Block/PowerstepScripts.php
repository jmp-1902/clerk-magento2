<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class PowerstepScripts extends Template
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
     * Determine if we should show scripts
     *
     * @return bool
     */
    public function shouldShow(): bool
    {
        return $this->config->get(Config::XML_PATH_POWERSTEP_TYPE, $this->ctx) == Config\Source\PowerstepType::TYPE_POPUP;
    }
}
