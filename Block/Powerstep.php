<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Catalog\{Block\Product\AbstractProduct, Block\Product\Context, Model\Product};
use Magento\Framework\Exception\NoSuchEntityException;

class Powerstep extends AbstractProduct
{
    /**
     * @throws NoSuchEntityException
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
        $this->ctx = $this->contextHelper->getScopeFromContext();
        parent::__construct($context, $data);
    }

    /**
     * Get Cart URL
     *
     * @return string
     */
    public function getCartUrl(): string
    {
        return $this->_cartHelper->getCartUrl();
    }

    /**
     * Get Checkout URL
     *
     * @return string
     */
    public function getCheckoutUrl(): string
    {
        return $this->getUrl('checkout', ['_secure' => true]);
    }

    /**
     * Get image url for product
     *
     * @return string
     */
    public function getImageUrl(): string
    {
        $product = $this->getProduct();
        return $this->_imageHelper->init($product, 'product_page_image_small')
            ->setImageFile($product->getImage())
            ->getUrl();
    }

    /**
     * Get product added
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        if (!$this->hasData('current_product')) {
            $this->setData('current_product', $this->_coreRegistry->registry('current_product'));
        }

        return $this->getData('current_product');
    }

    public function getExcludeState()
    {
        return $this->config->get(Config::XML_PATH_POWERSTEP_FILTER_DUPLICATES, $this->ctx);
    }

    public function getTemplates(): array
    {
        $templates = array();

        $template_contents = $this->config->get(Config::XML_PATH_POWERSTEP_TEMPLATES, $this->ctx);
        $template_contents = $template_contents ? explode(',', $template_contents) : [0 => ''];

        foreach ($template_contents as $key => $template) {
            $templates[$key] = str_replace(' ', '', $template);
        }

        return $templates;
    }

}
