<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Sales\Model\Order;

class SalesTracking extends Template
{
    /**
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * SalesTracking constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param Grouped $productGrouped
     * @param Settings $settingsHelper
     * @param ContextHelper $contextHelper
     * @param array $data
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context       $context,
        Session       $checkoutSession,
        Grouped       $productGrouped,
        Settings      $settingsHelper,
        ContextHelper $contextHelper,
        array         $data = []
    )
    {
        parent::__construct(
            $context,
            $data
        );
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
        $this->checkoutSession = $checkoutSession;
        $this->productGrouped = $productGrouped;
    }

    /**
     * Get order increment id
     *
     * @return string
     */
    public function getIncrementId(): string
    {
        return $this->getOrder()->getIncrementId();
    }

    /**
     * Get last order from session
     *
     * @return Order
     */
    private function getOrder(): Order
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    /**
     * Get customer email
     *
     * @return string
     */
    public function getCustomerEmail(): string
    {
        $collect_emails = $this->config->get(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_COLLECT_EMAILS, $this->ctx);
        return $collect_emails ? $this->getOrder()->getCustomerEmail() : "";
    }

    /**
     * Get all order products as json string
     *
     * @return string
     */
    public function getProducts(): string
    {
        $order = $this->getOrder();
        $products = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $groupParentId = $this->productGrouped->getParentIdsByChild($item->getProductId());
            $productId = isset($groupParentId[0]) ?? $item->getProductId();
            $product = [
                'id' => $productId,
                'quantity' => (int)$item->getQtyOrdered(),
                'price' => (float)$item->getBasePrice(),
            ];

            $products[] = $product;
        }

        return json_encode($products);
    }
}
