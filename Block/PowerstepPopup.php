<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Checkout\Helper\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class PowerstepPopup extends Template
{
    /**
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var Cart
     */
    protected Cart $cartHelper;

    /**
     * @var Image
     */
    protected Image $imageHelper;


    /**
     * PowerstepPopup constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param ProductRepositoryInterface $productRepository
     * @param Cart $cartHelper
     * @param Image $imageHelper
     * @param Settings $settingsHelper
     * @param ContextHelper $contextHelper
     * @param array $data
     * @throws NoSuchEntityException
     */
    public function __construct(
        Template\Context           $context,
        Session                    $checkoutSession,
        ProductRepositoryInterface $productRepository,
        Cart                       $cartHelper,
        Image                      $imageHelper,
        Settings                   $settingsHelper,
        ContextHelper              $contextHelper,
        array                      $data = []
    )
    {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;
        $this->cartHelper = $cartHelper;
        $this->imageHelper = $imageHelper;
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
        $this->setTemplate('powerstep_popup.phtml');
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText(): string
    {
        if ($product = $this->getProduct()) {
            return __(
                'You added %1 to your shopping cart.',
                $product->getName()
            );
        }

        return "failed to load product with id" . $this->checkoutSession->getClerkProductId();
    }

    public function getProduct(): false|ProductInterface
    {
        $productId = $this->checkoutSession->getClerkProductId();

        try {
            return $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Get Cart URL
     *
     * @return string
     */
    public function getCartUrl(): string
    {
        return $this->cartHelper->getCartUrl();
    }

    /**
     * Get image url for product
     *
     * @return string
     */
    public function getImageUrl(): string
    {
        $product = $this->getProduct();
        if (!$product) {
            return '';
        }

        return $this->imageHelper->init(product: $product, imageId: 'product_page_image_small')
            ->setImageFile($product->getImage())
            ->getUrl();
    }

    /**
     * Determine if we should show popup block
     *
     * @return bool
     */
    public function shouldShow(): bool
    {
        $showPowerstep = ($this->getRequest()->getParam('isAjax')) || ($this->checkoutSession->getClerkShowPowerstep(true));

        if ($showPowerstep) {
            $this->checkoutSession->setClerkShowPowerstep(false);
        }

        return $showPowerstep;
    }

    /**
     * Determine if request is ajax
     *
     * @return mixed
     */
    public function isAjax(): mixed
    {
        return $this->getRequest()->getParam('isAjax');
    }

    public function getExcludeState()
    {
        return $this->config->get(Config::XML_PATH_POWERSTEP_FILTER_DUPLICATES, $this->ctx);
    }

    /**
     * Get powerstep templates
     *
     * @return array
     */
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
