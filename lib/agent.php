<?php

namespace Mozaika\Delivery;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery\Services\Manager as MgrDelivery;
use Bitrix\Sale\Delivery\Services\Table as DeliveryServ;
use Mozaika\DaData\API as DaDataAPI;
use Mozaika\DaData\Controller\Location as LocationCtrl;
use Mozaika\Delivery\Service\BaseServiceHandler;
use Seven\Common\LogWrapper;


Loc::loadMessages(__FILE__);

class Agent
{

    /**
     * Run syn pickpoint by every delivery handler
     * @return string
     */
    public static function syncPickpoints() : string
    {
        // Select and filter active unique handlers
        $services = DeliveryServ::getList([
            'select' => ['ID', 'CLASS_NAME'],
            'filter' => [
                '=PARENT_ID' => 0,
                '=CODE' => '',
                '=ACTIVE' => 'Y',
            ],
        ])->fetchAll();

        $services = array_filter($services, function($service){
            return in_array(BaseServiceHandler::class, class_parents($service['CLASS_NAME'])) && $service['CLASS_NAME']::SERVICE_TYPE === BaseServiceHandler::SERVICE_TYPE_PICKPOINT;
        });

        // Shuffle and run each service sync handler
        shuffle($services);

        foreach ($services as $service) {


            try {
                $service['CLASS_NAME']::syncPickpoints(MgrDelivery::getObjectById($service['ID']));
            }
            catch (\Exception $e) {
                //TODO: log exception m.b.
            }
        }

        return '\\' . __METHOD__ . '();';
    }

    /**
     * Run pickpoint rand split
     * @return string
     */
    public static function splitPickpoints() : string
    {
        // Select all active pickpoints
        $points = Models\PickpointTable::getList([
            'select' => ['ID', 'LOC_LAT', 'LOC_LNG'],
            'filter' => ['=ACTIVE' => 'Y'],
            'order' => ['LOC_LAT' => 'ASC', 'LOC_LNG' => 'ASC'],
        ])->fetchAll();

        // Collect neighbors
        $neighbors = [];
        for ($i = count($points); --$i; ) {
            if ($points[$i]['LOC_LAT'] - $points[$i - 1]['LOC_LAT'] > 0.000030) {
                continue;
            }
            for ($j = $i; $j--; ) {
                $distance = sqrt(($points[$i]['LOC_LAT'] - $points[$j]['LOC_LAT']) ** 2 + ($points[$i]['LOC_LNG'] - $points[$j]['LOC_LNG']) ** 2);
                if ($distance < 0.000030) {
                    $neighbors[] = $i;
                    break;
                }
            }
        }

        // Move
        foreach ($neighbors as $i) {
            $points[$i]['LOC_LAT'] += round((rand(0, 10) > 5 ? 1 : -1) * (rand() / getrandmax() * 0.00001 + 0.000025), 6);
            $points[$i]['LOC_LNG'] += round((rand(0, 10) > 5 ? 1 : -1) * (rand() / getrandmax() * 0.00001 + 0.000025), 6);
            Models\PickpointTable::update($points[$i]['ID'], $points[$i]);
        }

        return '\\' . __METHOD__ . '();';
    }

    /**
     * Run calc localities ID for active pickpoints
     * @return string
     */
    public static function calcLocalities() : string
    {
        // Select 10 active pickpoints without locality ID
        $points = Models\PickpointTable::getList([
            'select' => ['ID', "SERVICE_CODE", 'LOC_LAT', 'LOC_LNG', 'LOC_ZIPCODE', 'LOC_KLADR', 'LOC_FIAS', 'LOC_REGION', 'LOC_CITY', 'LOC_ADDRESS'],
            'filter' => [
                '=ACTIVE' => 'Y',
                '=LOCALITY_ID' => 0,
            ],
            'limit' => 25,
        ])->fetchAll();

        // Work each point
        foreach ($points as $point) {

            // Find by FIAS
            if (empty($point['LOCALITY_ID']) && !empty($point['LOC_FIAS'])) {
                sleep(1); // Limits by queries per second on dadata API
                $loc = (new DaDataAPI)->getById($point['LOC_FIAS'])['data']['data'] ?? [];
                $point['LOCALITY_ID'] = self::findLocalityId($loc);
            }

            // Find by KLADR
            if (empty($point['LOCALITY_ID']) && !empty($point['LOC_KLADR'])) {
                sleep(1); // Limits by queries per second on dadata API
                $loc = (new DaDataAPI)->getById($point['LOC_KLADR'])['data']['data'] ?? [];
                $point['LOCALITY_ID'] = self::findLocalityId($loc);
            }

            // Find by postal code
            if (empty($point['LOCALITY_ID']) && !empty($point['LOC_ZIPCODE'])) {
                sleep(1); // Limits by queries per second on dadata API
                $loc = (new DaDataAPI)->getLocalitySuggestions($point['LOC_ZIPCODE'])['suggestions'][0]['data'] ?? [];
                $point['LOCALITY_ID'] = self::findLocalityId($loc);
            }

            // Try find by geocoords
            if (empty($point['LOCALITY_ID']) && !empty($point['LOC_LAT']) && !empty($point['LOC_LNG'])) {
                sleep(1); // Limits by queries per second on dadata API
                $loc = (new DaDataAPI)->getByCoords($point['LOC_LAT'], $point['LOC_LNG'])['data']['data'] ?? [];
                $point['LOCALITY_ID'] = self::findLocalityId($loc);
            }

            // Try find by address variants
            if (empty($point['LOCALITY_ID']) && !empty($point['LOC_ADDRESS'])) {
                $variants = [
                    $point['LOC_ADDRESS'],
                    preg_replace('#^\s*\d{5,6},\s*#', '', $point['LOC_ADDRESS']),
                    implode(', ', array_filter([$point['LOC_ZIPCODE'], $point['LOC_REGION'], $point['LOC_CITY']])),
                    implode(', ', array_filter([$point['LOC_REGION'], $point['LOC_CITY']])),
                ];
                foreach ($variants as $query) {
                    sleep(1); // Limits by queries per second on dadata API
                    $loc = LocationCtrl::getLocalitySuggestionsAction($query)[0]['data'] ?? [];
                    $point['LOCALITY_ID'] = self::findLocalityId($loc);
                    if (!empty($point['LOCALITY_ID'])) {
                        break;
                    }
                }
            }
            // Save with reseved ID for undefined locality and stop infinity tries
            Models\PickpointTable::update($point['ID'], ['LOCALITY_ID' => $point['LOCALITY_ID'] ?: 1]);

        }

        return '\\' . __METHOD__ . '();';
    }


    /**
     * Find locality id by dadata struct
     * @param array $data
     * @return string
     */
    protected static function findLocalityId(array $data) : int
    {

        if (empty($data)) {
            return 0;
        }
        $query = implode(', ', array_unique(array_filter([
            $data['country'],
            $data['region_with_type'],
            $data['area_with_type'],
            $data['city_with_type'],
            $data['settlement_with_type'],
        ])));
        try {
            $result = (int)(LocationCtrl::getLocalityClarifiedAction($query)['id'] ?? 0);
        }
        catch (\Exception $e) {
            $result = 0;
        }
        return $result;
    }
}