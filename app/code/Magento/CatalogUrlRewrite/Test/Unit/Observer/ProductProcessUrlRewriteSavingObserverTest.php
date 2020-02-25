<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogUrlRewrite\Test\Unit\Observer;

use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Api\StoreWebsiteRelationInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Magento\CatalogUrlRewrite\Observer\ProductProcessUrlRewriteSavingObserver;
use Magento\Catalog\Model\Product\Visibility;

/**
 * Class ProductProcessUrlRewriteSavingObserverTest
 *
 * Tests the ProductProcessUrlRewriteSavingObserver to ensure the
 * replace method (refresh existing URLs) and deleteByData (remove
 * old URLs) are called the correct number of times.
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProductProcessUrlRewriteSavingObserverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var UrlPersistInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $urlPersist;

    /**
     * @var Event|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $event;

    /**
     * @var Observer|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $observer;

    /**
     * @var Product|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $product;

    /**
     * @var ProductUrlRewriteGenerator|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var ProductProcessUrlRewriteSavingObserver
     */
    protected $model;

    /**
     * @var StoreManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $storeManager;

    /**
     * @var Website|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $website1;

    /**
     * @var Website|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $website2;

    /**
     * @var StoreWebsiteRelationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $storeWebsiteRelation;

    /**
     * Set up
     * Website_ID = 1 -> Store_ID = 1
     * Website_ID = 2 -> Store_ID = 2 & 5
     */
    protected function setUp()
    {
        $this->urlPersist = $this->createMock(UrlPersistInterface::class);
        $this->product = $this->createPartialMock(
            Product::class,
            [
                'getId',
                'dataHasChangedFor',
                'getVisibility',
                'getIsChangedWebsites',
                'getIsChangedCategories',
                'getStoreId',
                'getWebsiteIds'
            ]
        );
        $this->product->expects($this->any())->method('getId')->will($this->returnValue(3));
        $this->event = $this->createPartialMock(Event::class, ['getProduct']);
        $this->event->expects($this->any())->method('getProduct')->willReturn($this->product);
        $this->observer = $this->createPartialMock(Observer::class, ['getEvent']);
        $this->observer->expects($this->any())->method('getEvent')->willReturn($this->event);
        $this->productUrlRewriteGenerator = $this->createPartialMock(
            ProductUrlRewriteGenerator::class,
            ['generate']
        );
        $this->productUrlRewriteGenerator->expects($this->any())
            ->method('generate')
            ->will($this->returnValue([3 => 'rewrite']));
        $this->objectManager = new ObjectManager($this);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->website1 = $this->createPartialMock(Website::class, ['getWebsiteId']);
        $this->website1->expects($this->any())->method('getWebsiteId')->willReturn(1);
        $this->website2 = $this->createPartialMock(Website::class, ['getWebsiteId']);
        $this->website2->expects($this->any())->method('getWebsiteId')->willReturn(2);
        $this->storeManager->expects($this->any())
            ->method('getWebsites')
            ->will($this->returnValue([$this->website1, $this->website2]));

        $this->storeWebsiteRelation = $this->createPartialMock(
            StoreWebsiteRelationInterface::class,
            ['getStoreByWebsiteId']
        );
        $this->storeWebsiteRelation->expects($this->any())
            ->method('getStoreByWebsiteId')
            ->will($this->returnValueMap([[1, [1]], [2, [2, 5]]]));

        $this->model = $this->objectManager->getObject(
            ProductProcessUrlRewriteSavingObserver::class,
            [
                'productUrlRewriteGenerator' => $this->productUrlRewriteGenerator,
                'urlPersist' => $this->urlPersist,
                'storeManager' => $this->storeManager,
                'storeWebsiteRelation' => $this->storeWebsiteRelation
            ]
        );
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function urlKeyDataProvider()
    {
        return [
            'url changed' => [
                'isChangedUrlKey'       => true,
                'isChangedVisibility'   => false,
                'isChangedWebsites'     => false,
                'isChangedCategories'   => false,
                'visibilityResult'      => Visibility::VISIBILITY_BOTH,
                'expectedReplaceCount'  => 1,
                'expectedDeleteCount'   => 2,
                'productInWebsites'     => [1]

            ],
            'no changes' => [
                'isChangedUrlKey'       => false,
                'isChangedVisibility'   => false,
                'isChangedWebsites'     => false,
                'isChangedCategories'   => false,
                'visibilityResult'      => Visibility::VISIBILITY_BOTH,
                'expectedReplaceCount'  => 0,
                'expectedDeleteCount'   => 0,
                'productInWebsites'     => [1, 2]
            ],
            'visibility changed' => [
                'isChangedUrlKey'       => false,
                'isChangedVisibility'   => true,
                'isChangedWebsites'     => false,
                'isChangedCategories'   => false,
                'visibilityResult'      => Visibility::VISIBILITY_BOTH,
                'expectedReplaceCount'  => 1,
                'expectedDeleteCount'   => 0,
                'productInWebsites'     => [1, 2]
            ],
            'websites changed' => [
                'isChangedUrlKey'       => false,
                'isChangedVisibility'   => false,
                'isChangedWebsites'     => true,
                'isChangedCategories'   => false,
                'visibilityResult'      => Visibility::VISIBILITY_BOTH,
                'expectedReplaceCount'  => 1,
                'expectedDeleteCount'   => 0,
                'productInWebsites'     => [1, 2]
            ],
            'categories changed' => [
                'isChangedUrlKey'       => false,
                'isChangedVisibility'   => false,
                'isChangedWebsites'     => false,
                'isChangedCategories'   => true,
                'visibilityResult'      => Visibility::VISIBILITY_BOTH,
                'expectedReplaceCount'  => 1,
                'expectedDeleteCount'   => 0,
                'productInWebsites'     => [1, 2]
            ],
            'url changed invisible' => [
                'isChangedUrlKey'       => true,
                'isChangedVisibility'   => false,
                'isChangedWebsites'     => false,
                'isChangedCategories'   => false,
                'visibilityResult'      => Visibility::VISIBILITY_NOT_VISIBLE,
                'expectedReplaceCount'  => 0,
                'expectedDeleteCount'   => 0,
                'productInWebsites'     => [1, 2]
            ],
        ];
    }

    /**
     * @param bool $isChangedUrlKey
     * @param bool $isChangedVisibility
     * @param bool $isChangedWebsites
     * @param bool $isChangedCategories
     * @param bool $visibilityResult
     * @param int $expectedReplaceCount
     * @param int $expectedDeleteCount
     * @param int $productInWebsites
     *
     * @dataProvider urlKeyDataProvider
     */
    public function testExecuteUrlKey(
        $isChangedUrlKey,
        $isChangedVisibility,
        $isChangedWebsites,
        $isChangedCategories,
        $visibilityResult,
        $expectedReplaceCount,
        $expectedDeleteCount,
        $productInWebsites
    ) {
        $this->product->expects($this->any())->method('getStoreId')->will(
            $this->returnValue(Store::DEFAULT_STORE_ID)
        );
        $this->product->expects($this->any())->method('getWebsiteIds')->will(
            $this->returnValue($productInWebsites)
        );

        $this->product->expects($this->any())
            ->method('dataHasChangedFor')
            ->will($this->returnValueMap(
                [
                    ['visibility', $isChangedVisibility],
                    ['url_key', $isChangedUrlKey]
                ]
            ));

        $this->product->expects($this->any())
            ->method('getIsChangedWebsites')
            ->will($this->returnValue($isChangedWebsites));

        $this->product->expects($this->any())
            ->method('getIsChangedCategories')
            ->will($this->returnValue($isChangedCategories));

        $this->product->expects($this->any())
            ->method('getVisibility')
            ->will($this->returnValue($visibilityResult));

        $this->urlPersist->expects($this->exactly($expectedReplaceCount))
            ->method('replace')
            ->with([3 => 'rewrite']);

        $this->urlPersist->expects($this->exactly($expectedDeleteCount))
            ->method('deleteByData');

        $this->model->execute($this->observer);
    }
}
