<?php

namespace Clerk\Clerk\Helper;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;


class Context
{
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @param StoreManagerInterface $storeManager
     */
    protected StoreManagerInterface $storeManager;

    public function __construct(
        RequestInterface      $request,
        StoreManagerInterface $storeManager
    )
    {
        $this->request = $request;
        $this->storeManager = $storeManager;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStore(): StoreInterface
    {
        $requestParams = $this->request->getParams();
        if (!array_key_exists('scope_id', $requestParams)) {
            return $this->storeManager->getStore();
        }
        return $this->storeManager->getStore($requestParams['scope_id']);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStoreId(): int
    {
        $requestParams = $this->request->getParams();
        if (!array_key_exists('scope_id', $requestParams)) {
            return $this->storeManager->getStore()->getId();
        }
        return (int)$requestParams['scope_id'];
    }

    /**
     * @return array
     */
    public function getScope(): array
    {
        $requestParams = $this->request->getParams();
        $scopeInfo = [
            'scope' => 'default',
            'scope_id' => 0
        ];
        if(array_key_exists('store', $requestParams)){
            $scopeInfo = [
                'scope' => 'store',
                'scope_id' => $requestParams['store']
            ];
        }
        return $scopeInfo;
    }
}