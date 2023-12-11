<?php

namespace Lightweight\PartialShipping\Block\System\Config\Form\Field\ShippingRates;

use Lightweight\PartialShipping\Block\Adminhtml\System\Config\Form\Field\Countries;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\BlockInterface;

/**
 * Class FieldArray
 */
class FieldArray extends AbstractFieldArray
{
    /**
     * @var null|array
     */
    protected $countryRenderer = null;

    /**
     * Prepare to render the columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'dest_country', [
                'label'    => __('Dest. Country'),
                'renderer' => $this->getCountryRenderer(),
                'class'    => 'required-entry',
            ]
        );
        $this->addColumn(
            'price_from', [
                'label' => __('Price From'),
                'class' => 'validate-zero-or-greater validate-number required-entry',
            ]
        );
        $this->addColumn(
            'price_to', [
                'label' => __('Price To'),
                'class' => 'validate-zero-or-greater validate-number required-entry',
            ]
        );
        $this->addColumn(
            'cost', [
                'label' => __('Shipping Cost'),
                'class' => 'validate-zero-or-greater validate-number required-entry',
            ]
        );
    }

    /**
     * Get renderer
     *
     * @return array|BlockInterface|null
     */
    protected function getCountryRenderer()
    {
        if (!$this->countryRenderer) {
            try {
                $this->countryRenderer = $this->getLayout()->createBlock(
                    Countries::class,
                    '',
                    ['data' => ['is_render_to_js_template' => true]]
                );
            } catch (\Exception $e) {
                $this->countryRenderer = [];
            }
        }

        return $this->countryRenderer;
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $options = [];
        $country = $row->getData('dest_country');

        if ($country) {
            $options['option_' . $this->getCountryRenderer()->calcOptionHash($country)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }
}
