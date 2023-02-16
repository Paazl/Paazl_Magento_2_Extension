<?php
/**
 * Copyright © 2019 Paazl. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Paazl\CheckoutWidget\Model\Api;

use Exception;
use Magento\Framework\HTTP\ClientInterface;
use Paazl\CheckoutWidget\Helper\General as GeneralHelper;
use Paazl\CheckoutWidget\Model\Api\Http\ClientFactory;
use Paazl\CheckoutWidget\Model\Api\Response\Data\Token;
use Paazl\CheckoutWidget\Model\Api\Response\Data\TokenBuilder;

/**
 * Class PaazlApi
 *
 * @package Paazl\CheckoutWidget\Model\Api
 */
class PaazlApi
{

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var array
     */
    private $request = [];

    /**
     * @var GeneralHelper
     */
    private $generalHelper;

    /**
     * @var ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var TokenBuilder
     */
    private $tokenBuilder;

    /**
     * @var UrlProvider
     */
    private $urlProvider;

    /**
     * PaazlApi constructor.
     *
     * @param Configuration $configuration
     * @param GeneralHelper $generalHelper
     * @param ClientFactory $httpClientFactory
     * @param TokenBuilder  $tokenBuilder
     * @param UrlProvider   $urlProvider
     */
    public function __construct(
        Configuration $configuration,
        GeneralHelper $generalHelper,
        ClientFactory $httpClientFactory,
        TokenBuilder $tokenBuilder,
        UrlProvider $urlProvider
    ) {
        $this->configuration = $configuration;
        $this->generalHelper = $generalHelper;
        $this->httpClientFactory = $httpClientFactory;
        $this->tokenBuilder = $tokenBuilder;
        $this->urlProvider = $urlProvider;
    }

    /**
     * Returns API token
     *
     * @param string $reference
     * @return Token $response
     * @throws ApiException
     */
    public function getApiToken($reference)
    {
        $url = $this->urlProvider->getCheckoutTokenUrl();

        $httpClient = $this->getAuthorizedClient();

        try {
            $this->request['reference'] = $reference;
            $this->generalHelper->addTolog('Token request: ', $this->request);

            $httpClient->addHeader('Content-Type', 'application/json;charset=UTF-8');
            $httpClient->addHeader('Accept', 'application/json;charset=UTF-8');

            $httpClient->post($url, json_encode($this->request));
            $body = $httpClient->getBody();
            $status = $httpClient->getStatus();

            $this->generalHelper->addTolog('Token response status: ', $status);
            $this->generalHelper->addTolog('Token response: ', $body);
            if ($status >= 200 && $status < 300) {
                /** @var Token $token */
                $token = $this->tokenBuilder->setResponse($body)->create();
                return $token;
            }
        } catch (Exception $e) {
            throw new ApiException('API error', 0, $e);
        }

        throw new ApiException('API error', 0);
    }

    /**
     * Sends order to Paazl
     *
     * @param array $orderData
     * @param bool $modify
     * @return boolean
     * @throws ApiException
     */
    public function addOrder(array $orderData, bool $modify = false)
    {
        $url = $this->urlProvider->getOrderUrl();

        $httpClient = $this->getAuthorizedClient();

        $httpClient->addHeader('Content-Type', 'application/json;charset=UTF-8');
        $httpClient->addHeader('Accept', 'application/json;charset=UTF-8');

        if ($modify == false) {
            $this->generalHelper->addTolog('AddOrder request: ', $orderData);
            $httpClient->post($url, json_encode($orderData));
            $body = $httpClient->getBody();
            $status = $httpClient->getStatus();

            $this->generalHelper->addTolog('AddOrder response status: ', $status);
            $this->generalHelper->addTolog('AddOrder response body: ', $body);
        } else {
            $this->generalHelper->addTolog('ModifyOrder request: ', $orderData);
            unset($orderData['products']);
            $httpClient->put($url, json_encode($orderData));
            $body = $httpClient->getBody();
            $status = $httpClient->getStatus();

            $this->generalHelper->addTolog('ModifyOrder response status: ', $status);
            $this->generalHelper->addTolog('ModifyOrder response body: ', $body);
        }

        if ($status >= 400 && $status < 500) {
            throw new ApiException($body, $status, null, true);
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        throw new ApiException('API error', 0);
    }

    /**
     * @param array $orderData
     *
     * @return string
     * @throws ApiException
     */
    public function getShippingOptions(array $orderData)
    {
        $url = $this->urlProvider->getShippingOptionsUrl();

        $httpClient = $this->getAuthorizedClient();

        $this->generalHelper->addTolog('getShippingOptions request: ', $orderData);

        $httpClient->addHeader('Content-Type', 'application/json;charset=UTF-8');
        $httpClient->addHeader('Accept', 'application/json;charset=UTF-8');

        $httpClient->post($url, json_encode($orderData));
        $body = $httpClient->getBody();
        $status = $httpClient->getStatus();

        $this->generalHelper->addTolog('getShippingOptions response status: ', $status);
        $this->generalHelper->addTolog('getShippingOptions response: ', $body);
        if ($status >= 400 && $status < 500) {
            throw new ApiException($body, $status, null, true);
        }

        if ($status >= 200 && $status < 300) {
            return $body;
        }

        throw new ApiException('API error', 0);
    }

    /**
     * @param string $reference
     *
     * @return mixed|null
     * @throws ApiException
     * @throws Exception
     */
    public function fetchCheckoutData($reference)
    {
        $url = $this->urlProvider->getCheckoutUrl();
        $httpClient = $this->getAuthorizedClient();

        try {
            $url .= '?' . http_build_query([
                    'reference' => $reference
                ]);

            $httpClient->addHeader('Accept', 'application/json;charset=UTF-8');

            $this->generalHelper->addTolog('fetchCheckoutData URL: ', $url);

            $httpClient->get($url);
            $status = $httpClient->getStatus();
            $body = $httpClient->getBody();
            $this->generalHelper->addTolog('fetchCheckoutData response status: ', $status);
            $this->generalHelper->addTolog('fetchCheckoutData response: ', $body);
        } catch (Exception $e) {
            $this->generalHelper->addTolog('exception', $e->getMessage());
            throw new ApiException('API error', 0, $e);
        }

        if ($status !== 200) {
            // @codingStandardsIgnoreLine
            throw new Exception('Cannot obtain checkout info');
        }

        return json_decode($body, true);
    }

    /**
     * @return string
     */
    private function buildAuthorizationHeader()
    {
        return 'Bearer ' . $this->configuration->getKey() . ':' . $this->configuration->getSecret();
    }

    /**
     * @return ClientInterface
     */
    private function getAuthorizedClient(): ClientInterface
    {
        $httpClient = $this->httpClientFactory->create();
        $httpClient->setHeaders([
            'Authorization' => $this->buildAuthorizationHeader()
        ]);

        $timeout = $this->configuration->getTimeout();
        if ($timeout > 0) {
            $httpClient->setTimeout($timeout);
        }

        return $httpClient;
    }
}
