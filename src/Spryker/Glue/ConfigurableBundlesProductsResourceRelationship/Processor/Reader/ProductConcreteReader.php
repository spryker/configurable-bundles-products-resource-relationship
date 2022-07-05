<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\ConfigurableBundlesProductsResourceRelationship\Processor\Reader;

use Generated\Shared\Transfer\ProductConcreteCriteriaFilterTransfer;
use Generated\Shared\Transfer\ProductListTransfer;
use Spryker\Glue\ConfigurableBundlesProductsResourceRelationship\Dependency\RestApiResource\ConfigurableBundlesProductsResourceRelationshipToCatalogClientInterface;

class ProductConcreteReader implements ProductConcreteReaderInterface
{
    /**
     * @uses \Spryker\Client\ProductListSearch\Plugin\Search\ProductListQueryExpanderPlugin::REQUEST_PARAM_ID_PRODUCT_LIST
     */
    protected const REQUEST_PARAM_ID_PRODUCT_LIST = ProductListTransfer::ID_PRODUCT_LIST;

    /**
     * @uses \Spryker\Client\Catalog\CatalogConfig::PAGINATION_ITEMS_PER_PAGE_PARAMETER_NAME
     *
     * @var string
     */
    protected const REQUEST_PARAM_ITEMS_PER_PAGE = 'ipp';

    /**
     * @see \SprykerShop\Yves\CatalogPage\CatalogPageConfig::CATALOG_PAGE_LIMIT
     *
     * @var int
     */
    protected const REQUEST_PARAM_ITEMS_PER_PAGE_VALUE = 1000;

    /**
     * @uses \Spryker\Client\Catalog\Plugin\Elasticsearch\ResultFormatter\ProductConcreteCatalogSearchResultFormatterPlugin::NAME
     *
     * @var string
     */
    protected const FORMATTED_RESULT_KEY = 'ProductConcreteCatalogSearchResultFormatterPlugin';

    /**
     * @var \Spryker\Glue\ConfigurableBundlesProductsResourceRelationship\Dependency\RestApiResource\ConfigurableBundlesProductsResourceRelationshipToCatalogClientInterface
     */
    protected $catalogClient;

    /**
     * @param \Spryker\Glue\ConfigurableBundlesProductsResourceRelationship\Dependency\RestApiResource\ConfigurableBundlesProductsResourceRelationshipToCatalogClientInterface $catalogClient
     */
    public function __construct(ConfigurableBundlesProductsResourceRelationshipToCatalogClientInterface $catalogClient)
    {
        $this->catalogClient = $catalogClient;
    }

    /**
     * @param int $idProductList
     *
     * @return array<int, string>
     */
    public function getProductConcreteSkusIndexedByIdProductConcrete(int $idProductList): array
    {
        $productConcreteCriteriaFilterTransfer = (new ProductConcreteCriteriaFilterTransfer())
            ->setRequestParams([
                static::REQUEST_PARAM_ID_PRODUCT_LIST => $idProductList,
                static::REQUEST_PARAM_ITEMS_PER_PAGE => static::REQUEST_PARAM_ITEMS_PER_PAGE_VALUE,
            ]);

        /** @var array<string, array<\Generated\Shared\Transfer\ProductConcretePageSearchTransfer>> $searchResult */
        $searchResult = $this->catalogClient->searchProductConcretesByFullText($productConcreteCriteriaFilterTransfer);
        $productConcretePageSearchTransfers = $searchResult[static::FORMATTED_RESULT_KEY] ?? [];

        if (!$productConcretePageSearchTransfers) {
            return [];
        }

        $productConcreteIdsToProductConcreteSkusMap = [];
        foreach ($productConcretePageSearchTransfers as $productConcretePageSearchTransfer) {
            if (!$productConcretePageSearchTransfer->getFkProduct() || !$productConcretePageSearchTransfer->getSku()) {
                continue;
            }
            $productConcreteIdsToProductConcreteSkusMap[$productConcretePageSearchTransfer->getFkProductOrFail()] = $productConcretePageSearchTransfer->getSkuOrFail();
        }

        return $productConcreteIdsToProductConcreteSkusMap;
    }
}
