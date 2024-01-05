<?php

namespace Clerk\Clerk\Controller\Rotatekey;

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
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractAction
{
    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

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
     * @param EncryptorInterface $encryptor
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
        Api                      $api,
        EncryptorInterface       $encryptor,
    )
    {
        $this->clerkLogger = $clerkLogger;
        $this->configWriter = $configWriter;
        $this->cacheType = $cacheType;
        $this->encryptor = $encryptor;
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

            $response = [
                'status' => 'error',
                'message' => 'Failed to update Private API key',
                'scope' => $scope,
                'scopeId' => $scopeId
            ];

            if ($post) {

                $body_array = json_decode($post, true) ? json_decode($post, true) : array();

                if (array_key_exists('clerk_private_key', $body_array)) {
                    $encryptedValue = $this->encryptor->encrypt($body_array['clerk_private_key']);

                    $this->configWriter->save(Config::XML_PATH_PRIVATE_KEY, $encryptedValue, $scope, $scopeId);
                    $this->cacheType->cleanType('config');

                    $response = [
                        'status' => 'ok',
                        'message' => 'Updated Private API key',
                        'scope' => $scope,
                        'scopeId' => $scopeId
                    ];

                }

            }


            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true);

            if ($this->debug) {
                $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
            } else {
                $this->getResponse()->setBody(json_encode($response));
            }


        } catch (Exception $e) {

            $this->clerkLogger->error('Rotatekey execute ERROR', ['error' => $e->getMessage()]);

        }
    }
}