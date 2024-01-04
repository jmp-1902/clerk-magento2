<?php

namespace Clerk\Clerk\Controller\Plugin;

use Clerk\Clerk\Controller\AbstractAction;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{
    protected ClerkLogger $clerkLogger;

    protected ModuleList $moduleList;

    /**
     * Version controller constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ModuleList $moduleList
     * @param ProductMetadataInterface $productMetadata
     * @param RequestApi $requestApi
     */
    public function __construct(
        Context                  $context,
        StoreManagerInterface    $storeManager,
        ScopeConfigInterface     $scopeConfig,
        LoggerInterface          $logger,
        ModuleList               $moduleList,
        ClerkLogger              $clerkLogger,
        ProductMetadataInterface $productMetadata,
        RequestApi               $requestApi,
        Api                      $api
    )
    {
        $this->moduleList = $moduleList;
        $this->clerkLogger = $clerkLogger;
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
            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true);

            $response = $this->moduleList->getAll();

            if ($this->debug) {
                $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
            } else {
                $this->getResponse()->setBody(json_encode($response));
            }
        } catch (Exception $e) {

            $this->clerkLogger->error('Plugin execute ERROR', ['error' => $e->getMessage()]);

        }
    }
}
