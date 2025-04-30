<?php
namespace Mozaika\Delivery\Service;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Error;
use Bitrix\Main\Data\Cache;
use Bitrix\Sale\Delivery\Services\Table as DeliveryServ;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryMgr;
use Bitrix\Sale\Shipment;
use Mozaika\Delivery\CalculationResult;
use Seven\Common\LogWrapper;

Loc::loadMessages(__FILE__);

class UniversalCourierHandler extends BaseServiceHandler
{
    const SERVICE_TYPE = self::SERVICE_TYPE_COURIER;
    const SERVICE_CODE = 'universal_courier';
    const WIDGET_HANDLER = 'inkad';

    protected static $canHasProfiles = false;
    protected $injectHandlers;

    /**
     * UniversalCourierHandler constructor.
     * @param array $initParams
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
        // Load avaliable handlers
        $handlers = array_filter(array_map(function($handler){
            $type = defined($handler . '::SERVICE_TYPE') ? $handler::SERVICE_TYPE : 0;
            $handler = trim($handler, '\\');
            return ($type == BaseServiceHandler::SERVICE_TYPE_COURIER) && ($handler != self::class) ? $handler : '';
        }, DeliveryMgr::getHandlersList()));

        // Load avaliable services
        $services = DeliveryServ::getList([
            'filter' => [
                '!ID' => $this->getId(),
                '=CODE' => '',
                '=ACTIVE' => 'Y',
            ],
        ])->fetchAll();
        $this->injectHandlers = array_filter($services, function($service) use ($handlers) {
            return in_array(trim($service['CLASS_NAME'], '\\'), $handlers);
        });
    }

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
        $loc = $this->getShipmentLocation($shipment);
        if ($loc['search_country'] !== 'RU') {
            $result = $this->getInitialResult();
            $result->addError(new Error($this->locMessage('ERROR_ONLY_RUSSIA')));
            $result->setIncompatible();
            return $result;
        }

        // Cache init
        $order = $this->getShipmentOrderParams($shipment);
        $cacheId = $loc['search_hash'] . $order['hash'];
        $cache = Cache::createInstance();
        if ($cache->initCache(86400, $cacheId, 'mozaika.delivery/ucourier')) {
            return $cache->getVars();
        }

        // Check priority service use
        $conf = $this->getFullConfig();
        $handlers = $this->getInjectedHandlers();
        $indexById = array_flip(array_column($handlers, 'ID'));
        if ((!empty($loc['search_capital_region']) || in_array($loc["locality_id"], self::LOCALITY)) && isset($indexById[$conf['priority_service']])) {
            $result = DeliveryMgr::calculateDeliveryPrice($shipment, $conf['priority_service']);
            $result->setWidget(static::WIDGET_HANDLER);
            if ($result->isSuccess()) {
                return $result;
            }
        }

        // Calc another services
        $results = [];
        foreach ($handlers as $handler) {
            $results[$handler['ID']] = DeliveryMgr::calculateDeliveryPrice($shipment, $handler['ID']);
            $results[$handler['ID']]->setTarget(90);
        }
        $results = array_filter($results, function($result){
            return $result->isSuccess() && ($result->getPrice() > 0);
        });
        if (empty($results)) {
            $result = $this->getInitialResult();
            $result->addError(new Error($this->locMessage('ERROR_NO_SERVICES')));
            $result->setIncompatible();
            return $result;
        }

        // Find cheapest service
        uasort($results, function($a, $b){
            return $a->getPrice() <=> $b->getPrice();
        });
        $result = array_shift($results);
        $result->setWidget(static::WIDGET_HANDLER);
        \CEventLog::add([
            'SEVERITY' => 'DEBUG',
            'AUDIT_TYPE_ID' => 'MZK_DELIVERY',
            'MODULE_ID' => 'mozaika.delivery',
            'ITEM_ID' => 'cheapest_result',
            'DESCRIPTION' => json_encode([
                'Success' => $result->isSuccess() ? 'Y' : 'N',
                'DeliveryPrice' => $result->getDeliveryPrice(),
                'Description' => $result->getDescription(),
                'PeriodDescription' => $result->getPeriodDescription(),
                'PeriodFrom' => $result->getPeriodFrom(),
                'PeriodTo' => $result->getPeriodTo(),
                'Price' => $result->getPrice(),
                'ErrorMessages' => $result->getErrorMessages(),
                'HandlerID' => $result->getTmpData(),
            ], JSON_UNESCAPED_UNICODE + JSON_PRETTY_PRINT),
        ]);
        $cache->startDataCache();
        $cache->endDataCache($result);
        return $result;
    }

    /**
     * Check support this delivery method by selected location
     * @param Shipment $shipment
     * @return bool
     * @throws \Bitrix\Main\ArgumentNullException
     */
    public function isCompatible(Shipment $shipment)
    {
        return $this->calculateConcrete($shipment)->isCompatible();
    }

    /**
     * Get active injected handlers
     * @return array
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\SystemException
     */
    public function getInjectedHandlers() : array
    {
        $config = $this->getFullConfig();
        return array_values(array_filter($this->injectHandlers, function($handler) use ($config) {
            return ($config['service_' . $handler['ID']] ?? 'Y') === 'Y';
        }));
    }

    /**
     * Make configure data
     * @return array
     */
    protected function getConfigStructure()
    {
        // Make settings by each service
        $services = [];
        foreach ($this->injectHandlers as $service) {
            $services['service_' . $service['ID']] = [
                'TYPE' => 'ENUM',
                'NAME' => $service['NAME'] . ' /' . $service['ID'] . '/',
                'DEFAULT' => 'Y',
                'OPTIONS' => [
                    'Y' => static::locMessage('SETTINGS_SERV_ENABLED'),
                    'N' => static::locMessage('SETTINGS_SERV_DISABLED'),
                ],
            ];
        }

        // Add selector priority service
        $services['priority_service'] = [
            'TYPE' => 'ENUM',
            'NAME' => static::locMessage('SETTINGS_TARIFF_PRIORITY_SERVICE'),
            'OPTIONS' => array_column($this->injectHandlers, 'NAME', 'ID'),
        ];

        // Make settings struct
        return [
            'SERV' => [
                'TITLE' => static::locMessage('SETTINGS_SERV_TITLE'),
                'DESCRIPTION' => static::locMessage('SETTINGS_SERV_DESCRIPTION'),
                'ITEMS' => $services,
            ],
        ];
    }
}