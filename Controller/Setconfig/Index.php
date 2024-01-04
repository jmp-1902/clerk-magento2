<?php

namespace Clerk\Clerk\Controller\Setconfig;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface as CacheType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{
    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var ModuleList
     */
    protected ModuleList $moduleList;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var WriterInterface
     */
    protected WriterInterface $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $ScopeConfigInterface;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * @var CacheType
     */
    protected CacheType $cacheType;
    private WriterInterface $config_writer;

    /**
     * Version controller constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $ScopeConfigInterface
     * @param LoggerInterface $logger
     * @param ModuleList $moduleList
     * @param StoreManagerInterface $storeManager
     * @param ClerkLogger $clerkLogger
     * @param WriterInterface $configWriter
     * @param ProductMetadataInterface $productMetadata
     * @param CacheType $cacheType
     * @param RequestApi $request_api
     * @param Api $api
     */
    public function __construct(
        Context                  $context,
        ScopeConfigInterface     $ScopeConfigInterface,
        LoggerInterface          $logger,
        ModuleList               $moduleList,
        StoreManagerInterface    $storeManager,
        ClerkLogger              $clerkLogger,
        WriterInterface          $configWriter,
        ProductMetadataInterface $productMetadata,
        CacheType                $cacheType,
        RequestApi               $request_api,
        Api                      $api
    )
    {
        $this->clerkLogger = $clerkLogger;
        $this->config_writer = $configWriter;
        $this->cacheType = $cacheType;
        parent::__construct(
            $context,
            $storeManager,
            $ScopeConfigInterface,
            $logger,
            $moduleList,
            $clerkLogger,
            $productMetadata,
            $request_api,
            $api
        );
    }

    /**
     * Execute request
     * @throws FileSystemException
     */
    public function execute(): void
    {
        try {

            $post = $this->getRequest()->getcontent();
            $scope = $this->getRequest()->getParam('scope');
            if ($scope !== 'default') {
                $scope = $scope . 's';
            }
            $scopeId = intval($this->getRequest()->getParam('scope_id'));
            $arr_settings = [];

            if ($post) {
                $arr_settings = json_decode($post, true);

                $count = 0;
                foreach ($arr_settings as $key => $value) {

                    // general

                    if ($key == "LANGUAGE") {
                        $this->config_writer->save(Config::XML_PATH_LANGUAGE, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PATH_INCLUDE_PAGES") {
                        $this->config_writer->save(Config::XML_PATH_INCLUDE_PAGES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PAGES_ADDITIONAL_FIELDS") {
                        $this->config_writer->save(Config::XML_PATH_PAGES_ADDITIONAL_FIELDS, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_REAL_TIME_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_COLLECT_EMAILS") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_COLLECT_EMAILS, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_COLLECT_BASKETS") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_COLLECT_BASKETS, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS_HEAVY_QUERY") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_ADDITIONAL_FIELDS_HEAVY_QUERY, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_SALABLE_ONLY") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_SALABLE_ONLY, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_VISIBILITY") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_VISIBILITY, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_DISABLE_ORDER_SYNCHRONIZATION") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_DISABLE_ORDER_SYNCHRONIZATION, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_SYNCHRONIZATION_IMAGE_TYPE") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_IMAGE_TYPE, $value, $scope, $scopeId);
                        $count++;
                    }

                    //customer
                    if ($key == "SUBSCRIBER_SYNCHRONIZATION_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_SUBSCRIBER_SYNCHRONIZATION_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "CUSTOMER_SYNCHRONIZATION_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_CUSTOMER_SYNCHRONIZATION_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "CUSTOMER_SYNCHRONIZATION_EXTRA_ATTRIBUTES") {
                        $this->config_writer->save(Config::XML_PATH_CUSTOMER_SYNCHRONIZATION_EXTRA_ATTRIBUTES, $value, $scope, $scopeId);
                        $count++;
                    }

                    if ($key == "SEARCH_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_INCLUDE_CATEGORIES") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_INCLUDE_CATEGORIES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_CATEGORIES") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_CATEGORIES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_PAGES") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_PAGES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_PAGES_TYPE") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_PAGES_TYPE, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_TEMPLATE") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_TEMPLATE, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_NO_RESULTS_TEXT") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_NO_RESULTS_TEXT, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "SEARCH_LOAD_MORE_TEXT") {
                        $this->config_writer->save(Config::XML_PATH_SEARCH_LOAD_MORE_TEXT, $value, $scope, $scopeId);
                        $count++;
                    }

                    //facets

                    if ($key == "FACETED_SEARCH_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_FACETED_SEARCH_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "FACETED_SEARCH_DESIGN") {
                        $this->config_writer->save(Config::XML_PATH_FACETED_SEARCH_DESIGN, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "FACETED_SEARCH_ATTRIBUTES") {
                        $this->config_writer->save(Config::XML_PATH_FACETED_SEARCH_ATTRIBUTES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "FACETED_SEARCH_MULTISELECT_ATTRIBUTES") {
                        $this->config_writer->save(Config::XML_PATH_FACETED_SEARCH_MULTISELECT_ATTRIBUTES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "FACETED_SEARCH_TITLES") {
                        $this->config_writer->save(Config::XML_PATH_FACETED_SEARCH_TITLES, $value, $scope, $scopeId);
                        $count++;
                    }

                    // livesearch

                    if ($key == "LIVESEARCH_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_INCLUDE_CATEGORIES") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_INCLUDE_CATEGORIES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_CATEGORIES") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_CATEGORIES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_SUGGESTIONS") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_SUGGESTIONS, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_PAGES") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_PAGES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_PAGES_TYPE") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_PAGES_TYPE, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_DROPDOWN_POSITION") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_DROPDOWN_POSITION, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_TEMPLATE") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_TEMPLATE, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_INPUT_SELECTOR") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_INPUT_SELECTOR, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LIVESEARCH_FORM_SELECTOR") {
                        $this->config_writer->save(Config::XML_PATH_LIVESEARCH_FORM_SELECTOR, $value, $scope, $scopeId);
                        $count++;
                    }

