<?php

namespace Clerk\Clerk\Block\Widget;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

class Content extends Template implements BlockInterface
{
    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @var Cart
     */
    protected Cart $cart;

    /**
     * Content constructor.
     * @param Context $context
     * @param Registry $registry
     * @param Cart $cart
     * @param Settings $settingsHelper
     * @param ContextHelper $contextHelper
     * @param array $data
     * @throws NoSuchEntityException
     */
    public function __construct(
        Template\Context $context,
        Registry         $registry,
        Cart             $cart,
        Settings         $settingsHelper,
        ContextHelper    $contextHelper,
        array            $data = []
    )
    {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->cart = $cart;
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
        $this->setTemplate('Clerk_Clerk::widget.phtml');
    }

    public function getEmbeds(): string
    {
        $contents = $this->getContent();

        if ($this->getType() === 'cart') {
            $contents = $this->getCartContents();
        }

        if ($this->getType() === 'category') {
            $contents = $this->getCategoryContents();
        }

        if ($this->getType() === 'product') {
            $contents = $this->getProductContents();
        }

        $output = '';

        $contents_array = $contents ? explode(',', $contents) : [0 => ''];
        foreach ($contents_array as $content) {
            $output .= $this->getHtmlForContent(str_replace(' ', '', $content));
        }

        return $output;
    }

    /**
     * Get product ids from cart
     *
     * @return mixed
     */
    protected function getCartContents(): mixed
    {
        return $this->config->get(Config::XML_PATH_CART_CONTENT, $this->ctx);
    }

    /**
     * Get content for category page slider
     *
     * @return mixed
     */
    protected function getCategoryContents(): mixed
    {
        return $this->config->get(Config::XML_PATH_CATEGORY_CONTENT, $this->ctx);
    }

    /**
     * Get content for product page slider
     *
     * @return mixed
     */
    protected function getProductContents(): mixed
    {
        return $this->config->get(Config::XML_PATH_PRODUCT_CONTENT, $this->ctx);
    }

    private function getHtmlForContent($content): string
    {
        static $content_count = 0;

        $output = '<span ';
        $spanAttributes = [
            'class' => 'clerk',
            'data-template' => '@' . $content,
        ];

        $filter = false;

        if ($this->getProductId()) {
            $filter = $this->config->get(Config::XML_PATH_PRODUCT_FILTER_DUPLICATES, $this->ctx);
            $value = explode('/', $this->getProductId());
            if (isset($value[0], $value[1]) && $value[0] == 'product') {
                $productId = $value[1];
                $spanAttributes['data-products'] = json_encode([$productId]);
                $spanAttributes['data-product'] = $productId;
            }
        } elseif ($this->getCategoryId()) {
            $filter = $this->config->get(Config::XML_PATH_CATEGORY_FILTER_DUPLICATES, $this->ctx);
            $value = explode('/', $this->getCategoryId());
            if (isset($value[0]) && isset($value[1]) && $value[0] == 'category') {
                $categoryId = $value[1];
                $spanAttributes['data-category'] = $categoryId;
            }
        } elseif ($this->getType() === 'cart') {
            $filter = $this->config->get(Config::XML_PATH_CART_FILTER_DUPLICATES, $this->ctx);
            $spanAttributes['data-products'] = json_encode($this->getCartProducts());
        } elseif ($this->getType() === 'category') {
            $filter = $this->config->get(Config::XML_PATH_CATEGORY_FILTER_DUPLICATES, $this->ctx);
            $spanAttributes['data-category'] = $this->getCurrentCategory();
        } elseif ($this->getType() === 'product') {
            $filter = $this->config->get(Config::XML_PATH_PRODUCT_FILTER_DUPLICATES, $this->ctx);
            $spanAttributes['data-products'] = json_encode([$this->getCurrentProduct()]);
            $spanAttributes['data-product'] = $this->getCurrentProduct();
        }

        if ($filter) {
            $unique_class = "clerk_" . $content_count;
            $spanAttributes['class'] = 'clerk ' . $unique_class;
            if ($content_count > 0) {
                $filter_string = '';
                for ($i = 0; $i < $content_count; $i++) {
                    if ($i > 0) {
                        $filter_string .= ', ';
                    }
                    $filter_string .= '.clerk_' . $i;
                }
                $spanAttributes['data-exclude-from'] = $filter_string;
            }
        }

        foreach ($spanAttributes as $attribute => $value) {
            $output .= ' ' . $attribute . '=\'' . $value . '\'';
        }

        $output .= "></span>\n";

        $content_count++;

        return $output;
    }

    /**
     * @return array
     */
    protected function getCartProducts(): array
    {
        return array_values($this->cart->getProductIds());
    }

    /**
     * Get current category id
     *
     * @return int|null
     */
    protected function getCurrentCategory(): mixed
    {
        $category = $this->registry->registry('current_category');

        if ($category) {
            return $category->getId();
        }
        return null;
    }

    /**
     * Get current product id
     *
     * @return int|null
     */
    protected function getCurrentProduct(): ?int
    {
        $product = $this->registry->registry('current_product');

        if ($product) {
            return $product->getId();
        }
        return null;

    }

    /**
     * Determine if we should show any output
     *
     * @throws LocalizedException
     */
    protected function _toHtml(): string
    {
        if ($this->getType() === 'cart') {
            if (!$this->config->bool(Config::XML_PATH_CART_ENABLED, $this->ctx)) {
                return '';
            }
        }

        if ($this->getType() === 'category') {
            if (!$this->config->bool(Config::XML_PATH_CATEGORY_ENABLED, $this->ctx)) {
                return '';
            }
        }

        if ($this->getType() === 'product') {
            if (!$this->config->bool(Config::XML_PATH_PRODUCT_ENABLED, $this->ctx)) {
                return '';
            }
        }

        return parent::_toHtml();
    }
}
