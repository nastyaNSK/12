<?php

namespace Mozaika\Delivery\Service;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\ProductTable;
use Bitrix\Sale\Shipment;
use Mozaika\DaData\Controller\Location as LocationCtrl;
use Mozaika\DevEnv\ModuleOption;
use Mozaika\Delivery\Utils;
use Mozaika\Delivery\CalculationResult;
use Seven\Common\LogWrapper;

Loc::loadMessages(__FILE__);

class BaseServiceHandler extends \Bitrix\Sale\Delivery\Services\Base
{

    const SERVICE_TYPE_CUSTOM = 0;
    const SERVICE_TYPE_PICKPOINT = 1;
    const SERVICE_TYPE_COURIER = 2;

    const SERVICE_TYPE = self::SERVICE_TYPE_CUSTOM;
    const SERVICE_CODE = 'empty';
    const PROFILE_CODE = 'empty';
    const WIDGET_HANDLER = '';
    const ADDRESS_NEED = false;
    const LOCALITY = [42, 34, 513, 49, 187, 35, 27, 140, 134, 544, 29, 38, 115, 117, 332, 342, 257, 167, 36, 86, 321,
        89, 145, 1940, 1065, 320, 708, 13850, 333, 482, 88, 933, 136, 290, 788, 10785];

    protected static $isCalculatePriceImmediately = true;
    protected static $canHasProfiles = true;

    protected static $runtimeCacheLocations = [];
    protected static $runtimeCacheOrderParams = [];

    public static $modeForceCompability = false;
    public static $runtimeForcePrice = [
        'serviceId' => 0,
        'price' => 0,
    ];

    public static function getClassTitle()
    {
        return static::locMessage('TITLE');
    }

    public static function getClassDescription()
    {
        return static::locMessage('DESCRIPTION');
    }

    public function isCalculatePriceImmediately()
    {
        return static::$isCalculatePriceImmediately;
    }

    public static function isProfile()
    {
        return static::$isProfile;
    }

    public static function whetherAdminExtraServicesShow()
    {
        return static::$whetherAdminExtraServicesShow;
    }

    public static function canHasProfiles()
    {
        return static::$canHasProfiles;
    }

    protected function calculateConcrete(Shipment $shipment = null) : CalculationResult
    {
        // Check force success result
        $result = $this->getForceCheckResult();
        if ($result->isCompatible()) {
            return $result;
        }

        // Init base result
        $result = $this->getInitialResult();
        if (!static::$isProfile) {
            $result->addError(new \Bitrix\Main\Error(static::locMessage('ERROR_PROFILES_CALC')));
        }
        return $result;
    }

    public function isCompatible(Shipment $shipment)
    {
        return $this->calculateConcrete($shipment)->isCompatible();
    }

    /**
     * List buttons to create new profile
     * @return array
     */
    public function getProfilesList()
    {
        return [static::locMessage('NEW_PROFILE')];
    }

    /**
     * Get merged config values by profile and parent if exist
     * @return array
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\SystemException
     */
    public function getFullConfig() : array
    {
        $config = $values = [];
        if ($this->getParentId()) {
            $values = array_column($this->getParentService()->getConfig(), 'ITEMS');
        }
        $values = array_merge($values, array_column($this->getConfig(), 'ITEMS'));
        foreach ($values as $section) {
            foreach ($section as $key => $item) {
                $value = $item['VALUE'] ?? '';
                if (!isset($config[$key])) {
                    $config[$key] = ModuleOption::get(static::SERVICE_CODE . '_' . $key, ModuleOption::get($key));
                }
                if (strlen($value) > 0) {
                    $config[$key] = $value;
                }
            }
        }
        return $config;
    }

