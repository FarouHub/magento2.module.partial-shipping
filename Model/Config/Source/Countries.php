<?php

namespace Lightweight\PartialShipping\Model\Config\Source;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Countries implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var array
     */
    protected $countries = null;

    /**
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
        if (!$this->countries) {
            try {
                $this->countries = $this->collectionFactory->create()
                                                           ->loadData()
                                                           ->toOptionArray(false);
            } catch (\Exception $e) {
                $this->countries = [];
            }
        }

        return $this->countries;
    }
}
