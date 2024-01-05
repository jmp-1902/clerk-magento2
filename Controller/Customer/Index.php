<?php

namespace Clerk\Clerk\Controller\Customer;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\SubscriberFactory as SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{

    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var SubscriberCollectionFactory
     */
    protected SubscriberCollectionFactory $subscriberCollectionFactory;

    /**
     * @var CollectionFactory
     */
    protected mixed $collectionFactory;

    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var CustomerMetadataInterface
     */
    protected $customerMetadata;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * @var string
     */
    protected string $eventPrefix = 'clerk_customer';

    /**
     * Customer controller constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $customerCollectionFactory
     * @param ProductMetadataInterface $product_metadata
     * @param RequestApi $request_api
     * @param Api $api
     */
    public function __construct(
        Context                     $context,
        StoreManagerInterface       $storeManager,
        ScopeConfigInterface        $scopeConfig,
        CollectionFactory           $customerCollectionFactory,
        LoggerInterface             $logger,
        ModuleList                  $moduleList,
        ClerkLogger                 $clerkLogger,
        CustomerMetadataInterface   $customerMetadata,
        ProductMetadataInterface    $product_metadata,
        RequestApi                  $request_api,
        SubscriberFactory           $subscriberFactory,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        Api                         $api
    )
    {
        $this->collectionFactory = $customerCollectionFactory;
        $this->clerkLogger = $clerkLogger;
        $this->customerMetadata = $customerMetadata;
        $this->storeManager = $storeManager;
        $this->subscriberFactory = $subscriberFactory;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;

        parent::__construct(
            $context,
            $storeManager,
            $scopeConfig,
            $logger,
            $moduleList,
            $clerkLogger,
            $product_metadata,
            $request_api,
            $api
        );
    }

    public function execute(): void
    {
        try {

            if ($this->scopeConfig->getValue(Config::XML_PATH_CUSTOMER_SYNCHRONIZATION_ENABLED, $this->scope, $this->scopeid)) {

                $Customers = [];
                $this->getResponse()
                    ->setHttpResponseCode(200)
                    ->setHeader('Content-Type', 'application/json', true);

                if (!empty($this->scopeConfig->getValue(Config::XML_PATH_CUSTOMER_SYNCHRONIZATION_EXTRA_ATTRIBUTES, $this->scope, $this->scopeid))) {

                    $fields = explode(',', str_replace(' ', '', $this->scopeConfig->getValue(Config::XML_PATH_CUSTOMER_SYNCHRONIZATION_EXTRA_ATTRIBUTES, $this->scope, $this->scopeid)));

                } else {

                    $fields = [];

                }

                $response = $this->getCustomerCollection($this->page, $this->limit, $this->scopeid);

                $subscriberInstance = $this->subscriberFactory->create();

                foreach ($response->getData() as $customer) {

                    $_customer = array();
                    $_customer['id'] = $customer['entity_id'];
                    if (!is_null($customer['middlename'])) {
                        $_customer['name'] = sprintf("%s %s %s", $customer['firstname'], $customer['middlename'], $customer['lastname']);
                    } else {
                        $_customer['name'] = sprintf("%s %s", $customer['firstname'], $customer['lastname']);
                    }
                    $_customer['email'] = $customer['email'];


                    foreach ($fields as $field) {
                        if (isset($customer[$field])) {
                            if ($field == "gender") {

                                $_customer[$field] = $this->getCustomerGender($customer[$field]);

                            } else {

                                $_customer[$field] = $customer[$field];

                            }

                        }
                    }

                    if ($this->scopeConfig->getValue(Config::XML_PATH_SUBSCRIBER_SYNCHRONIZATION_ENABLED, $this->scope, $this->scopeid)) {
                        $sub_state = $subscriberInstance->loadByEmail($customer['email']);
                        if ($sub_state->getId()) {
                            $_customer['subscribed'] = (bool)$sub_state->getSubscriberStatus();
                        } else {
                            $_customer['subscribed'] = false;
                        }
                        $_customer['unsub_url'] = $sub_state->getUnsubscriptionLink();
                    }

                    $Customers[] = $_customer;
                }

                if ($this->scopeConfig->getValue(Config::XML_PATH_SUBSCRIBER_SYNCHRONIZATION_ENABLED, $this->scope, $this->scopeid)) {

                    $subscribersOnlyResponse = $this->getSubscriberCollection($this->page, $this->limit, $this->scopeid);

                    foreach ($subscribersOnlyResponse->getData() as $subscriber) {
                        if (isset($subscriber['subscriber_id'])) {
                            $sub_state = $subscriberInstance->loadByEmail($subscriber['subscriber_email']);
                            $_sub = array();
                            $_sub['id'] = 'SUB' . $subscriber['subscriber_id'];
                            $_sub['email'] = $subscriber['subscriber_email'];
                            $_sub['subscribed'] = (bool)$subscriber['subscriber_status'];
                            $_sub['name'] = "";
                            $_sub['firstname'] = "";
                            $_sub['unsub_url'] = $sub_state->getUnsubscriptionLink();
                            $Customers[] = $_sub;
                        }
                    }
                }

                if ($this->debug) {
                    $this->getResponse()->setBody(json_encode($Customers, JSON_PRETTY_PRINT));
                } else {
                    $this->getResponse()->setBody(json_encode($Customers));
                }
            } else {

                $this->getResponse()
                    ->setHttpResponseCode(200)
                    ->setHeader('Content-Type', 'application/json', true);

                $this->getResponse()->setBody(json_encode([]));

            }

        } catch (Exception $e) {

            $this->clerkLogger->error('Customer execute ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getCustomerCollection($page, $limit, $store_id)
    {
        $store = $this->storeManager->getStore($store_id);
        $customerCollection = $this->collectionFactory->create();
        $customerCollection->setOrder('title', 'ASC');
        $customerCollection->addFilter('store_id', $store->getId());
        $customerCollection->setPageSize($limit);
        $customerCollection->setCurPage($page);
        return $customerCollection;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCustomerGender($GenderCode): string
    {
        return $this->customerMetadata->getAttributeMetadata('gender')->getOptions()[$GenderCode]->getLabel();
    }

    public function getSubscriberCollection($page, $limit, $store_id)
    {
        $subscriberCollection = $this->subscriberCollectionFactory->create();
        $subscriberCollection->addFilter('store_id', $store_id);
        $subscriberCollection->addFilter('customer_id', 0);
        $subscriberCollection->setPageSize($limit);
        $subscriberCollection->setCurPage($page);
        return $subscriberCollection;
    }
}
