<?php

namespace Clerk\Clerk\Block\Adminhtml;

use Clerk\Clerk\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;

class Dashboard extends Template
{
    /** @var string */
    protected string $type = 'dashboard';

    /**
     * @var StoreRepositoryInterface
     */
    private StoreRepositoryInterface $storeRepository;

    public function __construct(
        Template\Context         $context,
        StoreRepositoryInterface $storeRepository,
        array                    $data = [])
    {
        $this->storeRepository = $storeRepository;
        parent::__construct($context, $data);
    }

    /**
     * Get iframe embed url
     *
     * @return false|string
     * @throws NoSuchEntityException
     */
    public function getEmbedUrl(): false|string
    {
        if (!$this->getStoreId()) {
            return false;
        }

        $publicKey = $this->_scopeConfig->getValue(
            Config::XML_PATH_PUBLIC_KEY,
            ScopeInterface::SCOPE_STORES,
            $this->getStore()->getCode()
        );

        $privateKey = $this->_scopeConfig->getValue(
            Config::XML_PATH_PRIVATE_KEY,
            ScopeInterface::SCOPE_STORES,
            $this->getStore()->getCode()
        );

        if (empty($publicKey) || empty($privateKey)) {
            return false;
        }

        $storePart = $this->getStorePart($publicKey);

        return sprintf('https://my.clerk.io/#/store/%s/analytics/%s?key=%s&private_key=%s&embed=yes', $storePart, $this->type, $publicKey, $privateKey);
    }

    /**
     * Get Store ID
     *
     * @return mixed
     */
    public function getStoreId(): mixed
    {
        return $this->getRequest()->getParam('store');
    }

    /**
     * Get current store
     * @throws NoSuchEntityException
     */
    public function getStore(): StoreInterface
    {
        return $this->storeRepository->getById($this->getStoreId());
    }

    /**
     * Get first 8 characters of public key
     *
     * @param $publicKey
     * @return string
     */
    protected function getStorePart($publicKey): string
    {
        return substr($publicKey, 0, 8);
    }

    /**
     * Get url for clerk system configuration
     *
     * @return string
     */
    public function getConfigureUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/clerk');
    }
}
