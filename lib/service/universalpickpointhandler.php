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

class UniversalPickPointHandler extends BasePickPointHandler
{

    const SERVICE_CODE = 'universal_pickpoint';

    protected $injectHandlers;

    /**
     * UniversalPickPointHandler constructor.
     * @param array $initParams
     */
    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
        // Load avaliable handlers
        $handlers = array_filter(array_map(function($handler){
            $type = defined($handler . '::SERVICE_TYPE') ? $handler::SERVICE_TYPE : 0;
            $handler = trim($handler, '\\');
            return ($type == BaseServiceHandler::SERVICE_TYPE_PICKPOINT) && ($handler != self::class) ? $handler : '';
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
     */
    protected function calculateConcrete(Shipment $shipment = null) : CalculationResult
    {
        // Check force success result
        $result = $this->getForceCheckResult();
        if ($result->isCompatible()) {
            return $result;
        }

        // Init
        $loc = $this->getShipmentLocation($shipment);
        $order = $this->getShipmentOrderParams($shipment);
        $cacheId = md5($loc['search_hash'] . $order['hash']);
        $cache = Cache::createInstance();
        if ($cache->initCache(86400, $cacheId, 'mozaika.delivery/upickpoint')) {
            return $cache->getVars();
        }

        // Calc each service
        $handlers = $this->getInjectedHandlers();
        $results = [];
        foreach ($handlers as $handler) {
            $results[$handler['ID']] = DeliveryMgr::calculateDeliveryPrice($shipment, $handler['ID']);
        }
        $results = array_filter($results, function($result){
            return $result->getPrice() > 0;
        });
        if (empty($results)) {
            $result = $this->getInitialResult();
            $result->addError(new Error($this->locMessage('ERROR_NO_SERVICES')));
            $result->setIncompatible();
            return $result;
        }

        // If selected point
        if (!empty($loc['search_ppoint_id']) && !empty($results)) {
            $avails = array_filter($results, function($result) use ($loc){
                $pointId = (int)$result->getPropsValue('PICKPOINT_ID');
                return $pointId == $loc['search_ppoint_id'];
            });
            $avail = array_shift($avails);
            if (!empty($avail)) {
                $result = $this->getInitialResult();
                foreach ($avail->getErrors() as $error) {
                    $result->addError($error);
                }
                $result->setDynamicDescription(DeliveryMgr::getObjectById($avail->getRealServiceId())->getName());
                $result->setRealServiceId($avail->getRealServiceId());
                $result->setDeliveryPrice($avail->getDeliveryPrice());
                $result->setPeriodFrom($avail->getPeriodFrom());
                $result->setPeriodTo($avail->getPeriodTo());
                $result->setCanPostPay($avail->getCanPostPay());
                $result->setPropsValues($avail->getPropsValues());
            }
            $cache->startDataCache();
            $cache->endDataCache($result);
            return $result;
        }

        // Get group info
        $canPostPay = false;
        $pricesValue = $pricesSale = $pricesLow = $pricesHigh = $periodsLow = $periodsHigh = $pointsCount = $pointsDistances = [];
        foreach ($results as $result) {
            $pricesValue[] = $result->getDeliveryPrice();
            $pricesSale[] = $result->getPriceSale();
            $pricesLow[] = $result->getPriceFrom();
            $pricesHigh[] = $result->getPriceTo();
            $periodsLow[] = $result->getPeriodFrom();
            $periodsHigh[] = $result->getPeriodTo();
            $pointsCount[] = $result->getPointsLocality();
            $pointsDistances[] = $result->getPointNearest();
            $canPostPay = $canPostPay || $result->getCanPostPay();
        }

        $result = $this->getInitialResult();
        $result->setDeliveryPrice(min($pricesValue) ?: 0);
        $result->setPriceSale(min($pricesSale) ?: 0);
        $result->setPriceFrom(min($pricesLow) ?: 0);
        $result->setPriceTo(max($pricesHigh) ?: 0);
        $result->setPeriodFrom(min($periodsLow) ?: 0);
        $result->setPeriodTo(max($periodsHigh) ?: 0);
        $result->setPointsLocality(array_sum($pointsCount) ?: 0);
        $result->setPointNearest(min($pointsDistances) ?: 0);
        $result->setCanPostPay($canPostPay);

        // Calc dynamic description
        $count = $result->getPointsLocality();
        $distance = $result->getPointNearest();
        $tpls = [
            '#POINTS#' => $count,
            '#DISTANCE#' => $distance,
            '#DISTANCE0#' => number_format($distance, 0, '.', ''),
            '#DISTANCE1#' => number_format($distance, 1, '.', ''),
            '#DISTANCE2#' => number_format($distance, 2, '.', ''),
            '#DISTANCE3#' => number_format($distance, 3, '.', ''),
        ];
        $result->setDynamicDescription(static::locMessage($count > 0 ? 'POINTS_LOCALITY_COUNT' : 'NEAREST_POINT_DISTANCE', $tpls));
        $result->setDynamicDescription2(static::locMessage($count > 0 ? 'POINTS_LOCALITY_COUNT2' : 'NEAREST_POINT_DISTANCE', $tpls));
        $result->setTarget(89);
        // Cache and return
        $cache->startDataCache();
        $cache->endDataCache($result);
        return $result;
    }

    /**
     * Get points list
     * @param Shipment $shipment
     * @param bool $onlyAvail
     * @return array
     */
    public function getPointsList(Shipment $shipment, bool $onlyAvail = true) : array
    {
        $points = [];
        foreach ($this->getInjectedHandlers() as $service) {
            foreach (DeliveryMgr::getObjectById($service['ID'])->getPointsList($shipment, $onlyAvail) as $point) {
                $points[$point['ID']] = $point;
            }
        }
        return $points;
    }

    /**
     * Get active injected handlers
     * @return array
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

        // Make settings struct
        return [
            'SERV' => [
                'TITLE' => static::locMessage('SETTINGS_SERV_TITLE'),
                'DESCRIPTION' => static::locMessage('SETTINGS_SERV_DESCRIPTION'),
                'ITEMS' => $services,
            ],
        ];
    }

    /**
     * Run sync pickpoints list
     */
    public static function syncPickpoints(BasePickPointHandler $handler) : void
    {
        return;
    }

}