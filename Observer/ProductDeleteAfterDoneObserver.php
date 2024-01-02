<?php

namespace Clerk\Clerk\Observer;

use Clerk\Clerk\Helper\Context;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductDeleteAfterDoneObserver implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var Api
     */
    protected Api $api;
    /**
     * @var Context
     */
    protected Context $context;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Api $api,
        Context $context
        )
    {
        $this->scopeConfig = $scopeConfig;
        $this->api = $api;
        $this->context = $context;
    }

    /**
     * Remove product from Clerk
     *
     * @param Observer $observer
     * @return void
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        if(!method_exists($event, 'getProduct')){
            return;
        }

        $scopeInfo = $this->context->getScope();

        $product = $event->getProduct();
        if(!$product){
            return;
        }
        if(!$product->getId()){
            return;
        }

        if(0 !== $scopeInfo['scope_id']){
            if ($this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED, $scopeInfo['scope'], $scopeInfo['scope_id'])) {
                $this->api->removeProduct($product->getId(), $scopeInfo['scope_id']);
            }
            return;
        }

        $productStoreIds = $product->getStoreIds();
        foreach ($productStoreIds as $storeId){
            if ($this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED, 'store', $storeId)) {
                $this->api->removeProduct($product->getId(), $storeId);
            }
        }
    }
}
