<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class LiveSearch extends Template
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
     * Get live search template
     *
     * @return mixed
     */
    public function getLiveSearchTemplate(): mixed
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_TEMPLATE, $this->ctx);
    }


    public function getSuggestions()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_SUGGESTIONS, $this->ctx);
    }

    public function getCategories()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_CATEGORIES, $this->ctx);
    }

    public function getPages()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_PAGES, $this->ctx);
    }

    public function getPagesType()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_PAGES_TYPE, $this->ctx);
    }

    public function getDropdownPosition()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_DROPDOWN_POSITION, $this->ctx);
    }

    public function getInputSelector()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_INPUT_SELECTOR, $this->ctx);
    }

    public function getFormSelector()
    {
        return $this->config->get(Config::XML_PATH_LIVESEARCH_FORM_SELECTOR, $this->ctx);
    }
}
