<?php
/**
 * Copyright © 2019 Paazl. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Paazl\CheckoutWidget\Model\Api\Converter\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\ArrayManager;
use Paazl\CheckoutWidget\Model\Api\Field\DeliveryType;
use Paazl\CheckoutWidget\Model\ShippingInfo;
use Paazl\CheckoutWidget\Model\ShippingInfoFactory;

/**
 * Class ToShippingInfo
 * Converts response from Checkout API to internal ShippingInfo object
 *
 * @package Paazl\CheckoutWidget\Model\Api\Converter\Checkout
 */
class ToShippingInfo
{

    /**
     * @var ShippingInfoFactory
     */
    private $shippingInfoFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var ArrayManager
     */
    private $arrayManager;

    /**
     * ToShippingInfo constructor.
     *
     * @param ShippingInfoFactory $shippingInfoFactory
     * @param Json                $json
     * @param ArrayManager        $arrayManager
     */
    public function __construct(
        ShippingInfoFactory $shippingInfoFactory,
        Json $json,
        ArrayManager $arrayManager
    ) {
        $this->shippingInfoFactory = $shippingInfoFactory;
        $this->json = $json;
        $this->arrayManager = $arrayManager;
    }

    /**
     * @param string|array $response
     *
     * @return ShippingInfo
     * @throws LocalizedException
     */
    public function convert($response)
    {
        $info = $this->shippingInfoFactory->create();

        $result = $response;
        if (!is_array($result) && !empty($result)) {
            $result = $this->json->unserialize($response);
        }

        $info->setType($this->arrayManager->get('deliveryType', $result));
        $info->setIdenfifier($this->arrayManager->get('shippingOption/identifier', $result));
        $info->setPrice(floatval($this->arrayManager->get('shippingOption/rate', $result)));
        $info->setOptionTitle($this->arrayManager->get('shippingOption/name', $result));
        $info->setPreferredDeliveryDate($this->arrayManager->get('preferredDeliveryDate', $result));
        $info->setCarrierPickupDate($this->arrayManager->get('pickupDate', $result));

        if ($info->getType() === DeliveryType::PICKUP) {
            $info->setPickupDate($this->arrayManager->get('pickupDate', $result));
            $info->setLocationCode($this->arrayManager->get('pickupLocation/code', $result));
            $info->setLocationAccountNumber($this->arrayManager->get('pickupLocation/accountNumber', $result));
            $info->setLocationName($this->arrayManager->get('pickupLocation/name', $result));
            $info->setPickupAddress($this->arrayManager->get('pickupLocation/address', $result));
        }

        return $info;
    }
}
