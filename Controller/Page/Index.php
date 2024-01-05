<?php

namespace Clerk\Clerk\Controller\Page;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Helper\Page as PageHelper;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{

    /**
     * @var PageFactory
     */
    protected PageFactory $pageFactory;

    /**
     * @var PageHelper
     */
    protected PageHelper $pageHelper;

    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var PageRepositoryInterface
     */
    protected PageRepositoryInterface $pageRepositoryInterface;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var ModuleList
     */
    protected ModuleList $moduleList;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * Index constructor.
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param PageRepositoryInterface $PageRepositoryInterface
     * @param SearchCriteriaBuilder $SearchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     * @param ClerkLogger $clerk_logger
     * @param ModuleList $moduleList
     * @param PageHelper $pageHelper
     * @param ProductMetadataInterface $productMetadata
     * @param PageFactory $pageFactory
     * @param RequestApi $request_api
     * @param Api $api
     */
    public function __construct(
        Context                      $context,
        ScopeConfigInterface         $scopeConfig,
        PageRepositoryInterface      $PageRepositoryInterface,
        SearchCriteriaBuilder        $SearchCriteriaBuilder,
        StoreManagerInterface        $storeManager,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface              $logger,
        ClerkLogger                  $clerk_logger,
        ModuleList                   $moduleList,
        PageHelper                   $pageHelper,
        ProductMetadataInterface     $productMetadata,
        PageFactory                  $pageFactory,
        RequestApi                   $request_api,
        Api                          $api
    )
    {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->pageRepositoryInterface = $PageRepositoryInterface;
        $this->searchCriteriaBuilder = $SearchCriteriaBuilder;
        $this->clerkLogger = $clerk_logger;
        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList;
        $this->storeManager = $storeManager;
        $this->pageHelper = $pageHelper;
        $this->pageFactory = $pageFactory;
        parent::__construct(
            $context,
            $storeManager,
            $scopeConfig,
            $logger,
            $moduleList,
            $clerk_logger,
            $productMetadata,
            $request_api,
            $api
        );
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function execute(): void
    {

        try {

            $includePages = $this->scopeConfig->getValue(Config::XML_PATH_INCLUDE_PAGES, $this->scope, $this->scopeid);

            $additionalFieldsPages = is_string($this->scopeConfig->getValue(Config::XML_PATH_PAGES_ADDITIONAL_FIELDS, $this->scope, $this->scopeid)) ? explode(',', $this->scopeConfig->getValue(Config::XML_PATH_PAGES_ADDITIONAL_FIELDS, $this->scope, $this->scopeid)) : [];

            $pages = [];

            if ($includePages) {

                $this->getResponse()
                    ->setHttpResponseCode(200)
                    ->setHeader('Content-Type', 'application/json', true);

                // collection of pages visible on all views
                $pages_default = $this->getPageCollection($this->page, $this->limit, 0);
                $pages_store = $this->getPageCollection($this->page, $this->limit, $this->scopeid);
                $pages_all = array_merge($pages_default->getData(), $pages_store->getData());
                foreach ($pages_all as $item) {
                    $page = array();
                    try {
                        $url = $this->pageHelper->getPageUrl($item['page_id']);

                        if (!$url) {
                            continue;
                        }

                        $page['id'] = $item['page_id'];
                        $page['type'] = 'cms page';
                        $page['url'] = $url;
                        $page['title'] = $item['title'];
                        $page['text'] = $item['content'];

                        if (!$this->validatePageMandatoryFields($page)) {
                            continue;
                        }

                        foreach ($additionalFieldsPages as $field) {
                            $field = str_replace(' ', '', $field);
                            if (empty($item[$field])) {
                                continue;
                            }

                            $page[$field] = $item[$field];
                        }

                        $pages[] = $page;

                    } catch (Exception $e) {
                        continue;
                    }
                }
            }

            $this->getResponse()->setBody(json_encode($pages));

        } catch (Exception $e) {

            $this->clerkLogger->error('Product execute ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getPageCollection($page, $limit, $storeId)
    {

        $store = $this->storeManager->getStore($storeId);
        $collection = $this->pageFactory->create();
        $collection->addFilter('is_active', 1);
        $collection->addFilter('store_id', $store->getId());
        $collection->addStoreFilter($store);
        $collection->setPageSize($limit);
        $collection->setCurPage($page);
        return $collection;
    }

    /**
     * @param $page
     * @return bool
     */
    public function validatePageMandatoryFields($page): bool
    {
        foreach ($page as $key => $content) {
            if (empty($content)) {
                return false;
            }
        }
        return true;
    }
}
