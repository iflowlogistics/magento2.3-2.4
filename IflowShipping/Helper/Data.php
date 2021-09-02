<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

namespace Iflow\IflowShipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{
    const CARRIER_SECTION = 'carriers/iflow/';
    const SHIPPING_SECTION_CREDENTIALS = 'shipping/iflow/credentials/';
    const SHIPPING_SECTION_MAPPING= 'shipping/iflow/attributes_mapping/';

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    public function __construct(
        Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    )
    {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    public function isDebugEnabled(){
        return $this->getConfigFlag(
            self::SHIPPING_SECTION_CREDENTIALS . 'debug_mode'
        );
    }

    public function isActive(){
        return $this->getConfigFlag(self::CARRIER_SECTION . 'active');
    }

    public function getFixedPrice(){
        return $this->getConfigData(self::CARRIER_SECTION . 'price');
    }

    public function getTitle(){
        return $this->getConfigData(self::CARRIER_SECTION . 'title');
    }

    public function getName(){
        return $this->getConfigData(self::CARRIER_SECTION . 'name');
    }

    public function showMethodOnError(){
        return $this->getConfigFlag(self::CARRIER_SECTION . 'showmethod');
    }

    public function getErrorMessage(){
        return $this->getConfigData(self::CARRIER_SECTION . 'specificerrmsg');
    }

    public function getStoreId(){
        return $this->encryptor->decrypt($this->getConfigData(self::SHIPPING_SECTION_CREDENTIALS . 'store_id'));
    }

    public function getUrl(){
        return $this->isSandboxMode() ? $this->getConfigData(self::SHIPPING_SECTION_CREDENTIALS . 'sandbox_webservices_url') : $this->getConfigData(self::SHIPPING_SECTION_CREDENTIALS . 'production_webservices_url');
    }

    public function isSandboxMode(){
        return $this->getConfigFlag(
            self::SHIPPING_SECTION_CREDENTIALS . 'sandbox_mode'
        );
    }

    public function getUsername(){
        return $this->getConfigData(self::SHIPPING_SECTION_CREDENTIALS . 'softlightusername');
    }

    public function getPassword(){
        return  $this->encryptor->decrypt($this->getConfigData(self::SHIPPING_SECTION_CREDENTIALS . 'softlightpassword'));
    }

    public function getAttributesMapping($shippingAddress){
        $attributesJson = $this->getConfigData(self::SHIPPING_SECTION_MAPPING . 'attributes');
        $result = [
            'calle' => '',
            'numero' => '',
            'piso' => '',
            'departamento' => '',
            'datos_adicionales' => ''
        ];
        if(!empty($attributesJson)) {
            $attributesArray = json_decode($attributesJson, true);
            $street = $this->getAddressData($shippingAddress, $attributesArray['street']['attribute'], $attributesArray['street']['array_position']);
            $number = $this->getAddressData($shippingAddress, $attributesArray['number']['attribute'], $attributesArray['number']['array_position']);
            $floor = $this->getAddressData($shippingAddress, $attributesArray['floor']['attribute'], $attributesArray['floor']['array_position']);
            $apartment = $this->getAddressData($shippingAddress, $attributesArray['apartment']['attribute'], $attributesArray['apartment']['array_position']);
            $additional_notes = $this->getAddressData($shippingAddress, $attributesArray['additional_notes']['attribute'], $attributesArray['additional_notes']['array_position']);

            $datosAdicionalesArray = array();
            if($floor != '' || $apartment != '') {
                $datosAdicionalesArray[] = 'Piso/Departamento: ' . trim($floor . ' ' . $apartment);
            }
            if($additional_notes != ''){
                $datosAdicionalesArray[] = 'Observaciones: ' . $additional_notes;
            }

            $result['calle'] = trim($street);
            $result['numero'] = trim($number);
            $result['piso'] = trim($floor);
            $result['departamento'] = trim($apartment);

            if(count($datosAdicionalesArray) > 0) {
                $result['datos_adicionales'] = trim(implode(', ', $datosAdicionalesArray), ', ');
            }
        }

        if($result['calle'] == ''){
            $result['calle'] = $shippingAddress->getStreetLine(1);
        }
        if($result['numero'] == ''){
            $result['numero'] = $shippingAddress->getStreetLine(2);
        }

        return $result;
    }

    private function getAddressData($address, $attribute, $position){
        $value = '';
        if($attribute != ''){
            $getStatement = 'get' . $this->getFunctionFormat($attribute);
            $result = $address->$getStatement();

            if (is_array($result)) {
                if (isset($position) && array_key_exists($position, $result)) {
                    $value = $result[$position];
                }
            }
            else{
                $value = $result;
            }
//            $value = ($position != '') ? $addressObject->getData($attribute)[$position] : $addressObject->getData($attribute);
        }
        return $value;
    }

    private function getFunctionFormat($name){
        $pos = strpos($name, '_');
        while (($pos = strpos($name, '_')) !== false) {
            $start = substr($name, 0, $pos);
            $end = substr($name, $pos + 1);
            $name = $start . ucfirst($end);
        }
        return ucfirst($name);
    }

    private function getConfigFlag($path){
        return $this->scopeConfig->isSetFlag(
            $path
        );
    }

    private function getConfigData($path){
        return $this->scopeConfig->getValue(
            $path
        );
    }

    public static function log($message, $fileName = 'iflow_shipping.log')
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/' . $fileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}