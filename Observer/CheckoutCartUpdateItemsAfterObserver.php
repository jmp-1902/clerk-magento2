<?php

namespace Clerk\Clerk\Observer;

use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class CheckoutCartUpdateItemsAfterObserver implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;

    /**
     * @var Cart
     */
    protected Cart $cart;
    protected Api $api;

    /**
     * CheckoutCartUpdateItemsAfterObserver constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Session $checkoutSession
     * @param Cart $cart
     * @param CustomerSession $customerSession
     * @param Api $api
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Session              $checkoutSession,
        Cart                 $cart,
        CustomerSession      $customerSession,
        Api                  $api
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->cart = $cart;
        $this->api = $api;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        if ($this->scopeConfig->getValue('clerk/product_synchronization/collect_baskets', ScopeInterface::SCOPE_STORE) == '1') {
            $cart_productIds = [];
            foreach ($this->cart->getQuote()->getAllVisibleItems() as $item) {
                if (!in_array($item->getProductId(), $cart_productIds)) {
                    $cart_productIds[] = $item->getProductId();
                }
            }

            if ($this->customerSession->isLoggedIn()) {
                $data_string = json_encode([
                    'key' => $this->scopeConfig->getValue(Config::XML_PATH_PUBLIC_KEY, ScopeInterface::SCOPE_STORE),
                    'products' => $cart_productIds,
                    'email' => $this->customerSession->getCustomer()->getEmail()
                ]);

                $this->api->post('log/basket/set', $data_string);
            }
        }
    }
}
