<?php

namespace Clerk\Clerk\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Settings
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;
    /**
     * @var WriterInterface
     */
    protected WriterInterface $configWriter;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface      $configWriter,
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;

    }

    /**
     * @param string $key
     * @param array $ctx
     * @return mixed
     */
    public function get(string $key, array $ctx): mixed
    {
        return $this->scopeConfig->getValue($key, $ctx['scope'], $ctx['scope_id']);
    }

    /**
     * @param mixed $value
     * @param string $key
     * @param array $ctx
     * @return void
     */
    public function set(mixed $value, string $key, array $ctx): void
    {
        $this->configWriter->save($key, $value, $ctx['scope'], $ctx['scope_id']);
    }

    /**
     * @param string $key
     * @param array $ctx
     * @return bool
     */
    public function bool(string $key, array $ctx): bool
    {
        return $this->scopeConfig->isSetFlag($key, $ctx['scope'], $ctx['scope_id']);
    }

}
