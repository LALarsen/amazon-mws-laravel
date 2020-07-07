<?php namespace Sonnenglas\AmazonMws;

use Exception;
use Sonnenglas\AmazonMws\AmazonInboundCore;

/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Submits a shipment to Amazon or updates it.
 *
 * This Amazon Inbound Core object submits a request to create an inbound
 * shipment with Amazon. It can also update existing shipments. In order to
 * create or update a shipment, information from a Shipment Plan is required.
 * Use the AmazonShipmentPlanner object to retrieve this information.
 */
class AmazonTransportContent extends AmazonInboundCore
{
    private $shipmentId;

    /**
     * AmazonTransportContent submits a transport content to Amazon or updates it.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $mock = false, $m = null)
    {
        parent::__construct($s, $mock, $m);
    }

    /**
     * Information required to create an Amazon-partnered carrier shipping estimate, or
     * to alert the Amazon fulfillment center to the arrival of an inbound shipment by a
     * non-Amazon-partnered carrier. (Required)
     *
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should contain a list of arrays, each with the following fields:
     * <ul>
     * <li><b>NonPartneredSmallParcelData</b>
     * <ul>
     * <li>CarrierName - string</li>
     * <li>PackageList - array of tracking numbers</li>
     * </ul>
     * </li>
     * <li><b>NonPartneredLtlData</b>
     * <ul>
     * <li>CarrierName - string</li>
     * <li>ProNumber - string</li>
     * </ul>
     * </li>
     * <li><b>PartneredSmallParcelData</b>
     * <ul>
     * <li>TBD</li>
     * </ul>
     * </li>
     * <li><b>PartneredLtlData</b>
     * <ul>
     * <li>TBD</li>
     * </ul>
     * </li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return void|boolean <b>FALSE</b> if improper input
     * @throws Exception
     */
    public function setDetails($a)
    {
        if (!$a || is_null($a) || is_string($a)) {
            $this->log("Tried to set Items to invalid values", 'Warning');
            return false;
        }

        $this->resetDetails();
        foreach ($a as $key => $x) {
            switch ($key) {
                case 'NonPartneredSmallParcelData';
                    if (is_array($x) && array_key_exists('CarrierName', $x) && array_key_exists('PackageList', $x) && is_array($x['PackageList'])) {
                        $this->options['TransportDetails.NonPartneredSmallParcelData.CarrierName'] = $x['CarrierName'];
                        $i = 1;
                        foreach ($x['PackageList'] as $trackingId) {
                            $this->options['TransportDetails.NonPartneredSmallParcelData.PackageList.member.' . $i . '.TrackingId'] = $trackingId;
                            $i++;
                        }
                    } else {
                        $this->resetDetails();
                        $this->log("Tried to set NonPartneredSmallParcelData with invalid array", 'Warning');
                        return false;
                    }
                    break;
                case 'NonPartneredLtlData';
                    if (is_array($x) && array_key_exists('CarrierName', $x) && array_key_exists('ProNumber', $x)) {
                        $this->options['TransportDetails.NonPartneredSmallParcelData.CarrierName'] = $x['CarrierName'];
                        $this->options['TransportDetails.NonPartneredSmallParcelData.ProNumber'] = $x['ProNumber'];
                    } else {
                        $this->resetDetails();
                        $this->log("Tried to set NonPartneredLtlData with invalid array", 'Warning');
                        return false;
                    }
                    break;
            }
        }
    }

    /**
     * Resets the transport detail options.
     *
     * Since the transport details is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    private function resetDetails()
    {
        foreach ($this->options as $op => $junk) {
            if (preg_match("#TransportDetails#", $op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Indicates whether the request is for an
     * Amazon-partnered carrier. (Required)
     * @param boolean $b
     */
    public function setPartnered($b)
    {
        if ($b) {
            $this->options['IsPartnered'] = 'true';
        } else {
            $this->options['IsPartnered'] = 'false';
        }
    }

    /**
     * Sets the shipment type. (Required)
     * @param string $s <p>"SP" or "LTL"</p>
     * @return void|boolean <b>FALSE</b> if improper input
     */
    public function setType($s)
    {
        if (is_string($s) && $s) {
            if ($s == 'SP' || $s == 'LTL') {
                $this->options['ShipmentType'] = $s;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Sets the shipment ID. (Required)
     * @param string $s <p>Shipment ID</p>
     * @return void|boolean <b>FALSE</b> if improper input
     */
    public function setShipmentId($s)
    {
        if (is_string($s) && $s) {
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sends a request to Amazon to create an Inbound Shipment.
     *
     * Submits a <i>PutTransportContent</i> request to Amazon. In order to do this,
     * all parameters must be set. Amazon will send back the Shipment ID
     * as a response, which can be retrieved using <i>getShipmentId</i>.
     * @return boolean <b>TRUE</b> if success, <b>FALSE</b> if something goes wrong
     * @throws Exception
     */
    public function put()
    {
        if (!isset($this->options['ShipmentId'])) {
            $this->log("Shipment ID must be set in order to create it", 'Warning');
            return false;
        }
        if (!isset($this->options['ShipmentType'])) {
            $this->log("Shipment type must be set in order to create it", 'Warning');
            return false;
        }
        if (!isset($this->options['IsPartnered'])) {
            $this->log("Shipment partner indication must be set in order to create it", 'Warning');
            return false;
        }
        if (!isset($this->options['TransportDetails.NonPartneredSmallParcelData.CarrierName'], $this->options['TransportDetails.NonPartneredSmallParcelData.CarrierName'])) {
            $this->log("Shipment details must be set in order to create it", 'Warning');
            return false;
        }
        $this->options['Action'] = 'PutTransportContent';

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'] . 'Result';
        if ($this->mockMode) {
            $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)) {
                return false;
            }

            $xml = simplexml_load_string($response['body'])->$path;
        }

        if ($xml->TransportResult->TransportStatus == 'WORKING') {
            $this->log("Successfully sent transport details ");
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the shipment ID of the newly created/modified order.
     * @return string|boolean single value, or <b>FALSE</b> if Shipment ID not fetched yet
     */
    public function getShipmentId()
    {
        if (isset($this->shipmentId)) {
            return $this->shipmentId;
        } else {
            return false;
        }
    }

}

?>
