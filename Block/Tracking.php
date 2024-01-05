<?php
/**
 * Tracking Block for Clerk.io
 */

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Magento\Backend\Block\Widget\Context;
use Magento\Directory\Model\Currency;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class Tracking extends Template
{

    protected FormKey $formKey;

    protected Currency $currency;

    protected $storeManager;
    protected Settings $config;
    protected ContextHelper $contextHelper;
    protected array $ctx;

    /**
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context               $context,
        FormKey               $formKey,
        Currency              $currency,
        StoreManagerInterface $storeManager,
        Settings              $settingsHelper,
        ContextHelper         $contextHelper,
    )
    {
        parent::__construct($context);
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
        $this->formKey = $formKey;
        $this->currency = $currency;
        $this->storeManager = $storeManager;

    }

    /**
     * Get public key
     *
     * @return mixed
     */
    public function getPublicKey(): mixed
    {
        return $this->config->get(Config::XML_PATH_PUBLIC_KEY, $this->ctx);
    }

    /**
     * @return mixed
     */
    public function getLanguage(): mixed
    {
        return $this->config->get(Config::XML_PATH_LANGUAGE, $this->ctx);
    }

    /**
     * Get collect emails
     *
     * @return string
     */
    public function getCollectionEmails(): string
    {
        return ($this->config->bool(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_COLLECT_EMAILS, $this->ctx) ? 'true' : 'false');
    }

    /**
     * Get collect carts
     *
     * @return string
     */
    public function getCollectionBaskets(): string
    {
        return ($this->config->get(Config::XML_PATH_PRODUCT_SYNCHRONIZATION_COLLECT_BASKETS, $this->ctx) ? 'true' : 'false');
    }

    /**
     * @throws LocalizedException
     */
    public function getFormKey(): string
    {

        return $this->formKey->getFormKey();
    }

    /**
     * Get store base currency code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    /**
     * Get current store currency code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCurrentCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    /**
     * Get default store currency code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getDefaultCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getDefaultCurrencyCode();
    }

    /**
     * Get allowed store currency codes
     *
     * If base currency is not allowed in current website config scope,
     * then it can be disabled with $skipBaseNotAllowed
     *
     * @param bool $skipBaseNotAllowed
     * @return array
     * @throws NoSuchEntityException
     */
    public function getAvailableCurrencyCodes(bool $skipBaseNotAllowed = false): array
    {
        return $this->storeManager->getStore()->getAvailableCurrencyCodes($skipBaseNotAllowed);
    }

    /**
     * Get current currency rate
     *
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCurrentCurrencyRate(): float
    {
        return $this->storeManager->getStore()->getCurrentCurrencyRate();
    }

    /**
     * Get currency symbol for current locale and currency code
     *
     * @return string
     */
    public function getCurrentCurrencySymbol(): string
    {
        return $this->currency->getCurrencySymbol();
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getAllCurrencyRates(): array
    {
        $currency_codes = $this->getAllowedCurrencies();
        $currency_rates_array = array();
        foreach ($currency_codes as $key => $code) {
            $currency_rates_array[$code] = $this->getCurrencyRateFromIso($code);
        }
        return $currency_rates_array;
    }

    /**
     * Get array of installed currencies for the scope
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getAllowedCurrencies(): array
    {
        return $this->storeManager->getStore()->getAllowedCurrencies();
    }

    /**
     * Get currency rate for current locale from currency code
     *
     * @param string|null $currencyIso Currency ISO code
     *
     * @return float
     * @throws NoSuchEntityException
     */
    public function getCurrencyRateFromIso(string $currencyIso = null): float
    {
        return !$currencyIso ? 1.0 : $this->storeManager->getStore()->getBaseCurrency()->getRate($currencyIso);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getClerkJSLink(): string
    {
        $storeName = $this->getStoreNameSlug() ?? 'clerk';
        return '://custom.clerk.io/' . $storeName . '.js';
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreNameSlug(): string
    {
        $storeName = $this->storeManager->getStore()->getName();
        if (!is_string($storeName)) {
            return '';
        }
        return preg_replace('/[^a-z]/', '', strtolower($storeName));
    }
}
