<?php

namespace Lightweight\PartialShipping\Block\Adminhtml\System\Config\Form\Field;

use Lightweight\PartialShipping\Model\Config\Source\Countries as CountriesSource;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * Class Countries
 */
class Countries extends Select
{
    /**
     * @var array
     */
    protected $countries = [];

    /**
     * @var CountriesSource
     */
    protected $countriesSource;

    /**
     * Countries constructor.
     *
     * @param Context         $context
     * @param CountriesSource $countriesSource
     * @param array           $data
     */
    public function __construct(
        Context $context,
        CountriesSource $countriesSource,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->countriesSource = $countriesSource;
    }

    /**
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->getCountriesSource() as $country) {
                if (isset($country['value']) && $country['value'] && isset($country['label']) && $country['label']) {
                    $this->addOption($country['value'], $country['label']);
                }
            }
        }

        return parent::_toHtml();
    }

    /**
     * @return array
     */
    public function getCountriesSource()
    {
        if (!$this->countries) {
            $this->countries = $this->countriesSource->toOptionArray();
        }

        return $this->countries;
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
}
