<?php

namespace Clerk\Clerk\Observer;

use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;

class SalesOrderCreditmemoSaveAfterObserver implements ObserverInterface
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
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    public function __construct(ScopeConfigInterface $scopeConfig, Api $api, OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        $this->api = $api;
    }

    /**
     * Return product from Clerk
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $trackReturns = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_ENABLE_ORDER_RETURN_SYNCHRONIZATION, ScopeInterface::SCOPE_STORE);
        if (!$trackReturns) {
            return;
        }
        $event = $observer->getEvent();
        if (!method_exists($event, 'getCreditmemo')) {
            return;
        }
        try {
            $creditmemo = $event->getCreditmemo();
            $orderId = $creditmemo->getOrderId();
            $storeId = $creditmemo->getStoreId();
            $order = $this->orderRepository->get($orderId);
            $orderIncrementId = $order->getIncrementId();
            foreach ($creditmemo->getAllItems() as $item) {
                $productId = $item->getProductId();
                $quantity = $item->getQty();
                if ($productId && $orderIncrementId && $quantity != 0) {
                    $this->api->returnProduct($orderIncrementId, $productId, $quantity, $storeId);
                }
            }
        } catch (Exception $e) {
            return;
        }
    }
}
