<?php

namespace Clerk\Clerk\Block;

use Clerk\Clerk\Helper\Context as ContextHelper;
use Clerk\Clerk\Helper\Settings;
use Clerk\Clerk\Model\Config;
use Exception;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\CatalogSearch\Block\Result as BaseResult;
use Magento\CatalogSearch\Helper\Data;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Model\QueryFactory;

class Result extends BaseResult
{

    const TARGET_ID = 'clerk-search-results';
    /**
     * @var array
     */
    protected array $ctx;
    /**
     * @var ContextHelper
     */
    protected ContextHelper $contextHelper;
    /**
     * @var Settings
     */
    protected Settings $config;

    /**
     * @param Context $context
     * @param LayerResolver $layerResolver
     * @param Data $catalogSearchData
     * @param QueryFactory $queryFactory
     * @param Settings $settingsHelper
     * @param ContextHelper $contextHelper
     * @param array $data
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context       $context,
        LayerResolver $layerResolver,
        Data          $catalogSearchData,
        QueryFactory  $queryFactory,
        Settings      $settingsHelper,
        ContextHelper $contextHelper,
        array         $data = []
    )
    {
        $this->config = $settingsHelper;
        $this->contextHelper = $contextHelper;
        $this->ctx = $this->contextHelper->getScopeFromContext();
        parent::__construct(
            $context,
            $layerResolver,
            $catalogSearchData,
            $queryFactory,
            $data
        );
    }

    public function getSuggestions()
    {
        return $this->config->get(Config::XML_PATH_SEARCH_SUGGESTIONS, $this->ctx);
    }

    /**
     * Get attributes for clerk span
     *
     * @return string
     */
    public function getSpanAttributes(): string
    {
        $output = '';

        $spanAttributes = [
            'id' => 'clerk-search',
            'class' => 'clerk',
            'data-template' => '@' . $this->getSearchTemplate(),
            'data-query' => $this->getSearchQuery(),
            'data-target' => '#' . $this->getTargetId(),
            'data-offset' => 0,
            'data-after-render' => '_clerk_after_load_event',
        ];

        if ($this->shouldIncludeCategories()) {
            $spanAttributes['data-search-categories'] = $this->getCategories();
            $spanAttributes['data-search-pages'] = $this->getPages();
            $spanAttributes['data-search-pages-type'] = $this->getPagesType();
        }


        if ($this->config->bool(Config::XML_PATH_FACETED_SEARCH_ENABLED, $this->ctx)) {
            try {
                $spanAttributes['data-facets-target'] = "#clerk-search-filters";
                $spanAttributes['data-facets-design'] = $this->getFacetsDesign();

                if ($titles = $this->config->get(Config::XML_PATH_FACETED_SEARCH_TITLES, $this->ctx)) {
                    $titles = json_decode($titles, true);

                    $titles_sorting = [];
                    foreach ($titles as $k => $v) {
                        if (array_key_exists('sort_order', $v)) {
                            $titles_sorting[$k] = $v['sort_order'];
                        }
                    }

                    asort($titles_sorting);

                    $spanAttributes['data-facets-titles'] = json_encode(array_filter(array_combine(array_keys($titles), array_column($titles, 'label'))));
                    $spanAttributes['data-facets-attributes'] = json_encode(array_keys($titles_sorting));

                    if ($multiselectAttributes = $this->config->get(Config::XML_PATH_FACETED_SEARCH_MULTISELECT_ATTRIBUTES, $this->ctx)) {
                        $spanAttributes['data-facets-multiselect-attributes'] = '["' . str_replace(',', '","', $multiselectAttributes) . '"]';
                    }
                }
            } catch (Exception) {
                $spanAttributes['data-facets-attributes'] = '["price","categories"]';
            }
        }

        foreach ($spanAttributes as $attribute => $value) {
            $output .= sprintf(" %s='%s'", $attribute, $value);
        }

        return trim($output);
    }

    /**
     * Get search template
     *
     * @return mixed
     */
    public function getSearchTemplate(): mixed
    {
        return $this->config->get(Config::XML_PATH_SEARCH_TEMPLATE, $this->ctx);
    }

    /**
     * Get search query
     *
     * @return string
     */
    public function getSearchQuery(): string
    {
        return $this->catalogSearchData->getEscapedQueryText();
    }

    /**
     * Get html id of target
     *
     * @return string
     */
    public function getTargetId(): string
    {
        return self::TARGET_ID;
    }

    /**
     * Determine if we should include categories and pages in search results
     *
     * @return string
     *
     */

    public function shouldIncludeCategories(): string
    {
        return ($this->config->get(Config::XML_PATH_SEARCH_INCLUDE_CATEGORIES,
            $this->ctx)) ? 'true' : 'false';
    }

    /**
     * @return mixed
     */
    public function getCategories(): mixed
    {
        return $this->config->get(Config::XML_PATH_SEARCH_CATEGORIES, $this->ctx);
    }

    /**
     * @return mixed
     */
    public function getPages(): mixed
    {
        return $this->config->get(Config::XML_PATH_SEARCH_PAGES, $this->ctx);
    }

    /**
     * @return mixed
     */
    public function getPagesType(): mixed
    {
        return $this->config->get(Config::XML_PATH_SEARCH_PAGES_TYPE, $this->ctx);
    }

    /**
     * Get facets template
     *
     * @return mixed
     */
    public function getFacetsDesign(): mixed
    {
        return $this->config->get(Config::XML_PATH_FACETED_SEARCH_DESIGN, $this->ctx);
    }

    /**
     * Get no results text
     *
     * @return mixed
     */
    public function getNoResultsText(): mixed
    {
        return $this->config->get(Config::XML_PATH_SEARCH_NO_RESULTS_TEXT, $this->ctx);
    }

}
