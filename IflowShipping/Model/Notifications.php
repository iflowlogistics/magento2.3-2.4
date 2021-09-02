<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

namespace Iflow\IflowShipping\Model;


use Iflow\IflowShipping\Api\NotificationsInterface;

class Notifications implements NotificationsInterface
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory
     */
    private $shipmentCollectionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory
     */
    private $trackCollectionFactory;

    /**
     * @var \Iflow\IflowShipping\Helper\Data
     */
    private $iflowHelper;

    /**
     * @var bool
     */
    private $debugEnable;

    public function __construct
    (
        \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory $trackCollectionFactory,
        \Iflow\IflowShipping\Helper\Data $iflowHelper
    )
    {
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->trackCollectionFactory = $trackCollectionFactory;
        $this->iflowHelper = $iflowHelper;
        $this->debugEnable = $this->iflowHelper->isDebugEnabled();
    }

    /**
     * @param string $track_id
     * @param string $shipment_id
     * @param string $status
     * @return array
     * @throws \Exception
     */
    public function updateStatus($track_id, $shipment_id, $status)
    {
        $updatedEntities = '';
        $response = '';
        $this->logInCustomFile('INIT :: trackid - ' . $track_id . ' | shipmentid - ' . $shipment_id . ' | status - ' . $status);
        try {
            if ($shipment_id != '') {
                $shipmentCollection = $this->shipmentCollectionFactory->create()->addFieldToFilter('iflow_shipment_id', ['eq' => $shipment_id]);

                if ($shipmentCollection->count() > 0) {
                    /**
                     * @var \Magento\Sales\Model\Order\Shipment $shipment
                     */
                    foreach ($shipmentCollection as $shipment) {
                        $shipment->getOrder()->setData('iflow_status', $status)->save();
                        $updatedEntities .= '[Shipment id ' . $shipment->getId() . ']';
                    }
                } else {
                    $response = json_encode(['status' => 'ERROR', 'message' => "No se encontro pedido con shipment id $shipment_id"]);
                    $this->logInCustomFile($response);
                    return $response;
                }
            }

            if ($track_id != '') {
                $trackCollection = $this->trackCollectionFactory->create()->addFieldToFilter('track_number', ['eq' => $track_id]);

                if ($trackCollection->count() > 0) {
                    /**
                     * @var \Magento\Sales\Model\Order\Shipment\Track $track
                     */
                    foreach ($trackCollection as $track) {
                        $track->getShipment()->setData('iflow_status', $status)->save();
                        $updatedEntities .= '[Track id ' . $track->getId() . ']';
                    }
                } else {
                    $response = json_encode(['status' => 'ERROR' , 'message' => "no se encontro un pedido con trackid $track_id"]);
                    $this->logInCustomFile($response);
                    return $response;
                }
            }
        }catch (\Exception $e){
            $response = json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
            $this->logInCustomFile($response);
            return $response;
        }
        $response = json_encode(['status' => 'OK','message' => "Entidades actualizadas: $updatedEntities"]);
        $this->logInCustomFile($response);
        return $response;
    }

    private function logInCustomFile($msge){
        if($this->debugEnable) {
            \Iflow\IflowShipping\Helper\Data::log($msge,'iflow_endpoint.log');
        }
    }
}