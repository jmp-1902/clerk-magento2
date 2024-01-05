<?php

namespace Clerk\Clerk\Observer;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductDeleteAfterDoneObserver implements ObserverInterface
{
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var Api
     */
    protected Api $api;
    protected ContextHelper $contextHelper;
    protected array $ctx;

    /**
     * @throws NoSuchEntityException
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Api                  $api,
        RequestInterface     $request,
        Settings             $settingsHelper,
        ContextHelper        $contextHelper,
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->api = $api;
        $this->request = $request;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
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
        $product = $observer->getEvent()->getProduct();
        if ($product && $product->getId()) {
            if ($this->ctx == 0) {
                $store_ids_prod = $product->getStoreIds();
                foreach ($store_ids_prod as $store_id) {
                    if ($this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED, 'store', $this->ctx['scope_id'])) {
                        $this->api->removeProduct($product->getId(), $this->ctx['scope_id']);
                    }
                }
            } else {
                if ($this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED, $this->ctx['scope'], $this->ctx['scope_id'])) {
                    $this->api->removeProduct($product->getId(), $this->ctx['scope_id']);
                }
            }
        }
    }
}
