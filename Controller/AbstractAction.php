<?php

namespace Clerk\Clerk\Controller;

use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Magento\Framework\Webapi\Rest\Request as RequestApi;
use Magento\Framework\App\ProductMetadataInterface;

abstract class AbstractAction extends Action
{
    /**
     * @var Api
     */
    protected Api $_api;

    /**
     * @var RequestApi
     */
    protected RequestApi $_request_api;

    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerk_logger;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var bool
     */
    protected bool $debug;

    /**
     * @var array
     */
    protected array $fields;

    /**
     * @var array
     */
    protected array $fieldHandlers = [];

    /**
     * @var int
     */
    protected int $limit;

    /**
     * @var int
     */
    protected int $page;

    /**
     * @var int
     */
    protected int $start_date;

    /**
     * @var int
     */
    protected int $end_date;

    /**
     * @var string
     */
    protected string $orderBy;

    /**
     * @var string
     */
    protected string $order;

    /**
     * @var array
     */
    protected array $fieldMap = [];

    /**
     * @var mixed
     */
    protected mixed $collectionFactory;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var string
     */
    protected string $eventPrefix = '';

    /**
     * @var ModuleList
     */
    protected ModuleList $moduleList;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $_storeManager;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $_product_metadata;

    /**
     * @var string|null
     */
    private string|null $privateKey;
    /**
     * @var string|null
     */
    private string|null $publicKey;
    /**
     * @var int
     */
    protected int $scopeid;
    /**
     * @var string
     */
    protected string $scope;

    /**
     * AbstractAction constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ModuleList $moduleList
     * @param ClerkLogger $clerk_logger
     * @param ProductMetadataInterface $product_metadata
     * @param RequestApi $request_api
     * @param Api $api
     */
    public function __construct(
        Context                  $context,
        StoreManagerInterface    $storeManager,
        ScopeConfigInterface     $scopeConfig,
        LoggerInterface          $logger,
        ModuleList               $moduleList,
        ClerkLogger              $clerk_logger,
        ProductMetadataInterface $product_metadata,
        RequestApi               $request_api,
        Api                      $api
    )
    {
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->_storeManager = $storeManager;
        $this->clerk_logger = $clerk_logger;
        $this->_product_metadata = $product_metadata;
        $this->_request_api = $request_api;
        $this->_api = $api;
        parent::__construct($context);
    }


    /**
     * Dispatch request
     *
     * @param RequestInterface $request
     * @return ResponseInterface|null
     * @throws FileSystemException
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function dispatch(RequestInterface $request): ResponseInterface|null
    {

        try {

            $version = $this->_product_metadata->getVersion();
            header('User-Agent: ClerkExtensionBot Magento 2/v' . $version . ' clerk/v' . $this->moduleList->getOne('Clerk_Clerk')['setup_version'] . ' PHP/v' . phpversion());

            $this->publicKey = $this->getRequestBodyParam('key');
            $this->privateKey = $this->getRequestBodyParam('private_key');

            $identity = $this->identifyScope();
            $authorized = $this->authorize($identity);

            if (!$authorized || empty($identity)) {
                $this->_actionFlag->set('', self::FLAG_NO_DISPATCH, true);
                $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

                //Display error
                $this->getResponse()
                    ->setHttpResponseCode(403)
                    ->representJson(
                        json_encode([
                            'error' => [
                                'code' => 403,
                                'message' => __(' Invalid Authentication, please provide valid credentials.'),
                            ]
                        ])
                    );

                $this->clerk_logger->warn('Invalid keys supplied', ['response' => parent::dispatch($request)]);

                return parent::dispatch($request);
            }

            $request->setParams(['scope_id' => $identity['scope_id']]);
            $request->setParams(['scope' => $identity['scope']]);

            //Filter out request arguments
            $this->getArguments($request);
            return parent::dispatch($request);

        } catch (Exception $e) {

            $this->clerk_logger->error('Validating API Keys ERROR', ['error' => $e->getMessage()]);

        }

        return null;
    }

    /**
     * @return array
     * @throws FileSystemException
     */
    private function identifyScope(): array
    {
        $scope_info = [];
        if (!$this->publicKey) {
            return $scope_info;
        }

        $website = $this->verifyWebsiteKeys();
        $store = $this->verifyKeys();
        $default = $this->verifyDefaultKeys();

        if (null !== $website) {
            $scope_info = [
                'scope_id' => $website,
                'scope' => 'website'
            ];
        }
        if (null !== $store) {
            $scope_info = [
                'scope_id' => $store,
                'scope' => 'store'
            ];
        }
        if (null !== $default && $this->_storeManager->isSingleStoreMode()) {
            $scope_info = [
                'scope_id' => $default,
                'scope' => 'default'
            ];
        }
        return $scope_info;
    }

