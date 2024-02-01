<?php
namespace Tabby\Checkout\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Catalog\Helper\Category;

/**
 * Source model for category selection
 */
class Categorylist implements ArrayInterface
{
    /**
     * @var \Magento\Catalog\Helper\Category
     */
    protected $categoryHelper;

    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var array
     */
    protected $categoryList = [];

    /**
     * @param \Magento\Catalog\Helper\Category $catalogCategory
     * @param \Magento\Catalog\Model\CategoryRepository $categoryRepository
     */
    public function __construct(
        \Magento\Catalog\Helper\Category $catalogCategory,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository
    ) {
        $this->categoryHelper = $catalogCategory;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Return store categories
     *
     * @param bool $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     * @return array
     */
    public function getStoreCategories($sorted = false, $asCollection = false, $toLoad = true)
    {
        return $this->categoryHelper->getStoreCategories($sorted, $asCollection, $toLoad);
    }

    /**
     * Option getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $arr = $this->toArray();
        $ret = [];

        foreach ($arr as $key => $value) {
            $ret[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        return $ret;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $categories = $this->getStoreCategories(true, false, true);
        return $this->renderCategories($categories);
    }

    /**
     * Process categories tree
     *
     * @param array $categories
     * @return array
     */
    public function renderCategories($categories)
    {
        foreach ($categories as $category) {
            $this->categoryList[$category->getEntityId()] = __($category->getName());   // Main categories
            $this->renderSubCat($category);
        }

        return $this->categoryList;
    }

    /**
     * Process sub-categories tree
     *
     * @param \Magento\Catalog\Model\Category $cat
     */
    public function renderSubCat($cat)
    {
        $categoryObj = $this->categoryRepository->get($cat->getId());

        $level = $categoryObj->getLevel();
        $arrow = str_repeat("---", $level-1);
        $subcategories = $categoryObj->getChildrenCategories();

        foreach ($subcategories as $subcategory) {
            $this->categoryList[$subcategory->getEntityId()] = __($arrow. ' ' . $subcategory->getName());

            if ($subcategory->hasChildren()) {

                $this->renderSubCat($subcategory);

            }
        }
    }
}
