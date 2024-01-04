<?php

namespace Clerk\Clerk\Controller\Version;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Store\Model\ScopeInterface;
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
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * Version controller constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ModuleList $moduleList
     * @param StoreManagerInterface $storeManager
     * @param ClerkLogger $clerkLogger
     * @param ProductMetadataInterface $productMetadata
     * @param RequestApi $requestApi
     * @param Api $api
     */
    public function __construct(
        Context                  $context,
        ScopeConfigInterface     $scopeConfig,
        LoggerInterface          $logger,
        ModuleList               $moduleList,
        StoreManagerInterface    $storeManager,
        ClerkLogger              $clerkLogger,
        ProductMetadataInterface $productMetadata,
        RequestApi               $requestApi,
        Api                      $api
    )
    {
        $this->moduleList = $moduleList;
        $this->clerkLogger = $clerkLogger;
        $this->productMetadata = $productMetadata;
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
     * Execute request
     */
    public function execute(): void
    {
        try {
            $version = $this->productMetadata->getVersion();

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true);

            if ($this->storeManager->isSingleStoreMode()) {
                $scope = 'default';
                $scope_id = '0';
            } else {
                $scope = ScopeInterface::SCOPE_STORE;
                $scope_id = $this->storeManager->getStore()->getId();
            }

            $response = [
                'platform' => 'Magento2',
                'platform_version' => $version,
                'clerk_version' => $this->moduleList->getOne('Clerk_Clerk')['setup_version'],
                'php_version' => phpversion(),
                'scope' => $scope,
                'scope_id' => $scope_id
            ];

            if ($this->debug) {
                $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
            } else {
                $this->getResponse()->setBody(json_encode($response));
            }
        } catch (Exception $e) {

            $this->clerkLogger->error('Version execute ERROR', ['error' => $e->getMessage()]);

        }
    }
}
