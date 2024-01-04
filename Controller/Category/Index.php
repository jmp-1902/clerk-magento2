<?php

namespace Clerk\Clerk\Controller\Category;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Exception;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Cms\Helper\Page;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{

    /**
     * @var array
     */
    protected array $fieldMap = [
        'entity_id' => 'id',
        'parent_id' => 'parent',
    ];

    /**
     * @var string
     */
    protected string $eventPrefix = 'clerk_category';

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var ModuleList
     */
    protected ModuleList $moduleList;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;
    private Page $pageHelper;

    /**
     * Category controller constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $categoryCollectionFactory
     * @param CategoryFactory $categoryFactory
     * @param LoggerInterface $logger
     * @param PageCollectionFactory $pageCollectionFactory
     * @param Page $pageHelper
     * @param ClerkLogger $clerkLogger
     * @param ModuleList $moduleList
     * @param ProductMetadataInterface $productMetadata
     * @param RequestApi $requestApi
     * @param Api $api
     * @throws FileSystemException
     */
    public function __construct(
        Context                  $context,
        ScopeConfigInterface     $scopeConfig,
        StoreManagerInterface    $storeManager,
        CollectionFactory        $categoryCollectionFactory,
        CategoryFactory          $categoryFactory,
        LoggerInterface          $logger,
        protected ClerkLogger    $clerkLogger,
        ModuleList               $moduleList,
        ProductMetadataInterface $productMetadata,
        RequestApi               $requestApi,
        Api                      $api
    )
    {
        $this->moduleList = $moduleList;
        $this->collectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->fields = [
            "entity_id",
            "parent_id"
        ];
        $this->addFieldHandlers();

        parent::__construct(
            $context,
            $storeManager,
            $scopeConfig,
            $logger,
            $moduleList,
            $clerkLogger,
            $productMetadata,
            $requestApi,
            $api
        );
    }

    /**
     * Add field handlers
     * @throws FileSystemException
     */
    protected function addFieldHandlers(): void
    {
        try {
            //Add parent fieldhandler
            $this->addFieldHandler('parent', function ($item) {
                return $item->getParentId();
            });

            $this->addFieldHandler('parent_name', function ($item) {
                $parentId = $item->getParentId();
                $parent = $this->categoryFactory->create()->load($parentId);
                return $parent->getName();
            });

            //Add url fieldhandler
            $this->addFieldHandler('url', function ($item) {
                return $item->getUrl();
            });

            //Add subcategories fieldhandler
            $this->addFieldHandler('subcategories', function ($item) {
                $children = $item->getAllChildren(true);
                //Remove own ID from subcategories array
                return array_values(array_diff($children, [$item->getId()]));
            });

            $this->addFieldHandler('name', function ($item) {
                return $item->getName();
            });

        } catch (Exception $e) {

            $this->clerkLogger->error('Category addFieldHandlers ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * Execute request
     * @throws FileSystemException
     */
    public function execute(): void
    {
        try {

            $collection = $this->prepareCollection();

            $this->_eventManager->dispatch($this->eventPrefix . '_get_collection_after', [
                'controller' => $this,
                'collection' => $collection
            ]);

            $response = [];

            if ($this->page <= $collection->getLastPageNumber()) {
                foreach ($collection as $resourceItem) {

                    $item = [];

                    foreach ($this->fields as $field) {

                        if (isset($resourceItem[$field])) {
                            $item[$this->getFieldName($field)] = $this->getAttributeValue($resourceItem, $field);
                        }

                        if (isset($this->fieldHandlers[$field])) {
                            $item[$field] = $this->fieldHandlers[$field]($resourceItem);
                        }
                    }

                    $response[] = $item;
                }
            }

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true);

            if ($this->debug) {
                $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
                $this->clerkLogger->log('Fetched ' . $this->page . ' with ' . count($response) . ' Categories', ['response' => $response]);
            } else {
                $this->getResponse()->setBody(json_encode($response));
                $this->clerkLogger->log('Fetched page ' . $this->page . ' with ' . count($response) . ' Categories', ['response' => $response]);
            }
        } catch (Exception $e) {
            $this->getResponse()
                ->setHttpResponseCode(500)
                ->setHeader('Content-Type', 'application/json', true)
                ->representJson(
                    json_encode([
                        'error' => [
                            'code' => 500,
                            'message' => 'An exception occurred',
                            'description' => $e->getMessage(),
                        ]
                    ])
                );

            $this->clerkLogger->error('Category execute ERROR', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Prepare collection
     *
     * @return object|null
     * @throws FileSystemException
     */
    protected function prepareCollection(): ?object
    {
        try {

            $collection = $this->collectionFactory->create();

            $rootCategory = $this->storeManager->getStore()->getRootCategoryId();

            $collection->addFieldToSelect('*');
            $collection->addAttributeToFilter('level', ['gteq' => 2]);
            $collection->addAttributeToFilter('name', ['neq' => null]);
            $collection->addPathsFilter('1/' . $rootCategory . '/%');
            $collection->addFieldToFilter('is_active', ["in" => ['1']]);


            $collection->setCurPage($this->page)->setPageSize($this->limit);


            return $collection;

        } catch (Exception $e) {

            $this->clerkLogger->error('Category prepareCollection ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }
}