                    // powerstep

                    if ($key == "POWERSTEP_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_POWERSTEP_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "POWERSTEP_TYPE") {
                        $this->config_writer->save(Config::XML_PATH_POWERSTEP_TYPE, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "POWERSTEP_TEMPLATES") {
                        $this->config_writer->save(Config::XML_PATH_POWERSTEP_TEMPLATES, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "POWERSTEP_FILTER_DUPLICATES") {
                        $this->config_writer->save(Config::XML_PATH_POWERSTEP_FILTER_DUPLICATES, $value, $scope, $scopeId);
                        $count++;
                    }

                    // exit intent

                    if ($key == "EXIT_INTENT_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_EXIT_INTENT_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "EXIT_INTENT_TEMPLATE") {
                        $this->config_writer->save(Config::XML_PATH_EXIT_INTENT_TEMPLATE, $value, $scope, $scopeId);
                        $count++;
                    }

                    //category

                    if ($key == "CATEGORY_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_CATEGORY_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "CATEGORY_CONTENT") {
                        $this->config_writer->save(Config::XML_PATH_CATEGORY_CONTENT, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "CATEGORY_FILTER_DUPLICATES") {
                        $this->config_writer->save(Config::XML_PATH_CATEGORY_FILTER_DUPLICATES, $value, $scope, $scopeId);
                        $count++;
                    }

                    // product

                    if ($key == "PRODUCT_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_CONTENT") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_CONTENT, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "PRODUCT_FILTER_DUPLICATES") {
                        $this->config_writer->save(Config::XML_PATH_PRODUCT_FILTER_DUPLICATES, $value, $scope, $scopeId);
                        $count++;
                    }

                    // cart

                    if ($key == "CART_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_CART_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "CART_CONTENT") {
                        $this->config_writer->save(Config::XML_PATH_CART_CONTENT, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "CART_FILTER_DUPLICATES") {
                        $this->config_writer->save(Config::XML_PATH_CART_FILTER_DUPLICATES, $value, $scope, $scopeId);
                        $count++;
                    }

                    // log

                    if ($key == "LOG_LEVEL") {
                        $this->config_writer->save(Config::XML_PATH_LOG_LEVEL, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LOG_TO") {
                        $this->config_writer->save(Config::XML_PATH_LOG_TO, $value, $scope, $scopeId);
                        $count++;
                    }
                    if ($key == "LOG_ENABLED") {
                        $this->config_writer->save(Config::XML_PATH_LOG_ENABLED, $value, $scope, $scopeId);
                        $count++;
                    }

                } // foreach

                if ($count != 0) {
                    $this->cacheType->cleanType('config');
                }
            } // if post


            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true);

            $response = [

                'ok' => 'ok',
                '$arr_settings' => $arr_settings,
                'scope' => $scope,
                'scopeId' => $scopeId

            ];


            if ($this->debug) {
                $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
            } else {
                $this->getResponse()->setBody(json_encode($response));
            }


        } catch (Exception $e) {

            $this->clerkLogger->error('Setconfig execute ERROR', ['error' => $e->getMessage()]);

        }
    }
}
