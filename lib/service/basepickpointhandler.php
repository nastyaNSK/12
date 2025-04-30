<?php

namespace Mozaika\Delivery\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryMgr;
use Bitrix\Sale\Shipment;
use Mozaika\Delivery\Utils;
use Mozaika\Delivery\Models\PickpointTable;
use Mozaika\Delivery\Models\ZoneTable;
use Mozaika\Delivery\CalculationResult;
use Seven\Common\LogWrapper;

Loc::loadMessages(__FILE__);

class BasePickPointHandler extends BaseServiceHandler
{

    const SERVICE_TYPE = self::SERVICE_TYPE_PICKPOINT;
    const WIDGET_HANDLER = 'pickpoint';
    const CHECK_VOLUME_LOGIC = 'size';

    protected static $canHasProfiles = false;

    protected $service;

    /**
     * Calc delivery cost
     * @param Shipment|null $shipment
     * @return CalculationResult
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function calculateConcrete(Shipment $shipment = null) : CalculationResult
    {
        // Check force success result
        $result = $this->getForceCheckResult();
        if ($result->isCompatible()) {
            return $result;
        }

        // Init and fast check
        $result = $this->getInitialResult();
        $loc = $this->getShipmentLocation($shipment);
        $pointId = (int)($loc['search_ppoint_id'] ?? 0);

        // Load nearest and locality points
        $points = $this->findNearestPoints($loc);
        foreach ($this->findLocalityPoints($loc) as $id => $point) {
            $points[$id] = $point;
        }
        $points = $this->calcPoints($points, $shipment);

        // Calc prices
        $pointsInCity = array_filter($points, function($point){
            return $point['FIND_TYPE'] === 'LOCALITY';
        });
        $prices = array_values(array_filter(array_unique(array_column($pointsInCity ?: $points, 'PRICE'))));
        $prices = sizeof($prices) > 0 ? $prices : [0];
        $priceLow = min($prices) ?: 0;
        $priceHigh = max($prices) ?: $priceLow;

        // Check price
        if (empty($priceLow)) {
            $result->addError(new Error(static::locMessage('ERROR_NO_PICKPOINTS')));
            return $result;
        }

        // Price used for calc saled delivery
        $result->setPriceSale(empty($pointsInCity) ? -1 : $priceLow);

        // Calc selected point
        if (!empty($pointId)) {
            if (empty($points[$pointId])) {
                $filter = [
                    '=ID' => $pointId,
                    '=SERVICE_CODE' => static::SERVICE_CODE,
                ];
                $points = array_map(function($point){
                    $point['AVAIL'] = true;
                    $point['DISTANCE'] = 0;
                    $point['FIND_TYPE'] = 'SELECTED';
                    return $point;
                }, $this->getPointsByFilter($filter));
                $points = $this->calcPoints($points, $shipment);
            }
            if (!empty($points[$pointId])) {
                $point = $points[$pointId];
                $result->setPropValue('PICKPOINT_ID', $pointId);
                $result->setPropValue('PICKPOINT_DATA', $point['SERVICE_ID']);
                if (!empty($point['ERRORS'])) {
                    foreach ($point['ERRORS'] as $error) {
                        $result->addError(new Error($error));
                    }
                    return $result;
                }
                if (empty($point['PRICE'])) {
                    $result->addError(new Error(static::locMessage('ERROR_NO_PICKPOINTS')));
                    return $result;
                }

                $result->setDeliveryPrice($point['PRICE']);
                $result->setCanPostPay($point['CAN_CASH'] == 'Y' || $point['CAN_CARD'] == 'Y');
                if (!empty($point['TRANSIT'])) {
                    $result->setPeriodFrom($point['TRANSIT']);
                    $result->setPeriodTo($point['TRANSIT']);
                }
                return $result;
            }
        }

        // Calc distances and dynamic description
        $distances = array_column($points, 'DISTANCE');
        $arrDis = array_filter($distances, function($item){
            return $item === 0;
        });

        $count = count($arrDis);
        $distance = $count > 0 ? min($arrDis): 0;
        $tpls = [
            '#POINTS#' => $count,
            '#DISTANCE#' => $distance,
            '#DISTANCE0#' => number_format($distance, 0, '.', ''),
            '#DISTANCE1#' => number_format($distance, 1, '.', ''),
            '#DISTANCE2#' => number_format($distance, 2, '.', ''),
            '#DISTANCE3#' => number_format($distance, 3, '.', ''),
        ];
        $result->setPointsLocality($count);
        $result->setPointNearest($distance);

        $result->setDynamicDescription(static::locMessage($count > 0 ? 'POINTS_LOCALITY_COUNT' : 'NEAREST_POINT_DISTANCE', $tpls));

        $result->setDynamicDescription2(static::locMessage($count > 0 ? 'POINTS_LOCALITY_COUNT2' : 'NEAREST_POINT_DISTANCE', $tpls));

        // Set can post pay
        $canPostPay = array_merge(array_column($points, 'CAN_CASH'), array_column($points, 'CAN_CARD'));
        $result->setCanPostPay(in_array('Y', $canPostPay));

        // Calc and set cost
        $result->setPriceFrom($priceLow);
        $result->setPriceTo($priceHigh);
        $result->setDeliveryPrice($priceLow);

        // Calc and set delivery period
        $periods = array_values(array_filter(array_unique(array_column($pointsInCity ?: $points, 'TRANSIT'))));
        $periods = sizeof($periods) > 0 ? $periods : [0];

        $periodLow = min($periods) ?: 0;
        if (!empty($periodLow)) {
            $result->setPeriodFrom($periodLow);
            $periodHigh = max($periods) ?: $periodLow;
            if ($periodHigh > $periodLow) {
                $result->setPeriodTo($periodHigh);
            }
        }

        return $result;
    }

    /**
     * Get points list
     * @param Shipment $shipment
     * @param bool $onlyAvail
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getPointsList(Shipment $shipment, bool $onlyAvail = true) : array
    {
        // Init and fast check
        $loc = $this->getShipmentLocation($shipment);
        $points = $this->findAllPoints($loc);
        if (!$onlyAvail) {
            $full = $this->findCountryPoints($loc);
            foreach ($full as $point) {
                if (!isset($points[$point['ID']])) {
                    $points[$point['ID']] = $point;
                }
            }
        }
        $path = str_replace(Application::getDocumentRoot(), '', dirname(dirname(__DIR__)) . '/assets/img/map-markers/');
        $points = array_map(function ($point) use ($path) {
            $point['MAP_MARKER_SRC'] = $path . $point['MAP_MARKER'] . '.svg';
            $point['PRICE'] = $point['PRICE'] ?? 0;
            $point['TRANSIT'] = $point['TRANSIT'] ?? 0;
            $point['ERRORS'] = $point['ERRORS'] ?? [];
            /* Disable error for points outbounds
            if (!$point['AVAIL']) {
                $point['ERRORS'][] = static::locMessage('ERROR_POINT_OUT_OF_LOCALITY');
            }
            */
            return $point;
        }, $points);

        $param = $this->getShipmentOrderParams($shipment);

        // Если не указан хоть один вес не выводим СД Сбер и пикпоинт
        if (empty($param["isDimensions"])) {
            $points = array_filter($points, function ($item) use ($param) {
                if ($item["SERVICE_CODE"] === "sberlogistics") {
                    return false;
                } else {
                    return true;
                }
            });
        }

        // Алгоритм вывода точек, подходящих для габаритов текущей корзины
        if (!empty($param["isDimensions"])) {
            $points = array_filter($points, function ($item) use ($param) {
                if ($item["SERVICE_CODE"] == "pickpoint" || $item["SERVICE_CODE"] == "sberlogistics") {
                    $isDimensions = array_filter($item["MAX_DIMENSIONS"], function ($i) {
                        return !empty($i);
                    });

                    if (sizeof($isDimensions) > 0) {
                        sort($item["MAX_DIMENSIONS"]);
                    } else {
                        return false;
                    }

                    if (
                        $item["MAX_DIMENSIONS"][0] < $param["dimensions"][0] ||
                        $item["MAX_DIMENSIONS"][1] < $param["dimensions"][1] ||
                        $item["MAX_DIMENSIONS"][2] < $param["dimensions"][2]
                    ) {
                        return false;
                    } else {
                        return true;
                    }
                }
                return true;
            });
        }

        $points = array_filter($points, function ($item) use ($param) {
            return ($item["MAX_WEIGHT"] == 0 || $item["MAX_WEIGHT"] > $param['weight']);
        });

        return $points;
    }

    /**
     * Find points by locality and by geo radius
     * @param array $loc
     * @return array
     */
    protected function findAllPoints(array $loc) : array
    {
        //$points = $this->findNearestPoints($loc); TODO: temporary calc all points
        $points = $this->findCountryPoints($loc);
        foreach ($this->findLocalityPoints($loc) as $id => $point) {
            $points[$id] = $point;
        }
        return $points;
    }

    /**
     * Find points by locality (zip, kladr, fias, okato or oktmo codes)
     * @param array $loc
     * @return array
     */
    protected function findLocalityPoints(array $loc) : array
    {
        // Fast check
        if (empty($loc['search_country'])) {
            return [];
        }

        // Make filter
        $filter = [];
        if (!empty($loc['locality_id'])) {
            $filter['=LOCALITY_ID'] = $loc['locality_id'];
        }
        foreach (['fias', 'kladr', 'okato', 'oktmo'] as $field) {
            $values = array_unique(array_filter([$loc['search_' . $field], $loc['search_reg_' . $field]]));
            if (!empty($values)) {
                $filter['LOC_' . strtoupper($field)] = $values;
            }
        }
        if (empty($filter)) {
            return [];
        }
        if (count($filter) > 1) {
            $filter = [array_merge(['LOGIC' => 'OR'], $filter)];
        }
        $filter = array_merge([
            '=ACTIVE' => 'Y',
            '=SERVICE_CODE' => static::SERVICE_CODE,
            '=LOC_COUNTRY' => $loc['search_country'],
        ], $filter);

        // Load points
        return array_map(function($point){
            $point['AVAIL'] = true;
            $point['DISTANCE'] = 0;
            $point['FIND_TYPE'] = 'LOCALITY';
            return $point;
        }, $this->getPointsByFilter($filter));
    }

    /**
     * Find points in radius by geo coordinates
     * @param array $loc
     * @param float $radius
     * @return array
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\SystemException
     */
    protected function findNearestPoints(array $loc, float $radius = 0.0) : array
    {
        // Load and check location
        if (empty($loc['search_lat']) || empty($loc['search_lng'])) {
            return [];
        }

        // Init radius
        if ($radius < 0.5) {
            $radius = (float)($this->getFullConfig()['default_nearest_radius'] ?? 50);
        }

        // Calc geo rectangle and make filter
        $kmInGrad = 2 * Utils::EARTH_RADIUS * M_PI / 360000;
        $deltaLat = $radius / $kmInGrad;
        $deltaLng = $radius / cos(deg2rad($loc['search_lat'])) / $kmInGrad;
        //TODO: пока делаем только для России\СНГ, долгота не переваливает за 180 градусов, не рассматриваем дополнительные полигоны
        $filter = [
            '=ACTIVE' => 'Y',
            '=SERVICE_CODE' => static::SERVICE_CODE,
            '=LOC_COUNTRY' => $loc['search_country'],
            '>=LOC_LAT' => $loc['search_lat'] - $deltaLat,
            '<=LOC_LAT' => $loc['search_lat'] + $deltaLat,
            '>=LOC_LNG' => $loc['search_lng'] - $deltaLng,
            '<=LOC_LNG' => $loc['search_lng'] + $deltaLng,
        ];

        // Load points
        $points = array_map(function($point) use ($loc) {
            $geo1 = [$point['LOC_LAT'], $point['LOC_LNG']];
            $geo2 = [$loc['search_lat'], $loc['search_lng']];
            $point['AVAIL'] = true;
            $point['DISTANCE'] = round(Utils::calcGeoDistance($geo1, $geo2), 3);
            $point['FIND_TYPE'] = 'NEAREST';
            return $point;
        }, $this->getPointsByFilter($filter));

        // Calc distance, filter and sort
        $points = array_filter($points, function ($point) use ($radius) {
            return $point['DISTANCE'] < $radius;
        });
        uasort($points, function($a, $b){
            return $a['DISTANCE'] <=> $b['DISTANCE'];
        });

        return $points;
    }

    /**
     * Find all points by country
     * @param array $loc
     * @return array
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\SystemException
     */
    protected function findCountryPoints(array $loc) : array
    {
        // Fast check
        if (empty($loc['search_country'])) {
            return [];
        }

        // Temporary select points around 100km radius only
        $points = array_map(function($point) {
            $point['AVAIL'] = false;
            return $point;
        }, $this->findNearestPoints($loc, 100));

        return $points;
    }

    /**
     * Calc multi points
     * @param array $points
     * @param Shipment $shipment
     * @return array
     */
    public function calcPoints(array $points, Shipment $shipment) : array
    {
        if (empty($points)) {
            return [];
        }

        // Load zones
        $zones = array_unique(array_column($points, 'RATE_ZONE'));

        if (!empty($zones)) {
            $zones = array_column(ZoneTable::getList([
                'select' => ['ID', 'RATES'],
                'filter' => ['ID' => $zones],
            ])->fetchAll(), 'RATES', 'ID');
        }

        // Init params
        $conf = $this->getFullConfig();
        $order = $this->getShipmentOrderParams($shipment);
        $extraPercent = (float)($conf['default_extra_percent'] ?? 0);
        $extraPrice = (float)($conf['default_extra_price'] ?? 0);

        // Calc each
        foreach ($points as &$point) {
            // Check by params and add ERRORS
            $point['PRICE'] = 0;
            $point['ERRORS'] = [];

            // Get point price and transit days
            $zone = $zones[$point['RATE_ZONE']] ?? [];
            foreach ($zone as $step => $price) {
                if ($order['weight'] < (float)$step) {
                    break;
                }
                $point['PRICE'] = $price;
            }
            $point['TRANSIT'] = (int)($point['TRANSIT_DAYS'] ?? 0);
            if (!empty($point['TRANSIT'])) {
                $point['TRANSIT'] += 1;
            }

            // Price additionals
            if (!empty($point['PRICE'])) {
                //TODO: учитывать наложенный платеж
                // Extra price
                $point['PRICE'] += $order['price'] * $extraPercent / 100;
                $point['PRICE'] += $extraPrice;
                if ($point["SERVICE_CODE"] == "boxberry") {
                    $point['PRICE'] += 80;
                }

            }
            $point['PRICE'] = round($point['PRICE']);
        }
        unset($point);
        return $points;
    }

    /**
     * Run sync pickpoints list
     * @param array $filter
     */
    protected function getPointsByFilter(array $filter) : array
    {
        $points = [];
        $list = PickpointTable::getList([
            'select' => ['ID', 'SERVICE_CODE', 'SERVICE_ID', 'MAP_MARKER', 'NAME', 'LOCALITY_ID', 'LOC_LAT', 'LOC_LNG', 'CAN_CASH', 'CAN_CARD', 'RATE_ZONE', 'TRANSIT_DAYS', 'MAX_WEIGHT', 'MAX_DIMENSIONS', 'LOC_KLADR'],
            'filter' => $filter,
            'cache' => ['ttl' => 3600],
        ]);
        while ($point = $list->fetch()) {
            $point['DELIVERY_ID'] = $this->getId();
            $point['MAP_LEGEND'] = static::locMessage('MAP_LEGEND_' . strtoupper($point['MAP_MARKER']));
            $points[$point['ID']] = $point;
        }
        return $points;
    }

    /**
     * Run sync pickpoints list
     */
    public static function syncPickpoints(BasePickPointHandler $handler) : void
    {
        $class = array_reverse(explode('\\', static::class));
        $class[0] = 'PickPointSyncer';
        $class = implode('\\', array_reverse($class));
        (new $class($handler))->run();
    }

}
