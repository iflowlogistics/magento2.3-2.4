<?php
namespace Iflow\IflowShipping\Model;
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
//use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Constraint\IsFalse;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'iflow';

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = false;

    /**
     * Container types that could be customized
     *
     * @var string[]
     */
    protected $_customizableContainerTypes = ['CUSTOM'];
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Catalog\Model\Product 
     */
    protected $productModel;

    /**
     * @var bool
     */
    protected $debugEnable;

    /**
     * @var \Iflow\IflowShipping\Helper\Data
     */
    protected $iflowHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterfaceFactory
     */
    private $_storeManagerFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Iflow\IflowShipping\Helper\Data $iflowHelper,
        \Magento\Store\Model\StoreManagerInterfaceFactory $storeManagerFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->_rateFactory = $rateFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        $this->customerSession = $customerSession;
        $this->productModel = $productModel;
        $this->iflowHelper = $iflowHelper;
        $this->_storeManagerFactory = $storeManagerFactory;
        $this->debugEnable = $this->iflowHelper->isDebugEnabled();
    }

    public function collectRates(RateRequest $request)
    {
        $this->logInCustomFile(json_encode($request->getData()));
        $this->logInCustomFile('collectRates call');
        if (!$this->iflowHelper->isActive()) {
            return false;
        }
        $shippingPrice = $this->iflowHelper->getFixedPrice();
        $packages = array();
        foreach($request->getAllItems() as $item){
            $product = $this->productModel->load($item->getProductId());
            if ($product->getTypeId() == "simple") {
                $itemHeight = $product->getTsDimensionsHeight();
                $itemLength = $product->getTsDimensionsLength();
                $itemWidth = $product->getTsDimensionsWidth();
                $itemWeight = $product->getWeight();
                $price = $item->getPrice();
                if ((int)$price== 0){
                    $price = (int)$product->getPrice();
                }
                $this->logInCustomFile("PRECIO ".$price);
                $qty = $item->getQty();
                for($i =0 ; $i < $item->getQty(); $i++) {
                    $packages[] = array(
                        'width' => (int)$itemWidth,
                        'height' => (int)$itemHeight,
                        'length' => (int)$itemLength,
                        'real_weight' => $itemWeight,// * 1000, //Quitado el *1000, manda la cotizacion en kg pero la impresion de paquetes en g;
                        'gross_price' => $price,
                    );
                }
            }
        }
        $province = $request->getDestRegionCode();

        $customerSession = $this->customerSession;
        $customer = $customerSession->getCustomer();
        if ($customer) {
            $shippingAddress = $customer->getDefaultShippingAddress();
            if ($shippingAddress) {
                $province = $shippingAddress->getRegionCode();
            }
        }
        
        $zip = $request->getDestPostcode();
        $shipment_data = array(
            'zip_code' => $zip,
            'province' => $province,
            'packages' => $packages,
            'delivery_mode' => 1,
        );
        $this->logInCustomFile(json_encode($shipment_data));
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $price_result_json = $this->_getShipmentRate($shipment_data);

        $price_result  = json_decode($price_result_json, TRUE);
        $this->logInCustomFile("Price result");
        $this->logInCustomFile($price_result_json);

        if(isset($price_result["code"]) && $price_result["code"] == 500){
            if ($this->iflowHelper->showMethodOnError()) {
                $error = $this->_rateErrorFactory->create();
                $error->setCarrier($this->_code);
                $error->setCarrierTitle($this->iflowHelper->getTitle());
                $error->setErrorMessage(__('No existen cotizaciones para el código postal ingresado.'));
                return $error;
            } else {
                return false;
            }
        }

        if(isset($price_result["results"])) {
            if(isset($price_result["results"]["final_value"])) {
                $shippingPrice = $price_result["results"]["final_value"];
            }
        }
        
        if($request->getFreeShipping()){
            $shippingPrice = 0;
            $this->logInCustomFile("Is free shipping");
        }
        
        $result = $this->_rateFactory->create();
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->iflowHelper->getTitle());
        $method->setMethod($this->_code);
        $method->setMethodTitle($this->iflowHelper->getName());
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $result->append($method);
        $this->logInCustomFile('collectRates result');

        return $result;
    }

    protected function _getShipmentRate($payload)
    {
        $this->logInCustomFile('_doShipmentRequest call');
        $payload['softlightUser'] = $this->iflowHelper->getUsername();
        $payload['softlightPassword'] = $this->iflowHelper->getPassword();
        $payloadJson = json_encode($payload);

        $url = $this->iflowHelper->getUrl() . 'magento/orders/getrate';
        $this->logInCustomFile('Body: '. $payloadJson);
        $this->logInCustomFile('Api url: '.$url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $error = false;
        $this->logInCustomFile('CURL RESPONSE: ' . $response);
        $this->logInCustomFile('CURL CINFO');
        if ($response === false) {
            $error = "No cURL data returned for $url [". $cinfo['http_code']. "]";
            if (curl_error($ch)) {
                $error .= "\n". curl_error($ch);
            }
        } else {
            if (! in_array($cinfo['http_code'], [200, 201])) {
                $error = "API CODE {$cinfo['http_code']}: $response";
            } else {
                $result = $response;
                $resultJson = json_decode($response);
            }
        }
        curl_close($ch);

        if ($error) {
            $this->logInCustomFile('API error: ' . $error);
        }

        $this->logInCustomFile('API response: ' . $response);

        return $response;
    }

    /**
     * Do request to shipment
     *
     * @param Request $request
     * @return \Magento\Framework\DataObject
     */
    public function requestToShipment($request)
    {
        $this->logInCustomFile('requestToShipment call');
        $packages = $request->getPackages();
        if (!is_array($packages) || !$packages) {
            throw new LocalizedException(__('No packages for request'));
        }
        if ($request->getStoreId() != null) {
            $this->setStore($request->getStoreId());
        }
        $data = [];
        foreach ($packages as $packageId => $package) {
            $request->setPackageId($packageId);
            $this->logInCustomFile("PARAMETROS".json_encode($package['params']));
            $request->setPackagingType($package['params']['container']);
            $request->setPackageWeight($package['params']['weight']);
            $request->setPackageParams(new \Magento\Framework\DataObject($package['params']));
            $items = $package['items'];
            foreach ($items as $itemid => $item) {
                $this->logInCustomFile("WEIGHT: ".($item['weight']*1000));
                $items[$itemid]['weight'] = $item['weight']*1000;                      
            }
            $this->logInCustomFile("ITEMS: ".print_r($package['items'],true));
            $request->setPackageItems($items);

            $result = $this->_doShipmentRequest($request);

            if ($result->hasErrors()) {
                $this->logInCustomFile('Result has errors');
                $this->rollBack($data);
                break;
            } else {
                $data[] = [
                    'tracking_number' => $result->getTrackingNumber(),
                    'label_content' => $result->getLabelContent(),
                    'description' => $result->getDescription(),
                    'shipment_id' => $result->getShipmentId()
                ];
            }
            $this->logInCustomFile('Description ' . $result->getDescription());
            if (!isset($isFirstRequest)) {
                $this->logInCustomFile('Setting Master Tracking Id: ' . $result->getTrackingNumber());
                $request->setMasterTrackingId($result->getTrackingNumber());
                $isFirstRequest = false;
            }
        }

        $response = new \Magento\Framework\DataObject(['info' => $data]);
        if ($result->getErrors()) {
            $response->setErrors($result->getErrors());
        }

        return $response;
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $this->logInCustomFile('_doShipmentRequest call');
        $payload = $this->_getRequestPayload($request);
        $payloadJson = json_encode($payload);

        $storeId = $this->iflowHelper->getStoreId();
        $url = $this->iflowHelper->getUrl();
        //$storeId = '569cb42fa9c8d4d3cbd16464';
        $url .= 'magento/orders/' . $storeId . '/create';
        $this->logInCustomFile('Api url: '.$url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $error = false;
        $this->logInCustomFile('CURL RESPONSE: ' . $response);
        $this->logInCustomFile('CURL CINFO');
        $this->logInCustomFile(json_encode($cinfo));
        if ($response === false) {
            $error = "No cURL data returned for $url [". $cinfo['http_code']. "]";
            if (curl_error($ch)) {
                $error .= "\n". curl_error($ch);
            }
        } else {
            if (! in_array($cinfo['http_code'], [200, 201])) {
                $error = "API CODE {$cinfo['http_code']}: $response";
            } else {
                $result = $response;
                $resultJson = json_decode($response);
            }
        }
        curl_close($ch);

        if ($error) {
            $this->logInCustomFile('API error: ' . $error);
            throw new \Magento\Framework\Exception\LocalizedException(__('Error contacting API: '.$response));
        }

        $this->logInCustomFile('API response: ' . $result);

        if (! $resultJson->success) {
            $this->logInCustomFile('API unsuccessful response: ' . $result);
            throw new \Magento\Framework\Exception\LocalizedException(__('Error creating shipment'));
        } else {
            $trackingNumber = '';
            $labelContent = '';
            try {
                $trackingNumber = $resultJson->results->tracking_id;
                $shipping = $resultJson->results->shippings[0];
                $labelUrl = $shipping->print_url;
                $shipmentId = $shipping->shipment_id;
                $labelContent = $this->_getLabelContentFromUrl($labelUrl);
            } catch (Exception $e) {
                $this->logInCustomFile('API response parsing error: ' . $e->getMessage());
            }
            
            return new \Magento\Framework\DataObject([
                'tracking_number' => $trackingNumber,
                'label_content' => $labelContent,
                'description' => $labelUrl,
                'shipment_id' => $shipmentId
                ]);
        }
    }

    protected function _getLabelContentFromUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $error = false;
        
        if ($response === false) {
            $error = "No cURL data returned for $url [". $cinfo['http_code']. "]";
            if (curl_error($ch)) {
                $error .= "\n". curl_error($ch);
            }
        } else {
            if ($cinfo['http_code'] != 200) {
                $error = "API CODE {$cinfo['http_code']}: $response";
            } else {
                $result = $response;
            }
        }
        curl_close($ch);

        if ($error) {
            $this->logInCustomFile('Error retrieving Labels: ' . $error);
            throw new \Magento\Framework\Exception\LocalizedException(__('Error retrieving Labels'));
        }
        return $result;
    }
    
    /**
     * Form Object with appropriate structure for shipment request
     *
     * @param \Magento\Framework\DataObject $request
     * @return \stdClass
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _getRequestPayload(\Magento\Framework\DataObject $request)
    {
        $this->logInCustomFile('_getRequestPayload call');
        $packageParams = $request->getPackageParams();

	    $this->logInCustomFile('Request debug: ' . $request->toJson());
	    $addressAttributes = $this->iflowHelper->getAttributesMapping($request->getOrderShipment()->getOrder()->getShippingAddress());
        $this->logInCustomFile('Mapping attributes result: ' . json_encode($addressAttributes));

        $payload = new \stdClass;
        $payload->softlightUser = $this->iflowHelper->getUsername();
        $payload->softlightPassword = $this->iflowHelper->getPassword();
        //endpoint
        $payload->endpoint = $this->_storeManagerFactory->create()->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB) . "rest/default/V1/iflow/status";
        $payload->orderId = $request->getOrderShipment()->getOrder()->getIncrementId();
        $payload->packageId = $request->getPackageId(); // not req by SL
        $payload->name = $request->getRecipientContactPersonFirstName(); // not available separately
        $payload->lastname = $request->getRecipientContactPersonLastName();
        $payload->email = $request->getRecipientEmail(); // not available in default Magento
        $payload->phone = $request->getRecipientContactPhoneNumber();
        $payload->address = new \stdClass;
        $payload->address->street = $addressAttributes['calle'];
        $payload->address->number = $addressAttributes['numero']; // # not necessarily available
        $payload->address->comments = $addressAttributes['datos_adicionales']; // SL "between_1"
        $payload->address->floor = $addressAttributes['piso'];
        $payload->address->apartment = $addressAttributes['departamento'];
        $payload->address->postalCode = $request->getRecipientAddressPostalCode();
        $payload->address->city = $request->getRecipientAddressCity();
        $payload->address->state = 'BUENOS AIRES';//$request->getRecipientAddressStateOrProvinceCode();
        $payload->address->receiverName = $request->getRecipientContactPersonName();

        $payload->items = [];

        $packageItems = $request->getPackageItems();

        foreach ($packageItems as $itemShipment) {
            $reqItem = new \Magento\Framework\DataObject();
            $reqItem->setData($itemShipment);
            
            $item = new \stdClass;
                
            $item->productId = $reqItem->getProductId();
            $product = $this->productModel->load($reqItem->getProductId());
            if($product->getTypeId() == "configurable") {

                
                $_children = $product->getTypeInstance()->getUsedProducts($product);
                foreach ($_children as $child){
                    $this->logInCustomFile("Here are your child Product Ids ".$child->getID()."\n");
                    $product = $this->productModel->load($child->getID());
            
                    $item->height = $product->getTsDimensionsHeight();
                    $item->width = $product->getTsDimensionsWidth();
                    $item->length = $product->getTsDimensionsLength();
                    if (($item->height + $item->width + $item->length) > 0
                    ){
                        break;
                    }
                }

            }
            else {
                $item->height = $product->getTsDimensionsHeight();
                $item->width = $product->getTsDimensionsWidth();
                $item->length = $product->getTsDimensionsLength();
            }

            $item->name = $reqItem->getName();
            $item->weight = $reqItem->getWeight();
            $item->quantity = $reqItem->getQty();
            $item->price = $reqItem->getPrice();
            $payload->items[] = $item;
        }
    
        $this->logInCustomFile('Request data: ' . json_encode($payload));

        return $payload;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $this->logInCustomFile('getAllowedMethods call');
        return [$this->_code => $this->iflowHelper->getName()];
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return boolean
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Check if carrier has shipping label option available
     *
     * @return boolean
     */
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    /**
     * Is state province required
     *
     * @return bool
     */
    public function isStateProvinceRequired()
    {
        return true;
    }

    /**
     * Check if city option required
     *
     * @return bool
     */
    public function isCityRequired()
    {
        return true;
    }

    /**
     * Determine whether zip-code is required for the country of destination
     *
     * @param string|null $countryId
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isZipCodeRequired($countryId = null)
    {
        return true;
    }

    /**
     * Return delivery confirmation types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDeliveryConfirmationTypes(\Magento\Framework\DataObject $params = null)
    {
        return [
            'NO_SIGNATURE_REQUIRED' => __('Not Required'),
        ];
    }

    /**
     * Return container types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getContainerTypes(\Magento\Framework\DataObject $params = null)
    {
        return $this->_getAllowedContainers($params);
    }

    /**
     * Get allowed containers of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array|bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getAllowedContainers(\Magento\Framework\DataObject $params = null)
    {
        return $containersAll = $this->getContainerTypesAll();
    }

    /**
     * Return all container types of carrier
     *
     * @return array|bool
     */
    public function getContainerTypesAll()
    {
        return ['PAQUETE' => 'PAQUETE'];
    }

    /**
     * Processing additional validation to check if carrier applicable.
     *
     * @param \Magento\Framework\DataObject $request
     * @return $this|bool|\Magento\Framework\DataObject
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function proccessAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        $this->logInCustomFile('proccessAdditionalValidation call');
        return $this;
    }
    
    private function logInCustomFile($msge){
        if($this->debugEnable) {
            \Iflow\IflowShipping\Helper\Data::log($msge);
        }
    }
}
