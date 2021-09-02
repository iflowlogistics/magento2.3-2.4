<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

namespace Iflow\IflowShipping\Block\Adminhtml\Order\Tab;


use Magento\Sales\Model\Order\Shipment;

class View extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    protected $_template = 'order/tab/view/iflow_order_info.phtml';

    protected $_coreRegistry;

    /**
     * View constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('current_order');
    }
    /**
     * Retrieve order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrderId()
    {
        return $this->getOrder()->getEntityId();
    }

    /**
     * Retrieve order increment id
     *
     * @return string
     */
    public function getOrderIncrementId()
    {
        return $this->getOrder()->getIncrementId();
    }
    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return 'Iflow - detalle de envio';
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return 'Iflow - detalle de envio';
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return $this->getOrder()->getShippingMethod() !== 'iflow_iflow';
    }

    public function getIflowShipmentData(){
        $order = $this->getOrder();
        $result = [
            'order_status' => $order->getIflowStatus(),
            'shipments' => []
        ];
        /**
         * @var Shipment $shipment
         */
        foreach ($order->getShipmentsCollection() as $shipment){
            $shipmentData = [
                'magento_shipment' => $shipment->getIncrementId(),
                'iflow_shipment_id' => $shipment->getIflowShipmentId(),
                'status' => $shipment->getIflowStatus(),
                'tracks' => ''
            ];
            $tracks = [];
            foreach ($shipment->getTracks() as $track){
                $tracks[] = $track->getTrackNumber();
            }
            $shipmentData['tracks'] = implode(' - ', $tracks);
            $result['shipments'][] = $shipmentData;
        }
        return $result;
    }
}