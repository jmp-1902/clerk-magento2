<?php

namespace Clerk\Clerk\Model\Adapter;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Image;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\CatalogInventory\Helper\Stock as StockFilter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\Rate as TaxRate;

class Product extends AbstractAdapter
{

    const PRODUCT_TYPE_SIMPLE = 'simple';
    const PRODUCT_TYPE_CONFIGURABLE = 'configurable';
    const PRODUCT_TYPE_GROUPED = 'grouped';
    const PRODUCT_TYPE_BUNDLE = 'bundle';
    const PRODUCT_TYPES = [
        self::PRODUCT_TYPE_SIMPLE,
        self::PRODUCT_TYPE_CONFIGURABLE,
        self::PRODUCT_TYPE_GROUPED,
        self::PRODUCT_TYPE_BUNDLE
    ];


    /**
     * @var ProductRepositoryInterface;
     */
    protected ProductRepositoryInterface $productRepository;


    /**
     * @var TaxRate;
     */
    protected TaxRate $taxRate;


    /**
     * @var null
     */
    protected ?array $productTaxRates;


    /**
     * @var StockFilter
     */
    protected StockFilter $stockFilter;


    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $requestInterface;
    /**
     * @var CollectionFactory
     */
    protected mixed $collectionFactory;

    /**
     * @var Image
     */
    protected Image $imageHelper;

    /**
     * @var StockStateInterface
     */
    protected StockStateInterface $stockStateInterface;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadataInterface;

    /**
     * @var string
     */
    protected string $eventPrefix = 'product';

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var Data
     */
    protected Data $taxHelper;

    /**
     * @var array
     */
    protected array $fieldMap = [
        'entity_id' => 'id',
    ];
    /**
     * @var ContextHelper
     */
    protected ContextHelper $contextHelper;
    /**
     * @var StoreInterface
     */
    protected StoreInterface $store;
    /**
     * @var Settings
     */
    protected Settings $config;
    /**
     * @var int
     */
    protected int $storeId;
    /**
     * @var ModuleManager
     */
    protected ModuleManager $moduleManager;
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;
    protected bool $msiEnabled;

    /**
     * Summary of __construct
     * @throws NoSuchEntityException
     */
    public function __construct(
        ScopeConfigInterface       $scopeConfig,
        ManagerInterface           $eventManager,
        CollectionFactory          $collectionFactory,
        StoreManagerInterface      $storeManager,
        Image                      $imageHelper,
        ClerkLogger                $clerkLogger,
        StockFilter                $stockFilter,
        Data                       $taxHelper,
        StockStateInterface        $stockStateInterface,
        ProductMetadataInterface   $productMetadataInterface,
        RequestInterface           $requestInterface,
        TaxRate                    $taxRate,
        ProductRepositoryInterface $productRepository,
        Settings                   $settingsHelper,
        ContextHelper              $contextHelper,
        ModuleManager              $moduleManager,
        ObjectManagerInterface     $objectManager
    )
    {
        $this->taxHelper = $taxHelper;
        $this->stockFilter = $stockFilter;
        $this->clerkLogger = $clerkLogger;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->stockStateInterface = $stockStateInterface;
        $this->productMetadataInterface = $productMetadataInterface;
        $this->requestInterface = $requestInterface;
        $this->taxRate = $taxRate;
        $this->productTaxRates = $this->taxRate->getCollection()->getData();
        $this->productRepository = $productRepository;
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->store = $this->contextHelper->getStoreFromContext();
        $this->storeId = $this->store->getId();
        $this->msiEnabled = $this->moduleManager->isEnabled('Magento_Inventory') && $this->moduleManager->isEnabled('Magento_InventoryAdminUi');
        parent::__construct(
            $scopeConfig,
            $eventManager,
            $storeManager,
            $collectionFactory,
            $clerkLogger
        );
    }

