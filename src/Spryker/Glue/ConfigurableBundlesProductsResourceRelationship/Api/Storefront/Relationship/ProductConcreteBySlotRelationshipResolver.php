<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\ConfigurableBundlesProductsResourceRelationship\Api\Storefront\Relationship;

use ApiPlatform\Metadata\GetCollection;
use Generated\Api\Storefront\ConfigurableBundleTemplateSlotsStorefrontResource;
use Generated\Shared\Transfer\ProductConcreteCriteriaFilterTransfer;
use Generated\Shared\Transfer\ProductListTransfer;
use Spryker\ApiPlatform\Relationship\AbstractRelationshipResolver;
use Spryker\Client\Catalog\CatalogClientInterface;
use Spryker\Glue\ProductsRestApi\Api\Storefront\Provider\ConcreteProductsStorefrontProvider;

class ProductConcreteBySlotRelationshipResolver extends AbstractRelationshipResolver
{
    /**
     * @uses \Spryker\Client\ProductListSearch\Plugin\Search\ProductListQueryExpanderPlugin::REQUEST_PARAM_ID_PRODUCT_LIST
     */
    protected const string REQUEST_PARAM_ID_PRODUCT_LIST = ProductListTransfer::ID_PRODUCT_LIST;

    protected const string REQUEST_PARAM_ITEMS_PER_PAGE = 'ipp';

    protected const int REQUEST_PARAM_ITEMS_PER_PAGE_VALUE = 1000;

    /**
     * @uses \Spryker\Client\Catalog\Plugin\Elasticsearch\ResultFormatter\ProductConcreteCatalogSearchResultFormatterPlugin::NAME
     */
    protected const string FORMATTED_RESULT_KEY = 'ProductConcreteCatalogSearchResultFormatterPlugin';

    public function __construct(
        protected CatalogClientInterface $catalogClient,
        protected ConcreteProductsStorefrontProvider $concreteProductsStorefrontProvider,
    ) {
    }

    /**
     * @return array<\Generated\Api\Storefront\ConcreteProductsStorefrontResource>
     */
    protected function resolveRelationship(): array
    {
        if (!$this->hasLocale()) {
            return [];
        }

        $productStockKeepingUnitsByProductListId = [];

        foreach ($this->getParentResources() as $parentResource) {
            if (!$parentResource instanceof ConfigurableBundleTemplateSlotsStorefrontResource) {
                continue;
            }

            if ($parentResource->idProductList === null) {
                continue;
            }

            $idProductList = $parentResource->idProductList;

            if (!isset($productStockKeepingUnitsByProductListId[$idProductList])) {
                $productStockKeepingUnitsByProductListId[$idProductList] = $this->getProductSkusByProductListId($idProductList);
            }
        }

        if ($productStockKeepingUnitsByProductListId === []) {
            return [];
        }

        $allProductIdToStockKeepingUnit = array_merge(...array_values($productStockKeepingUnitsByProductListId));

        return $this->buildConcreteProductResources(array_keys($allProductIdToStockKeepingUnit));
    }

    /**
     * @return array<int, string> Keys are product concrete identifiers, values are stock keeping units
     */
    protected function getProductSkusByProductListId(int $idProductList): array
    {
        $productConcreteCriteriaFilterTransfer = (new ProductConcreteCriteriaFilterTransfer())
            ->setRequestParams([
                static::REQUEST_PARAM_ID_PRODUCT_LIST => $idProductList,
                static::REQUEST_PARAM_ITEMS_PER_PAGE => static::REQUEST_PARAM_ITEMS_PER_PAGE_VALUE,
            ]);

        /** @var array<string, array<\Generated\Shared\Transfer\ProductConcretePageSearchTransfer>> $searchResult */
        $searchResult = $this->catalogClient->searchProductConcretesByFullText($productConcreteCriteriaFilterTransfer);

        $productConcretePageSearchTransfers = $searchResult[static::FORMATTED_RESULT_KEY] ?? [];

        $stockKeepingUnitsByProductId = [];

        foreach ($productConcretePageSearchTransfers as $productConcretePageSearchTransfer) {
            $productId = $productConcretePageSearchTransfer->getFkProduct();

            $stockKeepingUnit = $productConcretePageSearchTransfer->getSku();

            if ($productId === null || $stockKeepingUnit === null) {
                continue;
            }

            $stockKeepingUnitsByProductId[$productId] = $stockKeepingUnit;
        }

        return $stockKeepingUnitsByProductId;
    }

    /**
     * @param array<int> $productIds
     *
     * @return array<\Generated\Api\Storefront\ConcreteProductsStorefrontResource>
     */
    protected function buildConcreteProductResources(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $context = array_merge($this->context, [ConcreteProductsStorefrontProvider::CONTEXT_KEY_CONCRETE_PRODUCT_IDS => $productIds]);

        return (array)$this->concreteProductsStorefrontProvider->provide(new GetCollection(), [], $context);
    }
}
