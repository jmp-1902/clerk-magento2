<?php

namespace Clerk\Clerk\Controller\Order;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{
    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var array
     */
    protected array $fieldMap = [
        'increment_id' => 'id',
    ];

    /**
     * @var string
     */
    protected string $eventPrefix = 'clerk_order';

    /**
     * @var ModuleList
     */
    protected ModuleList $moduleList;

    /**
     * Order controller constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $orderCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ModuleList $moduleList
     * @param ClerkLogger $clerkLogger
     * @param ProductMetadataInterface $productMetadata
     * @param RequestApi $requestApi
     * @param Api $api
     * @throws FileSystemException
     */
    public function __construct(
        Context                  $context,
        ScopeConfigInterface     $scopeConfig,
        CollectionFactory        $orderCollectionFactory,
        StoreManagerInterface    $storeManager,
        LoggerInterface          $logger,
        ModuleList               $moduleList,
        ClerkLogger              $clerkLogger,
        ProductMetadataInterface $productMetadata,
        RequestApi               $requestApi,
        Api                      $api
    )
    {
        $this->collectionFactory = $orderCollectionFactory;
        $this->clerkLogger = $clerkLogger;
        $this->moduleList = $moduleList;
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

            //Add time fieldhandler
            $this->addFieldHandler('time', function ($item) {
                return strtotime($item->getCreatedAt());
            });

            //Add email fieldhandler
            $this->addFieldHandler('email', function ($item) {
                if ($this->scopeConfig->isSetFlag(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_COLLECT_EMAILS, $this->scope, $this->scopeid)) {
                    return $item->getCustomerEmail();
                }

                return null;
            });

            //Add customer fieldhandler
            $this->addFieldHandler('customer', function ($item) {
                return $item->getCustomerId();
            });

            //Add products fieldhandler
            $this->addFieldHandler('products', function ($item) {
                $products = [];
                foreach ($item->getAllVisibleItems() as $productItem) {
                    $products[] = [
                        'id' => $productItem->getProductId(),
                        'quantity' => (int)$productItem->getQtyOrdered(),
                        'price' => (float)$productItem->getPrice(),
                    ];
                }
                return $products;
            });

        } catch (Exception $e) {

            $this->clerkLogger->error('Order addFieldHandlers ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * Execute request
     * @throws FileSystemException
     */
    public function execute(): void
    {
        try {

            $disabled = $this->scopeConfig->isSetFlag(
                Config::XML_PATH_PRODUCT_SYNCHRONIZATION_DISABLE_ORDER_SYNCHRONIZATION,
                $this->scope,
                $this->scopeid
            );

            if ($disabled) {
                $this->getResponse()
                    ->setHttpResponseCode(200)
                    ->setHeader('Content-Type', 'application/json', true)
                    ->setBody(json_encode([]));

                $this->clerkLogger->log('Order Sync Disabled', ['response' => '']);

                return;
            }

            parent::execute();

        } catch (Exception $e) {

            $this->clerkLogger->error('Order execute ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * Parse request arguments
     * @throws FileSystemException
     */
    protected function getArguments(RequestInterface $request): void
    {
        try {
            parent::getArguments($request);

            //Use increment id instead of entity_id
            $this->fields = str_replace('entity_id', 'increment_id', $this->fields);

        } catch (Exception $e) {

            $this->clerkLogger->error('Order getArguments ERROR', ['error' => $e->getMessage()]);

        }
    }
}