    /**
     * Prepare collection
     *
     * @param $page
     * @param $limit
     * @param $orderBy
     * @param $order
     * @param $scope
     * @param $scopeid
     * @return mixed
     * @throws FileSystemException
     */
    protected function prepareCollection($page, $limit, $orderBy, $order, $scope, $scopeid): mixed
    {
        try {

            $collection = $this->collectionFactory->create();

            $collection->addFieldToSelect('*');
            $collection->addStoreFilter($scopeid);
            $productMetadata = $this->productMetadataInterface;
            $version = $productMetadata->getVersion();

            if (!$version >= '2.3.3') {
                if ($this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_SALABLE_ONLY, $scope, $scopeid)) {
                    $this->stockFilter->addInStockFilterToCollection($collection);
                }
            } else {
                if (!$this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_SALABLE_ONLY, $scope, $scopeid)) {
                    $collection->setFlag('has_stock_status_filter', true);
                }
            }

            $visibility = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_VISIBILITY, $scope, $scopeid);

            switch ($visibility) {
                case Visibility::VISIBILITY_IN_CATALOG:
                    $collection->setVisibility([Visibility::VISIBILITY_IN_CATALOG]);
                    break;
                case Visibility::VISIBILITY_IN_SEARCH:
                    $collection->setVisibility([Visibility::VISIBILITY_IN_SEARCH]);
                    break;
                case Visibility::VISIBILITY_BOTH:
                    $collection->setVisibility([Visibility::VISIBILITY_BOTH]);
                    break;
                case 'any':
                    $collection->addAttributeToFilter('visibility', ['in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH]]);
                    break;
            }

            $collection->setPageSize($limit)->setCurPage($page)->addOrder($orderBy, $order);

            $this->eventManager->dispatch('clerk_' . $this->eventPrefix . '_get_collection_after', [
                'adapter' => $this,
                'collection' => $collection
            ]);

            return $collection;

        } catch (Exception $e) {

            $this->clerkLogger->error('Prepare Collection Error', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function buildProductStockPriceImage($item, $relatedItems, $itemType, $emulateEntity, $productData): array
    {
        if ($emulateEntity) {
            $childIds = $item->getTypeInstance()->getChildrenIds($item->getId());
            if (!empty($childIds)) {
                $childIds = isset($childIds[0]) && is_array($childIds[0]) ? $childIds[0] : $childIds;
                $relatedItems = array();
                foreach ($childIds as $id) {
                    $relatedItems[] = $this->productRepository->getById($id);
                }
            }
        }

        $price = 0;
        $price_excl_tax = 0;
        $list_price = 0;
        $list_price_excl_tax = 0;
        $child_prices = array();
        $child_list_prices = array();
        $stock = 0;
        $child_stocks = array();
        $multi_source_stock = 0;
        $child_images = array();

        switch ($itemType) {
            case self::PRODUCT_TYPE_CONFIGURABLE:
                $image = $this->imageHelper->getUrl($item);
                foreach ($relatedItems as $relatedItem) {
                    $multi_source_stock += $this->getSourceStockBySku($item->getSku());

                    $child_price = $this->formatPrice($this->getProductTaxPrice($relatedItem, $relatedItem->getFinalPrice(), true));
                    $child_list_price = $this->formatPrice($this->getProductTaxPrice($relatedItem, $relatedItem->getPrice(), true));
                    if (!empty($child_price)) {
                        $child_prices[] = $this->formatPrice($this->getProductTaxPrice($relatedItem, $relatedItem->getFinalPrice(), true));
                    }
                    if (!empty($child_list_price)) {
                        $child_list_prices[] = $this->formatPrice($this->getProductTaxPrice($relatedItem, $relatedItem->getPrice(), true));
                    }
                    $stock += $this->getProductStockStateQty($relatedItem) ?? $this->getSaleableStockBySku($relatedItem->getSku());
                    $child_stocks[] = $this->getProductStockStateQty($relatedItem);
                    $child_images[] = $this->imageHelper->getUrl($relatedItem);
                }
                $price_source = !empty($child_prices) ? min($child_prices) : $item->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                $list_price_source = !empty($child_list_prices) ? min($child_list_prices) : $item->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
                $price = $this->formatPrice($this->getProductTaxPrice($item, $price_source, true));
                $price_excl_tax = $this->formatPrice($this->getProductTaxPrice($item, $price_source, false));
                $list_price = $this->formatPrice($this->getProductTaxPrice($item, $list_price_source, true));
                $list_price_excl_tax = $this->formatPrice($this->getProductTaxPrice($item, $list_price_source, false));
                $productData['child_prices'] = $child_prices;
                $productData['child_list_prices'] = $child_list_prices;
                $productData['child_images'] = $child_images;
                $productData['child_stocks'] = $child_stocks;
                break;
            case self::PRODUCT_TYPE_GROUPED:
                $image = $this->imageHelper->getUrl($item);
                foreach ($relatedItems as $relatedItem) {
                    $multi_source_stock += $this->getSourceStockBySku($item->getSku());
                    $qty = $relatedItem->getQty();
                    $amt = $relatedItem->getPrice();
                    $famt = $relatedItem->getFinalPrice() ?? $amt;
                    if (is_numeric($qty) && is_numeric($famt)) {
                        $price += $this->formatPrice($this->getProductTaxPrice($relatedItem, $famt, true)) * $qty;
                        $price_excl_tax += $this->formatPrice($this->getProductTaxPrice($relatedItem, $famt, false)) * $qty;
                    }
                    if (is_numeric($qty) && is_numeric($amt)) {
                        $list_price += $this->formatPrice($this->getProductTaxPrice($relatedItem, $amt, true)) * $qty;
                        $list_price_excl_tax += $this->formatPrice($this->getProductTaxPrice($relatedItem, $amt, false)) * $qty;
                    }
                    $child_prices[] = $this->formatPrice($this->getProductTaxPrice($relatedItem, $famt, true));
                    $child_list_prices[] = $this->formatPrice($this->getProductTaxPrice($relatedItem, $amt, true));
                    $stock += $this->getProductStockStateQty($relatedItem) ?? $this->getSaleableStockBySku($relatedItem->getSku());
                    $child_stocks[] = $this->getProductStockStateQty($relatedItem);
                    $child_images[] = $this->imageHelper->getUrl($relatedItem);
                }
                $productData['child_prices'] = $child_prices;
                $productData['child_list_prices'] = $child_list_prices;
                $productData['child_images'] = $child_images;
                $productData['child_stocks'] = $child_stocks;
                break;
            case self::PRODUCT_TYPE_BUNDLE:
                $image = $this->imageHelper->getUrl($item);
                $price = $this->formatPrice($item->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue());
                $list_price = $this->formatPrice($item->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue());
                $bundleItems = array();
                $selectionCollection = $item->getTypeInstance(true)->getSelectionsCollection(
                    $item->getTypeInstance(true)->getOptionsIds($item),
                    $item
                );

                foreach ($selectionCollection as $selection) {
                    $selectionArray = array();
                    $selectionArray['min_qty'] = $selection->getSelectionQty();
                    $selectionArray['stock'] = $this->stockStateInterface->getStockQty($selection->getProductId(), $item->getStore()->getWebsiteId());
                    $bundleItems[$selection->getOptionId()][$selection->getSelectionId()] = $selectionArray;
                }

                foreach ($bundleItems as $bundleItem) {
                    $bundleOptionMinStock = 0;
                    foreach ($bundleItem as $bundle_option) {
                        if ((integer)$bundle_option['min_qty'] <= $bundle_option['stock']) {
                            $bundleOptionMinStock = ($bundleOptionMinStock == 0) ? $bundle_option['stock'] : $bundleOptionMinStock;
                            $bundleOptionMinStock = ($bundleOptionMinStock < $bundle_option['stock']) ? $bundle_option['stock'] : $bundleOptionMinStock;
                        }
                    }
                    $stock = ($stock == 0) ? $bundleOptionMinStock : $stock;
                    $stock = ($stock < $bundleOptionMinStock) ? $bundleOptionMinStock : $stock;
                }
                $multi_source_stock = $this->getSourceStockBySku($item->getSku());
                break;
            case self::PRODUCT_TYPE_SIMPLE:
            default:
                $image = $this->imageHelper->getUrl($item);
                $price = $this->formatPrice($this->getProductTaxPrice($item, $item->getFinalPrice(), true));
                $price_excl_tax = $this->formatPrice($this->getProductTaxPrice($item, $item->getFinalPrice(), false));
                $list_price = $this->formatPrice($this->getProductTaxPrice($item, $item->getPrice(), true));
                $list_price_excl_tax = $this->formatPrice($this->getProductTaxPrice($item, $item->getPrice(), false));
                $stock = $this->getProductStockStateQty($item) ?? $this->getSaleableStockBySku($relatedItem->getSku());
                $multi_source_stock = $this->getSourceStockBySku($item->getSku());
                break;
        }
        $productData['price'] = $price;
        $productData['price_excl_tax'] = $price_excl_tax;
        $productData['list_price'] = $list_price;
        $productData['list_price_excl_tax'] = $list_price_excl_tax;
        $productData['stock'] = $stock;
        $productData['multi_source_stock'] = $multi_source_stock;
        $productData['image'] = $image;
        return $productData;
    }

    /**
     * Get source stock from SKU
     * @param int|string $sku
     * @return int
     */
    protected function getSourceStockBySku(int|string $sku): int
    {
        $stockTotal = 0;
        if ($this->msiEnabled) {
            $sourceItems = $this->objectManager->create('Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku')->execute($sku);
            foreach ($sourceItems as $sourceItem) {
                $stockTotal += $sourceItem->getQuantity();
            }
        }
        return $stockTotal;
    }

    /**
     * Format Price to 2 decimals
     * @param float|int $price
     * @return float $price
     */
    protected function formatPrice(float|int $price): float
    {
        return (float)number_format((float)$price, 2, ".", "");
    }

    /**
     * Get Product price with contextual taxes
     */

    protected function getProductTaxPrice($product, $price, $withTax = true): float
    {
        return $this->taxHelper->getTaxPrice($product, $price, $withTax, null, null, null, $this->store, null, true);
    }

    /**
     * Get Product stock from interface
     */
    protected function getProductStockStateQty($product): float|int
    {
        $product_stock = $this->stockStateInterface->getStockQty($product->getId(), $product->getStore()->getWebsiteId());
        return $product_stock ?? 0;
    }

    /**
     * Get Global Stock
     * @param int|string $sku
     * @return int
     */

    protected function getSaleableStockBySku(int|string $sku): int
    {
        $stockQuantity = 0;
        try {
            if ($this->msiEnabled) {
                $stockInfo = $this->objectManager->create('Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku')->execute($sku);
                if (!empty($stockInfo)) {
                    foreach ($stockInfo as $stockEntity) {
                        if (array_key_exists('qty', $stockEntity)) {
                            $stockQuantity += $stockEntity['qty'];
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
        return $stockQuantity;
    }

    /**
     * Add field handlers for products
     * @throws FileSystemException
     */
    protected function addFieldHandlers(): void
    {

        try {

            //Add age fieldhandler
            $this->addFieldHandler('age', function ($item) {
                return floor((time() - strtotime($item->getCreatedAt())) / (60 * 60 * 24));
            });

            //Add created_at fieldhandler
            $this->addFieldHandler('created_at', function ($item) {
                return strtotime($item->getCreatedAt());
            });

            $this->addFieldHandler('product_type', function ($item) {
                return $item->getTypeId();
            });

            $this->addFieldHandler('manufacturer', function ($item) {
                return $this->getAttributeValue($item, 'manufacturer');
            });

            $this->addFieldHandler('description_html', function ($item) {
                return $this->getAttributeValue($item, 'description') ? htmlentities($this->getAttributeValue($item, 'description'), ENT_QUOTES) : '';
            });

            $this->addFieldHandler('description', function ($item) {
                return $this->getAttributeValue($item, 'description') ? str_replace(array("\r", "\n"), ' ', strip_tags(html_entity_decode($this->getAttributeValue($item, 'description')))) : '';
            });

            $this->addFieldhandler('visibility', function ($item) {
                return $item->getattributetext('visibility');
            });

            $this->addFieldHandler('tax_rate', function ($item) {
                foreach ($this->productTaxRates as $tax) {
                    if (array_key_exists('tax_calculation_rate_id', $tax) && $item->getTaxClassId() == $tax['tax_calculation_rate_id']) {
                        return (float)$tax['rate'];
                    }
                }
                return 0;
            });

            $this->addFieldHandler('tier_price_values', function ($item) {
                $tierPriceValues = array();
                $tierPrices = $item->getTierPrice();
                if (!empty($tierPrices)) {
                    foreach ($tierPrices as $tierPrice) {
                        if (isset($tierPrice['price'])) {
                            $tierPriceValues[] = (float)$tierPrice['price'];
                        }
                    }
                }
                return $tierPriceValues;
            });

            $this->addFieldHandler('tier_price_quantities', function ($item) {
                $tierPriceQuantities = array();
                $tierPrices = $item->getTierPrice();
                if (!empty($tierPrices)) {
                    foreach ($tierPrices as $tierPrice) {
                        if (isset($tierPrice['price_qty'])) {
                            $tierPriceQuantities[] = (int)$tierPrice['price_qty'];
                        }
                    }
                }
                return $tierPriceQuantities;
            });

            //Add image fieldhandler
            $this->addFieldHandler('image', function ($item) {
                return $this->imageHelper->getUrl($item);
            });

            //Add url fieldhandler
            $this->addFieldHandler('url', function ($item) {
                return $item->setStoreId($this->storeId)->getUrlInStore();
            });

            //Add categories fieldhandler
            $this->addFieldHandler('categories', function ($item) {
                return $item->getCategoryIds();
            });

        } catch (Exception $e) {
            $this->clerkLogger->error('Getting Field Handlers Error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get attribute value for product
     *
     * @param $resourceItem
     * @param $field
     * @return mixed
     * @throws FileSystemException
     */
    protected function getAttributeValue($resourceItem, $field): mixed
    {
        try {

            $attributeResource = $resourceItem->getResource();

            if (!$attributeResource) {
                return parent::getAttributeValue($resourceItem, $field);
            }

            $attribute = $attributeResource->getAttribute($field);

            if (!is_bool($attribute) && is_object($attribute)) {
                if ($attribute->usesSource()) {
                    $source = $attribute->getSource();
                    if ($source) {
                        return $source->getOptionText($resourceItem[$field]);
                    }
                }
            }

            return parent::getAttributeValue($resourceItem, $field);

        } catch (Exception $e) {

            $this->clerkLogger->error('Getting Attribute Value Error', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * Get default product fields
     *
     * @param string $scope
     * @param int|string $scopeid
     * @return array
     * @throws FileSystemException
     */
    protected function getDefaultFields(string $scope, int|string $scopeid): array
    {

        $fields = [
            'name',
            'description',
            'image',
            'url',
            'categories',
            'manufacturer',
            'sku',
            'age',
            'created_at',
            'stock',
            'product_type',
            'tier_price_values',
            'tier_price_quantities',
            'child_images',
            'tax_rate'
        ];
        try {


            $additionalFields = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS, $scope, $scopeid);

            if ($additionalFields) {
                $fields = array_merge($fields, str_replace(' ', '', explode(',', $additionalFields)));
            }

            foreach ($fields as $key => $field) {
                $fields[$key] = $field;
            }

            return $fields;

        } catch (Exception $e) {

            $this->clerkLogger->error('Getting Default Fields Error', ['error' => $e->getMessage()]);

        }

        return $fields;
    }
}

