<?php

namespace Clerk\Clerk\Model\Adapter;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractAdapter
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
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $eventManager;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var mixed
     */
    protected mixed $collectionFactory;

    /**
     * @var array
     */
    protected array $fieldMap;

    /**
     * @var array
     */
    protected array $fields = [];

    /**
     * @var array
     */
    protected array $fieldHandlers = [];

    /**
     * AbstractAdapter constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ManagerInterface $eventManager
     * @param StoreManagerInterface $storeManager
     * @param mixed $collectionFactory
     * @param ClerkLogger $clerkLogger
     */
    public function __construct(
        ScopeConfigInterface  $scopeConfig,
        ManagerInterface      $eventManager,
        StoreManagerInterface $storeManager,
        CollectionFactory     $collectionFactory,
        ClerkLogger           $clerkLogger
    )
    {
        $this->clerkLogger = $clerkLogger;
        $this->scopeConfig = $scopeConfig;
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        $this->addFieldHandlers();
    }

    /**
     * Add default fieldhandlers
     */
    abstract protected function addFieldHandlers();

    /**
     * @param $fields
     * @param $page
     * @param $limit
     * @param $orderBy
     * @param $order
     * @param $scope
     * @param $scopeid
     * @return array
     * @throws FileSystemException
     */
    public function getResponse($fields, $page, $limit, $orderBy, $order, $scope, $scopeid): array
    {

        $response = [];
        try {

            if ($this->storeManager->isSingleStoreMode()) {
                $scope = 'store';
                $scopeid = $this->storeManager->getDefaultStoreView()->getId();
            }

            $this->setFields($fields, $scope, $scopeid);

            $collection = $this->prepareCollection($page, $limit, $orderBy, $order, $scope, $scopeid);

            if ($page <= $collection->getLastPageNumber()) {
                //Build response
                foreach ($collection as $resourceItem) {
                    $item = $this->getInfoForItem($resourceItem, $scope, $scopeid);

                    $response[] = $item;
                }
            }

        } catch (Exception $e) {
            $this->clerkLogger->error('Getting Response ERROR', ['error' => $e->getMessage()]);
        }
        return $response;
    }

    /**
     * @param $page
     * @param $limit
     * @param $orderBy
     * @param $order
     * @param $scope
     * @param $scopeid
     * @return mixed
     */
    abstract protected function prepareCollection($page, $limit, $orderBy, $order, $scope, $scopeid): mixed;

    /**
     * Get information for single resource item
     *
     * @param $resourceItem
     * @param $scope
     * @param $scopeid
     * @return array
     * @throws FileSystemException
     */
    public function getInfoForItem($resourceItem, $scope, $scopeid): array
    {
        $info = array();

        try {

            $fields = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS, $scope, $scopeid);
            $emulateFields = $this->scopeConfig->getValue(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS_HEAVY_QUERY, $scope, $scopeid);
            $fields = is_string($fields) ? str_replace(' ', '', explode(',', $fields)) : [];
            $typeId = $resourceItem->getTypeId();
            $typeInstance = $resourceItem->getTypeInstance();

            $this->setFields($fields, $scope, $scopeid);

            $relatedResourceItems = array();
            if ($typeId === self::PRODUCT_TYPE_CONFIGURABLE) {
                $relatedResourceItems = $typeInstance->getUsedProducts($resourceItem);
            } elseif ($typeId === self::PRODUCT_TYPE_GROUPED) {
                $relatedResourceItems = $typeInstance->getAssociatedProducts($resourceItem);
            }

            foreach ($this->getFields() as $field) {
                if (isset($this->fieldHandlers[$field])) {
                    $info[$this->getFieldName($field)] = $this->fieldHandlers[$field]($resourceItem);
                }

                $attributeValue = null;
                if (isset($resourceItem[$field]) && !array_key_exists($field, $info)) {
                    $attributeValue = $this->getAttributeValue($resourceItem, $field);
                }

                if (!isset($attributeValue) && $emulateFields) {
                    $attributeValue = $this->getAttributeValueHeavy($resourceItem, $field);
                }

                if (!is_null($attributeValue) && !array_key_exists($field, $info)) {
                    $info[$this->getFieldName($field)] = $attributeValue;
                }


                $attributeValues = $this->getInfoForChildItems($field, $emulateFields, $relatedResourceItems);
                if (!empty($attributeValues) && !array_key_exists('child_' . $this->getFieldName($field) . 's', $info)) {
                    $info["child_" . $this->getFieldName($field) . "s"] = $attributeValues;
                }
            }

            if (in_array($typeId, self::PRODUCT_TYPES)) {
                $info = $this->buildProductStockPriceImage($resourceItem, $relatedResourceItems, $typeId, $emulateFields, $info);
            }

            if (isset($info['price']) && isset($info['list_price'])) {
                $info['on_sale'] = $info['price'] < $info['list_price'];
            }

            // Fix for bundle products not reliably having implicit tax.
            if (isset($info['tax_rate']) && $info['product_type'] == self::PRODUCT_TYPE_BUNDLE) {
                if ($info['price'] === $info['price_excl_tax']) {
                    $info['price_excl_tax'] = $info['price'] / (1 + ($info['tax_rate'] / 100));
                }
                if ($info['list_price'] === $info['list_price_excl_tax']) {
                    $info['list_price_excl_tax'] = $info['list_price'] / (1 + ($info['tax_rate'] / 100));
                }
            }

        } catch (Exception $e) {

            $this->clerkLogger->error('Getting Response ERROR', ['error' => $e->getMessage()]);

        }

        return $info;
    }

    /**
     * Get list of fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Set fields to get
     *
     * @param $fields
     * @param $scope
     * @param $scopeid
     */
    public function setFields($fields, $scope, $scopeid): void
    {
        $this->fields = array_merge(['entity_id'], $this->getDefaultFields($scope, $scopeid), (array)$fields);
    }

    /**
     * Get mapped field name
     *
     * @param $field
     * @return mixed
     */
    protected function getFieldName($field): mixed
    {
        if (isset($this->fieldMap[$field])) {
            return $this->fieldMap[$field];
        }

        return $field;
    }

    /**
     * Get attribute value
     *
     * @param $resourceItem
     * @param $field
     * @return mixed
     */
    protected function getAttributeValue($resourceItem, $field): mixed
    {
        return $resourceItem[$field];
    }

    /**
     * Get attribute value for product by simulating resource
     *
     * @param $resourceItem
     * @param $field
     * @return mixed
     * @throws FileSystemException
     */
    public function getAttributeValueHeavy($resourceItem, $field): mixed
    {
        try {

            $attributeResource = $resourceItem->getResource();

            if (in_array($resourceItem->getTypeId(), self::PRODUCT_TYPES)) {
                $attributeResource->load($resourceItem, $resourceItem->getId(), [$field]);

                $customAttribute = $resourceItem->getCustomAttribute($field);
                if ($customAttribute) {
                    return $customAttribute->getValue();
                }
            }

        } catch (Exception $e) {

            $this->clerkLogger->error('Getting Attribute Value Error', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * @throws FileSystemException
     */
    public function getInfoForChildItems($field, $emulatedFields, $childProducts): array
    {
        $childAttributeValues = array();
        $entityField = 'entity_' . $field;
        if (!empty($childProducts)) {
            foreach ($childProducts as $associatedProduct) {
                if (isset($associatedProduct[$field])) {
                    $childAttributeValues[] = $this->getAttributeValue($associatedProduct, $field);
                } elseif (isset($associatedProduct[$entityField])) {
                    $childAttributeValues[] = $this->getAttributeValue($associatedProduct, $entityField);
                }
                if (empty($childAttributeValues) && $emulatedFields) {
                    $attributeValue = $this->getAttributeValueHeavy($associatedProduct, $field);
                    if (isset($attributeValue)) {
                        $childAttributeValues[] = $attributeValue;
                    }

                }
            }
        }
        if (is_array($childAttributeValues)) {
            $childAttributeValues = $this->flattenArray($childAttributeValues);
        }
        return $childAttributeValues;
    }

    /**
     * Flatten array
     *
     * @param array $array
     * @return array
     */
    public function flattenArray(array $array): array
    {
        $return = [];
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }

    /**
     * Add field to get
     *
     * @param $field
     */
    public function addField($field): void
    {
        $this->fields[] = $field;
    }

    /**
     * Add fieldhandler
     *
     * @param $field
     * @param callable $handler
     */
    public function addFieldHandler($field, callable $handler): void
    {
        $this->fieldHandlers[$field] = $handler;
    }


    /**
     * Get default fields
     *
     * @param string $scope
     * @param int|string $scopeid
     * @return array
     */
    abstract protected function getDefaultFields(string $scope, int|string $scopeid): array;
}
