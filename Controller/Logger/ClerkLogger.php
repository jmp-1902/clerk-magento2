<?php

namespace Clerk\Clerk\Controller\Logger;

use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config;
use DateTime;
use Exception;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class ClerkLogger
{

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;
    /**
     * @var
     */
    protected ConfigInterface $configWriter;
    /**
     * @var DirectoryList
     */
    protected DirectoryList $directory;
    protected ModuleList $moduleList;
    /**
     * @var string
     */
    protected string $version;
    /**
     * @var Api
     */
    protected Api $api;
    /**
     * @var string
     */
    private string $platform;
    /**
     * @var mixed
     */
    private mixed $publicKey;
    /**
     * @var DateTime
     */
    private DateTime $date;
    /**
     * @var bool
     */
    private bool $enabled;
    /**
     * @var int
     */
    private int $timeStamp;
    /**
     * @var mixed
     */
    private mixed $logLevel;
    /**
     * @var mixed
     */
    private mixed $logTo;

    /**
     * ClerkLogger constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param DirectoryList $directory
     * @param TimezoneInterface $date
     * @param ConfigInterface $configWriter
     * @param ModuleList $moduleList
     * @param ProductMetadataInterface $productMetadata
     * @param Api $api
     */
    function __construct(
        ScopeConfigInterface     $scopeConfig,
        DirectoryList            $directory,
        TimezoneInterface        $date,
        ConfigInterface          $configWriter,
        ModuleList               $moduleList,
        ProductMetadataInterface $productMetadata,
        Api                      $api
    )
    {

        $this->configWriter = $configWriter;
        $this->directory = $directory;
        $this->scopeConfig = $scopeConfig;
        $this->platform = 'Magento 2';
        $this->publicKey = $this->scopeConfig->getValue(Config::XML_PATH_PUBLIC_KEY, ScopeInterface::SCOPE_STORE);
        $this->date = $date->date();
        $this->timeStamp = $date->scopeTimeStamp();
        $this->logLevel = $this->scopeConfig->getValue(Config::XML_PATH_LOG_LEVEL, ScopeInterface::SCOPE_STORE);
        $this->logTo = $this->scopeConfig->getValue(Config::XML_PATH_LOG_TO, ScopeInterface::SCOPE_STORE);
        $this->enabled = (bool)$this->scopeConfig->getValue(Config::XML_PATH_LOG_ENABLED, ScopeInterface::SCOPE_STORE);
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->version = $this->productMetadata->getVersion();
        $this->api = $api;
        $this->initSettings();
    }

    public function initSettings(): void
    {
        if ($this->enabled) {

            $realtimeupdates_initiated = $this->scopeConfig->getValue('clerk/log/realtimeupdatesfirst');

            $collectemails_initiated = $this->scopeConfig->getValue('clerk/log/collectemailsfirst');

            $onlysynchronizesaleableproducts_initiated = $this->scopeConfig->getValue('clerk/log/onlysynchronizesaleableproductsfirst');

            $disableordersynchronization_initiated = $this->scopeConfig->getValue('clerk/log/disableordersynchronizationfirst');

            $facetedsearchsettings_initiated = $this->scopeConfig->getValue('clerk/log/facetedsearchsettingsfirst');

            $categorysettings_initiated = $this->scopeConfig->getValue('clerk/log/categorysettingsfirst');

            $productsettings_initiated = $this->scopeConfig->getValue('clerk/log/productsettingsfirst');

            $cartsettings_initiated = $this->scopeConfig->getValue('clerk/log/cartsettingsfirst');

            $livesearch_initiated = $this->scopeConfig->getValue('clerk/log/livesearchfirst');

            $search_initiated = $this->scopeConfig->getValue('clerk/log/searchfirst');

            $powerstep_initiated = $this->scopeConfig->getValue('clerk/log/powerstepfirst');

            //Realtime Updates Initialize
            if ($this->scopeConfig->getValue('clerk/product_synchronization/use_realtime_updates') == '1' && !$realtimeupdates_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/realtimeupdatesfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Realtime Updates initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/product_synchronization/use_realtime_updates') == '1' && $realtimeupdates_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/realtimeupdatesfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Realtime Updates uninitiated', ['' => '']);

            }

            //Collect Emails Initialize
            if ($this->scopeConfig->getValue('clerk/product_synchronization/collect_emails') == '1' && !$collectemails_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/collectemailsfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Collect Emails initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/product_synchronization/collect_emails') == '1' && $collectemails_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/collectemailsfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Collect Emails uninitiated', ['' => '']);

            }

            //Only Sync Saleable Products Initialize
            if ($this->scopeConfig->getValue('clerk/product_synchronization/saleable_only') == '1' && !$onlysynchronizesaleableproducts_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/onlysynchronizesaleableproductsfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Only Sync Saleable Products initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/product_synchronization/saleable_only') == '1' && $onlysynchronizesaleableproducts_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/onlysynchronizesaleableproductsfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Only Sync Saleable Products uninitiated', ['' => '']);

            }

            //Disable Order Synchronization Initialize
            if ($this->scopeConfig->getValue('clerk/product_synchronization/disable_order_synchronization') == '1' && !$disableordersynchronization_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/disableordersynchronizationfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Disable Order Synchronization initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/product_synchronization/disable_order_synchronization') == '1' && $disableordersynchronization_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/disableordersynchronizationfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Disable Order Synchronization uninitiated', ['' => '']);

            }

            //Faceted Search Settings Initialize
            if ($this->scopeConfig->getValue('clerk/faceted_search/enabled') == '1' && !$facetedsearchsettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/facetedsearchsettingsfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Faceted Search Settings initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/faceted_search/enabled') == '1' && $facetedsearchsettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/facetedsearchsettingsfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Faceted Search Settings uninitiated', ['' => '']);

            }

            //Category Settings Initialize
            if ($this->scopeConfig->getValue('clerk/category/enabled') == '1' && !$categorysettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/categorysettingsfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Category Settings initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/category/enabled') == '1' && $categorysettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/categorysettingsfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Category Settings uninitiated', ['' => '']);

            }

            //Product Settings Initialize
            if ($this->scopeConfig->getValue('clerk/product/enabled') == '1' && !$productsettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/productsettingsfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Product Settings initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/product/enabled') == '1' && $productsettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/productsettingsfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Product Settings uninitiated', ['' => '']);

            }

            //Cart Settings Initialize
            if ($this->scopeConfig->getValue('clerk/cart/enabled') == '1' && !$cartsettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/cartsettingsfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Cart Settings initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/cart/enabled') == '1' && $cartsettings_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/cartsettingsfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Cart Settings uninitiated', ['' => '']);

            }

            //Live Search Initialize
            if ($this->scopeConfig->getValue('clerk/livesearch/enabled') == '1' && !$livesearch_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/livesearchfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Live Search initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/livesearch/enabled') == '1' && $livesearch_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/livesearchfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Live Search uninitiated', ['' => '']);

            }

            //Search Initialize
            if ($this->scopeConfig->getValue('clerk/search/enabled') == '1' && !$search_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/searchfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Search initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/search/enabled') == '1' && $search_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/searchfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Search uninitiated', ['' => '']);

            }

            //Powerstep Initialize
            if ($this->scopeConfig->getValue('clerk/powerstep/enabled') == '1' && !$powerstep_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/powerstepfirst', '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Powerstep initiated', ['' => '']);

            }

            if (!$this->scopeConfig->getValue('clerk/powerstep/enabled') == '1' && $powerstep_initiated == 1) {

                $this->configWriter->saveConfig('clerk/log/powerstepfirst', '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
                $this->log('Powerstep uninitiated', ['' => '']);

            }
        }
    }

    /**
     * @param $message
     * @param $metaData
     * @throws Exception
     */
    public function log($message, $metaData): void
    {
        header('User-Agent: ClerkExtensionBot Magento 2/v' . $this->version . ' clerk/v' . $this->moduleList->getOne('Clerk_Clerk')['setup_version'] . ' PHP/v' . phpversion());
        $metaData = $this->getMetadata($metaData);

        $errorType = 'log';
        if ($this->enabled) {
            if ($this->logLevel === 'all') {
                if ($this->logTo == 'collect') {
                    $this->logToRemote($errorType, $message, $metaData);
                } elseif ($this->logTo == 'file') {
                    $this->logToFile($message, $metaData);
                }
            }
        }
    }

    /**
     * @param $metaData
     * @return mixed
     */
    public function getMetadata($metaData): array
    {
        if (isset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']) && $_SERVER['HTTPS'] === 'on') {
            $metaData['uri'] = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            $metaData['uri'] = sprintf("http://%s%s", $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
        }

        if ($_GET) {
            $metaData['params'] = $_GET;
        } elseif ($_POST) {
            $metaData['params'] = $_POST;
        }
        return $metaData;
    }

    public function logToRemote($errorType, $message, $metaData): void
    {
        try {
            $data_string = json_encode([
                'key' => $this->publicKey,
                'source' => $this->platform,
                'time' => $this->timeStamp,
                'type' => $errorType,
                'message' => $message,
                'metadata' => $metaData
            ]);
            $response = json_decode($this->api->post('log/debug', $data_string));

            if ($response->status == 'error') {
                $this->logToFile($message, $metaData);
            }
        } catch (FileSystemException|Exception $e) {
            return;
        }
        return;
    }

    /**
     * @throws FileSystemException
     */
    public function logToFile($Message, $Metadata): void
    {

        $log = $this->date->format('Y-m-d H:i:s') . ' MESSAGE: ' . $Message . ' METADATA: ' . json_encode($Metadata) . PHP_EOL .
            '-------------------------' . PHP_EOL;
        $path = $this->directory->getPath('log') . '/clerk_log.log';

        fopen($path, "a+");
        file_put_contents($path, $log, FILE_APPEND);
    }

    /**
     * @param $message
     * @param $metaData
     * @throws FileSystemException
     */
    public function error($message, $metaData): void
    {
        header('User-Agent: ClerkExtensionBot Magento 2/v' . $this->version . ' clerk/v' . $this->moduleList->getOne('Clerk_Clerk')['setup_version'] . ' PHP/v' . phpversion());
        $metaData = $this->getMetadata($metaData);

        $errorType = 'error';
        if ($this->enabled) {
            if ($this->logTo == 'collect') {
                $this->logToRemote($errorType, $message, $metaData);
            } elseif ($this->logTo == 'file') {
                $this->logToFile($message, $metaData);
            }
        }
    }

    /**
     * @param $message
     * @param $metaData
     * @throws FileSystemException
     */
    public function warn($message, $metaData): void
    {
        header('User-Agent: ClerkExtensionBot Magento 2/v' . $this->version . ' clerk/v' . $this->moduleList->getOne('Clerk_Clerk')['setup_version'] . ' PHP/v' . phpversion());
        $metaData = $this->getMetadata($metaData);

        $errorType = 'warn';
        if ($this->enabled) {
            if ($this->logLevel !== 'error') {
                if ($this->logTo == 'collect') {
                    $this->logToRemote($errorType, $message, $metaData);
                } elseif ($this->logTo == 'file') {
                    $this->logToFile($message, $metaData);
                }
            }
        }
    }
}
