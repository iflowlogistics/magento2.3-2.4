<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

namespace Iflow\IflowShipping\Api;


interface NotificationsInterface
{
    /**
     * @api
     * @param string $track_id
     * @param string $shipment_id
     * @param string $status
     * @return array
     */
    public function updateStatus($track_id, $shipment_id, $status);

}