    /**
     * Verify public & private key
     *
     * @return int|null
     * @throws FileSystemException
     */
    private function verifyDefaultKeys(): ?int
    {

        try {

            $scopeID = $this->_storeManager->getDefaultStoreView()->getId();
            if ($this->timingSafeEquals($this->getPublicDefaultKey($scopeID), $this->publicKey)) {
                return $scopeID;
            }

        } catch (Exception $e) {

            $this->clerk_logger->error('verifyKeys ERROR', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Verify public & private key
     *
     * @return int|null
     * @throws FileSystemException
     */
    private function verifyKeys(): ?int
    {

        try {

            $storeids = $this->getStores();
            foreach ($storeids as $scopeID) {
                if ($this->timingSafeEquals($this->getPublicKey($scopeID), $this->publicKey)) {
                    return $scopeID;
                }
            }

        } catch (Exception $e) {

            $this->clerk_logger->error('verifyKeys ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * Verify public & private key
     *
     * @return int|null
     * @throws FileSystemException
     */
    private function verifyWebsiteKeys(): ?int
    {

        try {

            $websiteids = $this->getWebsites();
            foreach ($websiteids as $scopeID) {
                if ($this->timingSafeEquals($this->getPublicWebsiteKey($scopeID), $this->publicKey)) {
                    return $scopeID;
                }
            }

        } catch (Exception $e) {

            $this->clerk_logger->error('verifyKeys ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }


    /**
     * Get public store key
     *
     * @param $scopeID
     * @return string|null
     * @throws FileSystemException
     */
    private function getPublicDefaultKey($scopeID): ?string
    {
        try {

            return $this->scopeConfig->getValue(
                Config::XML_PATH_PUBLIC_KEY,
                ScopeInterface::SCOPE_STORE,
                $scopeID
            );

        } catch (Exception $e) {

            $this->clerk_logger->error('getPublicKey ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * Get private store key
     *
     * @param string $scope
     * @param int $scope_id
     * @return string|null
     * @throws FileSystemException
     */
    private function getPrivateKey(string $scope, int $scope_id): ?string
    {
        try {

            return $this->scopeConfig->getValue(
                Config::XML_PATH_PRIVATE_KEY,
                $scope,
                $scope_id
            );

        } catch (Exception $e) {

            $this->clerk_logger->error('getPrivateKey ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * Get public store key
     *
     * @param $scopeID
     * @return string|null
     * @throws FileSystemException
     */
    private function getPublicKey($scopeID): ?string
    {
        try {

            return $this->scopeConfig->getValue(
                Config::XML_PATH_PUBLIC_KEY,
                ScopeInterface::SCOPE_STORES,
                $scopeID
            );

        } catch (Exception $e) {

            $this->clerk_logger->error('getPublicKey ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * Get Token from Request Header
     * @return string
     */
    private function getHeaderToken(): string
    {
        try {

            $token = '';
            $auth_header = $this->_request_api->getHeader('X-Clerk-Authorization');

            if (null == $auth_header && !is_string($auth_header)) {
                return "";
            }

            $auth_header_array = explode(' ', $auth_header);
            if (count($auth_header_array) !== 2 || $auth_header_array[0] !== 'Bearer') {
                return "";
            }

            $token = $auth_header_array[1];

        } catch (Exception $e) {

            $this->logger->error('getHeaderToken ERROR', ['error' => $e->getMessage()]);

        }
        return $token;
    }

    /**
     * Get public website key
     *
     * @param $scopeID
     * @return string|null
     * @throws FileSystemException
     */
    private function getPublicWebsiteKey($scopeID): string|null
    {
        try {

            return $this->scopeConfig->getValue(
                Config::XML_PATH_PUBLIC_KEY,
                ScopeInterface::SCOPE_WEBSITES,
                $scopeID
            );

        } catch (Exception $e) {
            $this->clerk_logger->error('getPublicKey ERROR', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Timing safe key comparison
     *
     * @param string $safe
     * @param string $user
     * @return boolean
     */
    private function timingSafeEquals(string $safe, string $user): bool
    {
        $safeLen = strlen($safe);
        $userLen = strlen($user);

        if ($userLen != $safeLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($safe[$i]) ^ ord($user[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return $result === 0;
    }

    /**
     * Parse request arguments
     * @throws FileSystemException
     */
    protected function getArguments(RequestInterface $request): void
    {
        try {

            $this->debug = (bool)$request->getParam('debug', false);
            $startDate = strtotime('today - 200 years');
            $startDateParam = $request->getParam('start_date');
            if (!empty($startDateParam)) {
                if (is_int($startDateParam)) {
                    $startDate = $startDateParam;
                } else {
                    $startDate = strtotime($startDateParam);
                }
            }
            $endDate = strtotime('today + 1 day');
            $endDateParam = $request->getParam('end_date');
            if (!empty($endDateParam)) {
                if (is_int($endDateParam)) {
                    $endDate = $endDateParam;
                } else {
                    $endDate = strtotime($endDateParam);
                }
            }
            $this->start_date = date('Y-m-d', $startDate);
            $this->end_date = date('Y-m-d', $endDate);
            $this->limit = (int)$request->getParam('limit', 0);
            $this->page = (int)$request->getParam('page', 0);
            $this->orderBy = $request->getParam('orderby', 'entity_id');
            $this->order = $request->getParam('order', 'asc');
            $this->limit = (int)$request->getParam('limit', 0);
            $this->page = (int)$request->getParam('page', 0);
            $this->orderBy = $request->getParam('orderby', 'entity_id');
            $this->scope = $request->getParam('scope');
            $this->scopeid = $request->getParam('scope_id');

            $this->order = $request->getParam('order') === 'desc' ? Collection::SORT_ORDER_DESC : Collection::SORT_ORDER_ASC;

            /**
             * Explode fields on , and filter out "empty" entries
             */
            $fields = $request->getParam('fields');
            $this->fields = $fields ? array_filter(explode(',', $fields), 'strlen') : $this->getDefaultFields();
            $this->fields = array_merge(['entity_id'], $this->fields);

            foreach ($this->fields as $key => $field) {

                $this->fields[$key] = str_replace(' ', '', $field);

            }

        } catch (Exception $e) {

            $this->clerk_logger->error('getArguments ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * Get default fields
     *
     * @return array
     */
    protected function getDefaultFields(): array
    {
        return [];
    }

    /**
     * Execute request
     * @throws FileSystemException
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function execute(): void
    {
        try {

            $collection = $this->prepareCollection()->addFieldToFilter('store_id', $this->scopeid);

            $this->_eventManager->dispatch($this->eventPrefix . '_get_collection_after', [
                'controller' => $this,
                'collection' => $collection
            ]);

            $response = [];

            if ($this->page <= $collection->getLastPageNumber()) {
                //Build response
                foreach ($collection as $resourceItem) {
                    $item = [];

                    foreach ($this->fields as $field) {
                        if (isset($resourceItem[$field])) {
                            $item[$this->getFieldName($field)] = $this->getAttributeValue($resourceItem, $field);
                        }

                        if (isset($this->fieldHandlers[$field])) {
                            if (!is_null($this->fieldHandlers[$field]($resourceItem))) {
                                $item[$this->getFieldName($field)] = $this->fieldHandlers[$field]($resourceItem);
                            }
                        }
                    }

                    $response[] = $item;

                }
            }

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true);

            if ($this->debug) {
                $this->clerk_logger->log('Fetched page ' . $this->page . ' with ' . count($response) . ' Orders', ['response' => $response]);
                $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
            } else {
                $this->clerk_logger->log('Fetched page ' . $this->page . ' with ' . count($response) . ' Orders', ['response' => $response]);
                $this->getResponse()->setBody(json_encode($response));
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
            $this->clerk_logger->error('AbstractAction execute ERROR', ['error' => $e->getMessage()]);
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

            $collection->addFieldToSelect('*');

            if ($this->start_date) {

                $collection->setPageSize($this->limit)
                    ->setCurPage($this->page)
                    ->addAttributeToFilter('created_at', ['from' => $this->start_date, 'to' => $this->end_date])
                    ->addOrder($this->orderBy, $this->order);
            } else {

                $collection->setPageSize($this->limit)
                    ->setCurPage($this->page)
                    ->addOrder($this->orderBy, $this->order);

            }

            return $collection;

        } catch (Exception $e) {

            $this->clerk_logger->error('prepareCollection ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }

    /**
     * Get mapped field name
     *
     * @param $field
     * @return mixed
     * @throws FileSystemException
     */
    protected function getFieldName($field): mixed
    {

        try {

            if (isset($this->fieldMap[$field])) {
                return $this->fieldMap[$field];
            }

        } catch (Exception $e) {

            $this->clerk_logger->error('Getting Field Name ERROR', ['error' => $e->getMessage()]);

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
     * Add fieldhandler
     *
     * @param $field
     * @param callable $handler
     */
    public function addFieldHandler($field, callable $handler): void
    {
        $this->fieldHandlers[$field] = $handler;
    }

    public function getWebsites(): array
    {
        $websiteIds = [];
        foreach ($this->_storeManager->getWebsites() as $website) {
            $websiteId = $website["website_id"];
            $websiteIds[] = $websiteId;
        }

        return $websiteIds;
    }

    public function getStores(): array
    {
        return array_keys($this->_storeManager->getStores(true));
    }

    /**
     * @param $key
     * @return mixed|null
     * @throws FileSystemException
     */
    public function getRequestBodyParam($key): mixed
    {
        try {

            $body = $this->_request_api->getBodyParams();

            if ($body && is_array($body) && array_key_exists($key, $body)) {
                return $body[$key];
            }

        } catch (Exception $e) {

            $this->clerk_logger->error('Getting Request Body ERROR', ['error' => $e->getMessage()]);

        }

        return null;
    }

    /**
     * Validate token with Clerk
     *
     * @return bool
     * @throws Exception
     */
    public function validateJwt(): bool
    {

        $token_string = $this->getHeaderToken();

        if (!$token_string) {
            return false;
        }

        $rsp_array = $this->_api->verifyToken($token_string, $this->publicKey);

        if (!is_array($rsp_array)) {
            return false;
        }

        if (!array_key_exists('status', $rsp_array)) {
            return false;
        }

        if ($rsp_array['status'] !== 'ok') {
            return false;
        }

        return true;

    }

    /**
     * @throws Exception
     */
    private function authorize(array $scope_info): bool
    {
        if (empty($scope_info)) {
            return false;
        }
        if (!array_key_exists('scope_id', $scope_info) || !array_key_exists('scope', $scope_info)) {
            return false;
        }

        $legacy_auth = $this->scopeConfig->getValue(
            Config::XML_PATH_USE_LEGACY_AUTH,
            $scope_info['scope'],
            $scope_info['scope_id']
        );

        if (!$legacy_auth) {
            // check Header Token
            return $this->validateJwt();
        }

        if (!$this->privateKey) {
            return false;
        }
        $private_key = $this->getPrivateKey($scope_info['scope'], $scope_info['scope_id']);
        return $this->timingSafeEquals($private_key, $this->privateKey);

    }
}
