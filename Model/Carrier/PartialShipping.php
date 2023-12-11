<?php

namespace Lightweight\PartialShipping\Model\Carrier;

use Lightweight\PartialShipping\Logger\Logger;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class PartialShipping extends AbstractCarrier implements CarrierInterface
{
    const DEFAULT_GROUPING_ATTR_VALUE = '__NONE__';

    /**
     * @var string
     */
    protected $_code = 'partial_shipping';

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory         $rateErrorFactory
     * @param LoggerInterface      $logger
     * @param ResultFactory        $rateResultFactory
     * @param MethodFactory        $rateMethodFactory
     * @param ProductRepository    $productRepository
     * @param Logger               $customLogger
     * @param array                $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        ProductRepository $productRepository,
        Logger $customLogger,
        array $data = []
    )
    {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->productRepository = $productRepository;

        parent::__construct($scopeConfig, $rateErrorFactory, $customLogger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @param RateRequest $request
     *
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

//        $this->log('Configured shipping rates: ' . var_export(json_decode($this->getConfigData('shipping_rates'), true), true));

        $shippingGroups = $this->findShippingGroups($request);

        $minShippingGroups = $this->getConfigData('min_shipment_groups');
        if (count($shippingGroups) < $minShippingGroups) {
            return false;
        }

        $cost   = $this->calcShippingCost($request, $shippingGroups);
        $method = $this->createResultMethod($cost);

        /** @var Result $result */
        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * @param RateRequest $request
     * @param array       $shippingGroups
     *
     * @return float
     */
    protected function calcShippingCost(RateRequest $request, array $shippingGroups)
    {
        $shippingRates      = json_decode($this->getConfigData('shipping_rates'), true);
        $requestDestCountry = $request->getDestCountryId();
        $shippingCosts      = 0.0;

        foreach ($shippingGroups as $shippingGroupName => $shippingGroupTotal) {
            $shippingRateFound = false;

            foreach ($shippingRates as $shippingRate) {
                $destCountry = $shippingRate['dest_country'];
                $priceFrom   = (float)trim($shippingRate['price_from']);
                $priceTo     = (float)trim($shippingRate['price_to']);
                $cost        = (float)trim($shippingRate['cost']);

                if ($requestDestCountry == $destCountry
                    && $shippingGroupTotal >= $priceFrom
                    && $shippingGroupTotal < $priceTo
                ) {
                    $shippingCosts     += $cost;
                    $shippingRateFound = true;
                    $this->log(
                        sprintf(
                            'Found shipping rate for group "%s": %s-%s-%s => %s',
                            $shippingGroupName, $destCountry, $priceFrom, $priceTo, $cost
                        )
                    );
                }
            }

            if (!$shippingRateFound) {
                $fallbackShippingCost = (float)$this->getConfigData('fallback_shipping_rate');
                $shippingCosts        += $fallbackShippingCost;
                $this->log(sprintf('No shipping rate found for group `%s`, using fallback => %s', $shippingGroupName, $fallbackShippingCost));
            }
        }

        $this->log('Calculated shipping costs: ' . $shippingCosts);

        return $shippingCosts;
    }

    /**
     * @param RateRequest $request
     *
     * @return array
     */
    protected function findShippingGroups(RateRequest $request)
    {
        $groupingAttribute = $this->getConfigData('grouping_attribute');
        $shippingGroups    = [];
        $quoteId           = 'n.a.';

        /* @var $item Item */
        foreach ($request->getAllItems() as $item) {
            $quoteId = $item->getQuoteId();

            if ($item->getProductType() == 'simple') {
                $groupingAttributeValue = $this->getGroupingAttributeValueFromProduct($item->getProduct()->getId(), $groupingAttribute);

                if (!isset($shippingGroups[$groupingAttributeValue])) {
                    $shippingGroups[$groupingAttributeValue] = 0;
                }

                $shippingGroups[$groupingAttributeValue] += $item->getParentItemId()
                    ? $item->getParentItem()->getRowTotalInclTax()
                    : $item->getRowTotalInclTax();
            }
        }

        $this->log('Quote ID: ' . $quoteId);
        $this->log('Groups and totals per group: ' . var_export($shippingGroups, true));

        return $shippingGroups;
    }

    /**
     * @param $productId
     * @param $groupingAttribute
     *
     * @return string
     */
    protected function getGroupingAttributeValueFromProduct($productId, $groupingAttribute)
    {
        $groupingAttributeValue = self::DEFAULT_GROUPING_ATTR_VALUE;

        try {
            $product   = $this->productRepository->getById($productId);
            $attrValue = trim($product->getData($groupingAttribute));

            if (!empty($attrValue)) {
                $groupingAttributeValue = (string)$attrValue;
            }
        } catch (\Exception $e) {
        }

        return $groupingAttributeValue;
    }

    /**
     * @param int|float $shippingPrice
     *
     * @return Method
     */
    protected function createResultMethod($shippingPrice)
    {
        /** @var Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        return $method;
    }

    /**
     * @param       $message
     * @param int   $level
     * @param array $context
     */
    public function log($message, $level = Logger::INFO, array $context = [])
    {
        if ($this->getConfigData('logging_enabled')) {
            $this->_logger->log($level, $message, $context);
        }
    }
}
