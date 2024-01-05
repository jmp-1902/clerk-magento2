<?php

namespace Clerk\Clerk\Helper;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Model\Config;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;

class Image
{
    /**
     * @var ImageFactory
     */
    protected ImageFactory $helperFactory;

    protected $helperInstance;
    /**
     * @var StoreInterface
     */
    protected StoreInterface $store;
    /**
     * @var Settings
     */
    protected Settings $config;
    /**
     * @var mixed
     */
    protected mixed $imageSize;
    /**
     * @var ContextHelper
     */
    private ContextHelper $contextHelper;

    /**
     * @param ImageFactory $helperFactory
     * @throws NoSuchEntityException
     */
    public function __construct(
        ImageFactory  $helperFactory,
        ContextHelper $contextHelper,
        Settings      $settingsHelper,
    )
    {
        $this->config = $settingsHelper;
        $this->helperFactory = $helperFactory;
        $this->contextHelper = $contextHelper;
        $this->helperInstance = $this->helperFactory->create();
        $this->store = $this->contextHelper->getStoreFromContext();
        $this->imageSize = $this->config->get(
            Config::XML_PATH_PRODUCT_SYNCHRONIZATION_IMAGE_TYPE,
            ['scope_id' => $this->store->getId(), 'scope' => 'store']
        );
    }

    /**
     * Builds product image URL
     *
     * @param Product $item
     * @return string|null
     */
    public function getUrl(Product $item): ?string
    {
        $imageUrl = null;
        $imagePath = null;
        $helper = $this->helperInstance->init($item, $this->imageSize);

        if ($this->imageSize) {
            $imageUrl = $helper->getUrl();
            if ($imageUrl == $helper->getDefaultPlaceholderUrl()) {
                // Try other image types if image placeholder.
                $imageUrl = null;
            }
        }

        if (!$imageUrl) {
            $imagePath = $item->getImage() ?? $item->getSmallImage() ?? $item->getThumbnail();
        }
        if ($imagePath === 'no_selection') {
            $imageUrl = $helper->getDefaultPlaceholderUrl('small_image');
        } elseif (!$imagePath) {
            $imageUrl = $helper->getDefaultPlaceholderUrl('small_image');
        } else {
            $imageUrl = $this->store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $imagePath;
        }

        if (strpos($imageUrl, 'catalog/product/') === false) {
            $imageUrl = str_replace('catalog/product', 'catalog/product/', $imageUrl);
        }
        return $imageUrl;
    }

}