    /**
     * Make def configure data
     * @return array
     */
    protected function getDefConfigStructure(array $useParams = [])
    {
        $config = [
            'API' => [
                'TITLE' => static::locMessage('SETTINGS_API_TITLE'),
                'DESCRIPTION' => static::locMessage('SETTINGS_API_DESCRIPTION'),
                'ITEMS' => [
                    'api_mode' => [
                        'TYPE' => 'ENUM',
                        'NAME' => static::locMessage('SETTINGS_API_MODE'),
                        'DEFAULT' => '',
                        'OPTIONS' => [
                            '' => static::locMessage('VALUE_NO_OVERRIDE'),
                            'dev' => static::locMessage('SETTINGS_API_MODE_DEV'),
                            'prod' => static::locMessage('SETTINGS_API_MODE_PROD'),
                        ],
                    ],
                    'api_client_id' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_API_CLIENT_ID'),
                        'DEFAULT' => '',
                    ],
                    'api_login' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_API_LOGIN'),
                        'DEFAULT' => '',
                    ],
                    'api_password' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_API_PASSWORD'),
                        'DEFAULT' => '',
                    ],
                    'api_key' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_API_KEY'),
                        'DEFAULT' => '',
                    ],
                    'api_token' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_API_TOKEN'),
                        'DEFAULT' => '',
                    ],
                    'api_timeout' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_API_TIMEOUT'),
                        'DEFAULT' => '',
                    ],
                    'api_debug' => [
                        'TYPE' => 'ENUM',
                        'NAME' => static::locMessage('SETTINGS_API_DEBUG'),
                        'DEFAULT' => '',
                        'OPTIONS' => [
                            '' => static::locMessage('VALUE_NO_OVERRIDE'),
                            'dev' => static::locMessage('MAIN_YES'),
                            'prod' => static::locMessage('MAIN_NO'),
                        ],
                    ],
                ],
            ],
            'DEF' => [
                'TITLE' => static::locMessage('SETTINGS_DEF_TITLE'),
                'DESCRIPTION' => static::locMessage('SETTINGS_DEF_DESCRIPTION'),
                'ITEMS' => [
                    'default_dimensions' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_DIMENSIONS'),
                        'DEFAULT' => '',
                    ],
                    'default_weight' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_WEIGHT'),
                        'DEFAULT' => '',
                    ],
                    'default_pack_weight' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_PACK_WEIGHT'),
                        'DEFAULT' => '',
                    ],
                    'default_extra_percent' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_EXTRA_PERCENT'),
                        'DEFAULT' => '',
                    ],
                    'default_extra_price' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_EXTRA_PRICE'),
                        'DEFAULT' => '',
                    ],
                    'default_nearest_radius' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_NEAREST_RADIUS'),
                        'DEFAULT' => '',
                    ],
                ],
            ],
        ];
        if (empty($useParams)) {
            return $config;
        }
        foreach ($config as $sectionKey => $section) {
            foreach ($section['ITEMS'] as $itemKey => $item) {
                if (!in_array($sectionKey . ':' . $itemKey, $useParams)) {
                    unset($config[$sectionKey]['ITEMS'][$itemKey]);
                }
            }
            if (empty($config[$sectionKey]['ITEMS'])) {
                unset($config[$sectionKey]);
            }
        }
        return $config;
    }

    /**
     * Get inherited language message
     * @param string $key
     * @param array $replace
     * @return string
     */
    protected static function locMessage(string $key, array $replace = []) : string
    {
        $prefixes = [
            str_replace('\\', '_', strtoupper(static::class)),
            'MOZAIKA_DELIVERY_EMPTY',
            'MOZAIKA_DELIVERY',
        ];
        foreach ($prefixes as $prefix) {
            $message = Loc::getMessage($prefix . '_' . $key, $replace);
            if (!empty($message)) {
                return $message;
            }
        }
        return Loc::getMessage($key, $replace) ?: $key;
    }

    /**
     * Get prepared requested locations
     * @param Shipment $shipment
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getShipmentLocation(Shipment $shipment) : array
    {
        // Load values from order properties
        $props = $shipment->getOrder()->getPropertyCollection();
        $xref = array_flip(array_map('strtoupper', array_column($props->getArray()['properties'], 'CODE', 'ID')));
        $propValues = $signValues = [];
        $significant = ['LOCALITY_ID', 'PICKPOINT_ID', 'STREET', 'HOUSE'];
        foreach (Utils::getConfigOrderProps() as $key => $code) {
            $propValues[$key] = empty($xref[$code]) ? '' : $props->getItemByOrderPropertyId($xref[$code])->getValue();
            if (in_array($key, $significant)) {
                $signValues[$key] = trim($propValues[$key]);
            }
        }

        // Make hash and check runtime cache
        $runtimeHash = md5(serialize($signValues));
        if (!empty(self::$runtimeCacheLocations[$runtimeHash])) {
            return self::$runtimeCacheLocations[$runtimeHash];
        }

        // Load dadata location and check is require
        $loc = $reg = [];
        $address = '';
        if (!empty($signValues['LOCALITY_ID'])) {
            $reg = LocationCtrl::getLocalityByIdAction($signValues['LOCALITY_ID'], true);
            $loc = LocationCtrl::getAddressClarifiedAction($signValues['LOCALITY_ID'], $signValues['STREET'] ?? '', $signValues['HOUSE'] ?? '');
            $address = ($loc['unrestricted_value'] ?? '') ?: ($reg['unrestricted_value'] ?? '');
            $reg = $reg['data'] ?? [];
            $loc = $loc['data'] ?? $reg;
            Utils::calcPseudoBeltway($loc);
        }

        // Fill search values with normalize
        $loc = array_merge($loc, [
            'locality_id' => $signValues['LOCALITY_ID'] ?? 0,
            'search_global' => LocationCtrl::calcGlobalRegion($loc),
            'search_capital_region' => $loc['beltway_region'] ?? '',
            'search_country' => strtoupper($loc['country_iso_code'] ?? ''),
            'search_beltway_hit' => strtoupper($loc['beltway_hit'] ?? ''),
            'search_beltway_distance' => number_format((float)($loc['beltway_distance'] ?? 0), 2, '.', ''),
            'search_lat' => number_format((float)($loc['geo_lat'] ?? 0), 8, '.', ''),
            'search_lng' => number_format((float)($loc['geo_lon'] ?? 0), 8, '.', ''),
            'search_zipcode' => strtoupper($loc['postal_code'] ?? ''),
            'search_fias' => strtoupper($loc['fias_id'] ?? ''),
            'search_kladr' => strtoupper($loc['kladr_id'] ?? ''),
            'search_okato' => strtoupper($loc['okato'] ?? ''),
            'search_oktmo' => strtoupper($loc['oktmo'] ?? ''),
            'search_reg_lat' => number_format((float)($reg['geo_lat'] ?? 0), 8, '.', ''),
            'search_reg_lng' => number_format((float)($reg['geo_lon'] ?? 0), 8, '.', ''),
            'search_reg_zipcode' => strtoupper($reg['postal_code'] ?? ''),
            'search_reg_fias' => strtoupper($reg['fias_id'] ?? ''),
            'search_reg_kladr' => strtoupper($reg['kladr_id'] ?? ''),
            'search_reg_okato' => strtoupper($reg['okato'] ?? ''),
            'search_reg_oktmo' => strtoupper($reg['oktmo'] ?? ''),
            'search_address' => $address,
            'search_ppoint_id' => (int)($signValues['PICKPOINT_ID']),
        ]);

        // Calc hash of location data
        $hash = array_filter($loc, function($field){
            return substr($field, 0, 7) == 'search_';
        }, ARRAY_FILTER_USE_KEY);
        ksort($hash);
        $loc['search_hash'] = md5(serialize($hash));

        // Runtime cache location and return
        self::$runtimeCacheLocations[$runtimeHash] = $loc;
        return $loc;
    }

    /**
     * Load default pack weight, prod weight and dimensions
     * @return array
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\SystemException
     */
    protected function loadDefaultsProdParams() : array
    {
        // Load default values
        $defaults = [
            'dimensions' => ModuleOption::get('default_dimensions', '5.00;20.00;30.00'),
            'weight' => ModuleOption::get('default_weight', 0.2),
            'pack_weight' => ModuleOption::get('default_pack_weight', 0.4),
            'k_dimensions' => ModuleOption::get('k_dimensions', "1.00;1.00;1.00"),
            'default_pack_weight' => ModuleOption::get('default_pack_weight', "0.4"),
        ];

        // Overwrite defaults if set
        foreach ($this->getFullConfig() as $items) {
            foreach ($defaults as $key => $val) {
                if (!empty($items['default_' . $key])) {
                    $defaults[$key] = $items['default_' . $key];
                }
            }
        }

        // Format and check
        $defaults['dimensions'] = explode(';', $defaults['dimensions']);
        $defaults['dimensions'][0] = (float)($defaults['dimensions'][0] ?? 0.1);
        $defaults['dimensions'][1] = (float)($defaults['dimensions'][1] ?? 0.1);
        $defaults['dimensions'][2] = (float)($defaults['dimensions'][2] ?? 0.1);
        sort($defaults['dimensions']);
        $defaults['weight'] = max(0.05, (float)$defaults['weight']);
        $defaults['pack_weight'] = max(0.01, (float)$defaults['pack_weight']);

        return $defaults;
    }

    /**
     * Get order weight, dimension and price
     * @param Shipment $shipment
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getShipmentOrderParams (Shipment $shipment) : array
    {
        // Init
        $defaults = $this->loadDefaultsProdParams();
        $dimensions = [0, 0, 0]; // 2 cm on every dimension to pack
        $weight = $defaults['pack_weight'];
        $price = 0;
        $basketItems = $shipment->getOrder()->getBasket()->getOrderableItems();

        // Load product avail quantities
        $products = [];
        foreach ($basketItems as $item) {
            $products[] = $item->getProductId();
        }
        $quantities = ProductTable::getList([
            'select' => ['ID', 'QUANTITY'],
            'filter' => [
                'ID' => array_unique($products),
            ],
        ])->fetchAll();
        $quantities = array_column($quantities, 'QUANTITY', 'ID');

        $isDimensions = true;

        // Calc values
        foreach ($basketItems as $item) {
            $avail = $quantities[$item->getProductId()] ?? 0;
            if (empty($avail)) {
                continue;
            }

            $dim = Utils::parseDimensions($item->getField('DIMENSIONS'), $defaults['dimensions']);

            if ($isDimensions) {
                $isDimensions = !empty($dim[0]) && !empty($dim[1]) && !empty($dim[2]);
            }

            $dimensions[0] += $dim[0] * $item->getQuantity();
            $dimensions[1] = max($dimensions[1], $dim[1]);
            $dimensions[2] = max($dimensions[2], $dim[2]);
            $weight += max($item->getWeight() / 1000 ?: $defaults['weight'], 0.03) * $item->getQuantity();
            $price += $item->getPrice() * $item->getQuantity();
        }
        $k = explode(';', $defaults['k_dimensions']);

        $dimensions[0] += $k[0];
        $dimensions[1] += $k[2];
        $dimensions[2] += $k[2];

        arsort($dimensions);
        $dimensions = array_values($dimensions);

        // Formatted and return
        $result = [
            'weight' => number_format($weight, 3, '.', '') + number_format($defaults["default_pack_weight"], 3, '.', ''),
            'price' => number_format($price, 2, '.', ''),
            'dimensions' => array_map(function($item){
                return number_format($item, 2, '.', '');
            }, $dimensions),
            'isDimensions' => $isDimensions
        ];

        if ($shipment->getOrder()->getPersonTypeId() == 1) {
            $property = $shipment->getOrder()->getPropertyCollection();
            $somePropValue = $property->getItemByOrderPropertyId(153);
            if ($somePropValue->getValue()) {
                $result['pickpoint'] = $somePropValue->getValue();
            }

            $locPropValue = $property->getItemByOrderPropertyId(163);
            if ($locPropValue->getValue()) {
                $result['localityId'] = $locPropValue->getValue();
            }
        }

        $result['hash'] = md5(serialize($result));
        $result['paymentId'] = $shipment->getOrder()->getField("PAY_SYSTEM_ID");

        return $result;
    }

    /**
     * Get initial calc result object
     * @return CalculationResult
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getInitialResult () : CalculationResult
    {
        $result = new CalculationResult();
        $result->setRealServiceId($this->getId());
        $result->setWidget(static::WIDGET_HANDLER);
        $result->setAddressNeed(static::ADDRESS_NEED);
        $result->setGroupTitle($this->getFullConfig()['group_title'] ?? '');
        return $result;
    }

    /**
     * Get checked force calc result object
     * @return CalculationResult
     */
    protected function getForceCheckResult () : CalculationResult
    {
        $result = new CalculationResult();
        $result->setDeliveryPrice(4);
        if (!static::$modeForceCompability) {
            $result->setIncompatible();
        }
        return $result;
    }

}