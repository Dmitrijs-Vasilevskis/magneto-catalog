<?php
/**
 * @category  Scandiweb
 * @package   Scandiweb_Test
 * @author   Dmitrijs Vasilevskis <info@scandiweb.com>
 * @copyright Copyright (c) 2025 Scandiweb, Inc (https://scandiweb.com)
 */

declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\App\Area;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class AddNewProduct implements DataPatchInterface
{
    /**
     * @var AppState
     */
    protected AppState $appState;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var ProductInterfaceFactory
     */
    protected ProductInterfaceFactory $productFactory;

    /**
     * @var Product
     */
    protected Product $productModel;

    /**
     * @var CategoryLinkManagementInterface
     */
    protected CategoryLinkManagementInterface $categoryLink;

    /**
     * @var CategoryCollectionFactory
     */
    protected CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var SourceItemInterfaceFactory
     */
    protected SourceItemInterfaceFactory $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface
     */
    protected SourceItemsSaveInterface $sourceItemSave;

    /**
     * @var array
     */
    protected array $sourceItems = [];

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductInterfaceFactory $productFactory
     * @param Product $productModel
     * @param AppState $appState
     * @param CategoryLinkManagementInterface $categoryLink
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemSave
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductInterfaceFactory $productFactory,
        Product $productModel,
        AppState $appState,
        CategoryLinkManagementInterface $categoryLink,
        CategoryCollectionFactory $categoryCollectionFactory,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemSave
    ) {
        $this->appState = $appState;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productModel = $productModel;
        $this->categoryLink = $categoryLink;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemSave = $sourceItemSave;
    }

    /**
     * @return void
     */
    public function apply(): void
    {
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, [$this, 'execute']);
    }

    /**
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws StateException
     * @throws ValidationException
     */
    public function execute(): void
    {
        $product = $this->productFactory->create();

        if($product->getIdBySku("sample-product")) {
            return;
        }

        $product
            ->setTypeId(ProductType::TYPE_SIMPLE)
            ->setAttributeSetId($this->productModel->getDefaultAttributeSetId())
            ->setName("Sample Product")
            ->setSku("sample-product")
            ->setPrice(9.99)
            ->setUrlKey("sample-product")
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData([
                "use_config_manage_stock" => 1,
                "is_qty_decimal" => 0,
                "is_in_stock" => 1,
            ]);

        $product = $this->productRepository->save($product);

        if($product->getSku()) {
            $categoryIds = $this->categoryCollectionFactory
                ->create()
                ->addAttributeToFilter("name", ["in" => ["Men", "Women"]])
                ->getAllIds();

            $sourceItem = $this->sourceItemFactory->create();

            $sourceItem->setSourceCode("default");
            $sourceItem->setSku($product->getSku());
            $sourceItem->setQuantity(100);
            $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
            $this->sourceItems[] = $sourceItem;

            $this->sourceItemSave->execute($this->sourceItems);
            $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIds);
        }
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
