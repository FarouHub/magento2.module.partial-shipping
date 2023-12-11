<?php

namespace Lightweight\PartialShipping\Model\Config\Source;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var array
     */
    private $options = [];

    /**
     * ProductAttributes constructor.
     *
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToSelect(
            [
                EavAttributeInterface::ATTRIBUTE_CODE,
                EavAttributeInterface::FRONTEND_LABEL,
            ]
        );

        /** @var Attribute $attribute */
        foreach ($collection as $attribute) {
            array_push(
                $this->options, [
                    'label' => $attribute->getData(EavAttributeInterface::FRONTEND_LABEL),
                    'value' => $attribute->getData(EavAttributeInterface::ATTRIBUTE_CODE),
                ]
            );
        }

        return $this->options;
    }
}
