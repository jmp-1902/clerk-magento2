<?php

namespace Clerk\Clerk\Observer;

use Clerk\Clerk\Model\Adapter\Product as ProductAdapter;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Clerk\Clerk\Helper\Context;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ProductModelConfigurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as ProductModelGrouped;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ProductSaveAfterObserver implements ObserverInterface
{
    /**
     * @var ProductModel
     */
    protected ProductModel $productModel;

    /**
     * @var ProductModelGrouped
     */
    protected ProductModelGrouped $productModelGrouped;

    /**
     * @var ProductModelConfigurable
     */
    protected ProductModelConfigurable $productModelConfigurable;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $eventManager;

    /**
     * @var Emulation
     */
    protected Emulation $emulation;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var Api
     */
    protected Api $api;

    /**
     * @var ProductAdapter
     */
    protected ProductAdapter $productAdapter;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * ProductSaveAfterObserver constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ManagerInterface $eventManager
     * @param RequestInterface $request
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     * @param Api $api
     * @param ProductAdapter $productAdapter
     * @param ProductModelConfigurable $productModelConfigurable
     * @param ProductModelGrouped $productModelGrouped
     * @param ProductModel $productModel
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        ScopeConfigInterface       $scopeConfig,
        ManagerInterface           $eventManager,
        RequestInterface           $request,
        Emulation                  $emulation,
        StoreManagerInterface      $storeManager,
        Api                        $api,
        ProductAdapter             $productAdapter,
        ProductModelConfigurable   $productModelConfigurable,
        ProductModelGrouped        $productModelGrouped,
        ProductModel               $productModel,
        ProductRepositoryInterface $productRepository,
        LoggerInterface            $logger,
        Context                    $context
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->eventManager = $eventManager;
        $this->request = $request;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->api = $api;
        $this->productAdapter = $productAdapter;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->productModelConfigurable = $productModelConfigurable;
        $this->productModelGrouped = $productModelGrouped;
        $this->productModel = $productModel;
        $this->context = $context;
    }

    /**
     * Add product to Clerk
     *
     * @param Observer $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {

        $event = $observer->getEvent();
        if (!method_exists($event, 'getProduct')) {
            return;
        }

        $product = $event->getProduct();
        if (!$product->getId()) {
            return;
        }

        $scopeInfo = $this->context->getScope();
        if ($scopeInfo['scope_id'] !== 0) {
            $this->updateProduct($product, $scopeInfo['scope_id']);
            return;
        }

        $productStoreIds = $product->getStoreIds();
        $productId = $product->getId();
        foreach ($productStoreIds as $storeId) {
            $productInstance = $this->productRepository->getById($productId, false, $storeId);
            $this->updateProduct($productInstance, $storeId);
        }

    }

    /**
     * @param ProductInterface $product
     * @param int $storeId
     * @return void
     */
    protected function updateProduct(ProductInterface $product, int $storeId): void
    {
        $use_rtu = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
        if (!$use_rtu) {
            return;
        }
        try {
            $this->updateStore($product, $storeId);
        } catch (Exception $e) {
            $this->logger->error('Updating Products Error', ['error' => $e->getMessage()]);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @param ProductInterface $product
     * @param $storeId
     * @throws NoSuchEntityException
     */
    protected function updateStore(ProductInterface $product, $storeId): void
    {
        $this->emulation->startEnvironmentEmulation($storeId);

        if (!$product->getId()) {
            return;
        }

        $visibilitySetting = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_VISIBILITY, ScopeInterface::SCOPE_STORE, $storeId);
        if ('any' !== $visibilitySetting && $visibilitySetting !== $product->getVisibility()) {
            return;
        }

        $statusSetting = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_SALABLE_ONLY, ScopeInterface::SCOPE_STORE, $storeId);
        if ($statusSetting && !$product->isSalable()) {
            return;
        }

        // Call Api for Product.
        $productInfo = $this->productAdapter->getInfoForItem($product, 'store', $storeId);
        $this->api->addProduct($productInfo, $storeId);

        // Call Api for Configurable Parent Product.
        $productParentIds = $this->productModelConfigurable->getParentIdsByChild($product->getId());
        if (empty($productParentIds)) {
            return;
        }
        $parentProduct = $this->productRepository->getById($productParentIds[0]);
        $parentProductInfo = $this->productAdapter->getInfoForItem($parentProduct, 'store', $storeId);
        $this->api->addProduct($parentProductInfo, $storeId);

        // Call Api for Configurable Parent Product.
        $parentGroupProductIds = $this->productModelGrouped->getParentIdsByChild($product->getId());
        if (empty($parentGroupProductIds)) {
            return;
        }
        $parentGroupProduct = $this->productRepository->getById($parentGroupProductIds[0]);
        $parentGroupProductInfo = $this->productAdapter->getInfoForItem($parentGroupProduct, 'store', $storeId);
        $this->api->addProduct($parentGroupProductInfo, $storeId);

    }


}
