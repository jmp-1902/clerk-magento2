<?php

namespace Clerk\Clerk\Helper;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Context
{
    /**
     * @var RequestInterface
     */
    protected RequestInterface $requestInterface;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    public function __construct(
        StoreManagerInterface $storeManager,
        RequestInterface      $requestInterface
    )
    {
        $this->storeManager = $storeManager;
        $this->requestInterface = $requestInterface;
    }

    /**
     * @return array{scope_id: int, scope: string}
     */
    public function getScopeFromParams(): array
    {
        $params = $this->requestInterface->getParams();
        $scope_info = [
            'scope_id' => 0,
            'scope' => 'default'
        ];
        if (array_key_exists('website', $params)) {
            $scope_info = [
                'scope_id' => (int)$params['website'],
                'scope' => 'website'
            ];
        }
        if (array_key_exists('store', $params)) {
            $scope_info = [
                'scope_id' => (int)$params['store'],
                'scope' => 'store'
            ];
        }
        return $scope_info;

    }

    /**@return array{scope_id: int, scope: string}
     * @throws NoSuchEntityException
     */
    public function getScopeFromContext(): array
    {
        $scope_info = [
            'scope_id' => 0,
            'scope' => 'default'
        ];
        if (!$this->storeManager->isSingleStoreMode()) {
            $scope_info = [
                'scope_id' => $this->storeManager->getStore()->getId(),
                'scope' => ScopeInterface::SCOPE_STORE
            ];
        }
        return $scope_info;
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreIdFromContext(): int
    {
        $requestParams = $this->requestInterface->getParams();
        if (array_key_exists('scope_id', $requestParams)) {
            return (int)$requestParams['scope_id'];
        }
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStoreNameFromContext(): string
    {
        return $this->getStoreFromContext()->getName();
    }

    /**
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    public function getStoreFromContext(): StoreInterface
    {
        $requestParams = $this->requestInterface->getParams();
        if (array_key_exists('scope_id', $requestParams)) {
            return $this->storeManager->getStore($requestParams['scope_id']);
        }
        return $this->storeManager->getStore();
    }


    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getShopBaseDomainUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }
}
