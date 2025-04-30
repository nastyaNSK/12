<?php

namespace Mozaika\Delivery;

use Bitrix\Main\Localization\Loc;
use Mozaika\DevEnv\ModuleOption;
use Seven\Common\LogWrapper;

Loc::loadMessages(__FILE__);

class Utils
{

    const EARTH_RADIUS = 6372795;
    const GLOBALS_FIAS = [
        '0C5B2444-70A0-4932-980C-B4DC0D3F02B5' => 'msk',
        'C2DEB16A-0330-4F05-821F-1D09C93331E6' => 'spb',
    ];
    const CAPITALS_REGION_FIAS = [
        '0C5B2444-70A0-4932-980C-B4DC0D3F02B5' => 'msk',
        '29251DCF-00A1-4E34-98D4-5C47484A36D4' => 'msk',
        'C2DEB16A-0330-4F05-821F-1D09C93331E6' => 'spb',
        '6D1EBB35-70C6-4129-BD55-DA3969658F5D' => 'spb',
    ];
    const BELTWAYS = [
        'msk' => [
            'road' => 'MKAD',
            'center' => [55.757625, 37.621990],
            'radius' => 19,
        ],
        'spb' => [
            'road' => 'KAD',
            'center' => [59.936124, 30.116510],
            'radius' => 13,
        ],
    ];

    /**
     * Calc pseudo beltway by geo coordinates
     * @param array $loc
     */
    public static function calcPseudoBeltway (array &$loc) : void
    {
        // Check capital region
        $loc['beltway_capital'] = self::GLOBALS_FIAS[strtoupper($loc['fias_id'] ?? '')] ?? '';
        $loc['beltway_region'] = self::CAPITALS_REGION_FIAS[strtoupper($loc['region_fias_id'] ?? '')] ?? '';

        // Check require fields and already set beltway
        $lat = (float)($loc['geo_lat'] ?? 0);
        $lng = (float)($loc['geo_lon'] ?? 0);
        if (empty($lat) || empty($lng) || !empty($loc['beltway_hit'])) {
            return;
        }

        // Check and set beltway
        $beltway = self::BELTWAYS[$loc['beltway_region']] ?? [];
        if (empty($beltway)) {
            return;
        }
        $distance = self::calcGeoDistance($beltway['center'], [$lat, $lng]);
        $loc['beltway_hit'] = ($distance > $beltway['radius'] ? 'OUT' : 'IN') . '_' . $beltway['road'];
        $loc['beltway_distance'] = max(0, round($distance - $beltway['radius']));
    }

    /**
     * Calc distance between two geo points in km by geocoords
     * @param array $geo1
     * @param array $geo2
     * @return float
     */
    public static function calcGeoDistance(array $geo1, array $geo2)
    {
        $lat1 = deg2rad($geo1[0]);
        $lat2 = deg2rad($geo2[0]);
        $lon1 = deg2rad($geo1[1]);
        $lon2 = deg2rad($geo2[1]);

        $cl1 = cos($lat1);
        $cl2 = cos($lat2);
        $sl1 = sin($lat1);
        $sl2 = sin($lat2);
        $delta = $lon2 - $lon1;
        $cdelta = cos($delta);
        $sdelta = sin($delta);

        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

        $ad = atan2($y, $x);
        $dist = $ad * self::EARTH_RADIUS;

        return $dist / 1000;
    }

    /**
     * Parse dimensions array
     * @param $dimensions
     * @param array $default
     * @return array
     */
    public static function parseDimensions($dimensions, array $default) : array
    {
        for ($i = 3; $i--; ) {
            if (!is_array($dimensions)) {
                $dimensions = @unserialize($dimensions);
            }
        }
        if (!is_array($dimensions)) {
            $dimensions = $default;
        }
        else {
            $dimensions = array_values(array_map(function($item){
                return max($item / 10, 0.01);
            }, $dimensions));
        }
        sort($dimensions);
        for ($i = 3; $i--; ) {
            $dimensions[$i] = $dimensions[$i] > 0.01 ? $dimensions[$i] : 0;
        }
        sort($dimensions);
        return $dimensions;
    }

    /**
     * Get references options from module options
     * @return array
     */
    public static function getConfigOrderProps() : array
    {
        return array_map('strtoupper', [
            'LOCALITY_ID' => ModuleOption::get('order_prop_locality', 'LOCALITY_ID'),
            'STREET' => ModuleOption::get('order_prop_street', 'STREET'),
            'HOUSE' => ModuleOption::get('order_prop_house', 'HOUSE'),
            'BUILDING' => ModuleOption::get('order_prop_building', 'BUILDING'),
            'PICKPOINT_ID' => ModuleOption::get('order_prop_pickpoint', 'PICKPOINT_ID'),
            'GROUP_DELIVERY_ID' => ModuleOption::get('order_prop_delivery_id', 'GROUP_DELIVERY_ID'),
            'PICKPOINT_DATA' => ModuleOption::get('order_prop_pickpoint_data', 'PICKPOINT_DATA'),
            'PARCEL_TYPE' => ModuleOption::get('order_prop_parcel_type', 'PARCEL_TYPE'),
        ]);
    }

}