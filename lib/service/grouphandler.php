<?php
namespace Mozaika\Delivery\Service;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Error;
use Bitrix\Sale\Delivery\Services\Table as DeliveryServ;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryMgr;
use Bitrix\Sale\Shipment;
use Mozaika\DevEnv\ModuleOption;
use Mozaika\Delivery\CalculationResult;
use Seven\Common\LogWrapper;

Loc::loadMessages(__FILE__);

class GroupHandler extends BaseServiceHandler
{

    const SERVICE_CODE = 'group';
    const WIDGET_HANDLER = 'group';

    protected static $canHasProfiles = false;
    protected $injectHandlers;

    /**
     * UniversalCourierHandler constructor.
     * @param array $initParams
     */
    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
        // Load avaliable handlers
        $handlers = array_filter(array_map(function($handler){
            $handler = trim($handler, '\\');
            return $handler != self::class ? $handler : '';
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
        $handlers = $this->getInjectedHandlers();
        $indexById = array_flip(array_column($handlers, 'ID'));
        $props = $shipment->getOrder()->getPropertyCollection();
        $index = array_flip(array_map('strtoupper', array_column($props->getArray()['properties'], 'CODE', 'ID')));
        $code = ModuleOption::get('order_prop_delivery_id', 'GROUP_DELIVERY_ID');
        $variantId = empty($index[$code]) ? 0 : $props->getItemByOrderPropertyId($index[$code])->getValue();

        // Result for once selected delivery
        if (isset($indexById[$variantId])) {
            $result = DeliveryMgr::calculateDeliveryPrice($shipment, $variantId);
            $result->setGroupTitle($this->getFullConfig()['group_title'] ?? '');
            $result->setWidget($this->getInitialResult()->getWidget());
            $result->setDynamicDescription($handlers[$indexById[$variantId]]['NAME']);
            return $result;
        }

        // Calc and check services
        $results = [];
        foreach ($handlers as $handler) {
            $result = DeliveryMgr::calculateDeliveryPrice($shipment, $handler['ID']);
            if (!$result->isSuccess()) {
                continue;
            }
            $results[$handler['ID']] = [
                'price' => $result->getDeliveryPrice(),
                'periodMin' => $result->getPeriodFrom(),
                'periodMax' => $result->getPeriodTo() ?: $result->getPeriodFrom(),
            ];
        }
        if (empty($results)) {
            $result = $this->getInitialResult();
            $result->addError(new Error($this->locMessage('ERROR_NO_SERVICES')));
            $result->setIncompatible();
            return $result;
        }

        // Make result
        $prices = array_filter(array_column($results, 'price'));
        if (sizeof(array_column($results, 'periodMin')) > 0) {
            $periodMin = min(array_column($results, 'periodMin'));
        }
        if (sizeof(array_column($results, 'periodMax')) > 0) {
            $periodMax = array_column($results, 'periodMax');
        }
        if (sizeof($prices)<=0) {
            $prices = [0];
        }
        $result = $this->getInitialResult();
        $result->setPriceFrom(min($prices));
        $result->setPriceTo(max($prices));
        $result->setPeriodFrom($periodMin ?? 0);
        $result->setPeriodTo($periodMax ?? 0);
        $result->setTarget(92);
        return $result;
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
            'DEF' => [
                'TITLE' => static::locMessage('SETTINGS_DEF_TITLE'),
                'DESCRIPTION' => static::locMessage('SETTINGS_DEF_DESCRIPTION'),
                'ITEMS' => [
                    'group_title' => [
                        'TYPE' => 'STRING',
                        'NAME' => static::locMessage('SETTINGS_DEF_GROUP_TITLE'),
                        'DEFAULT' => '',
                    ],
                ],
            ],
            'SERV' => [
                'TITLE' => static::locMessage('SETTINGS_SERV_TITLE'),
                'DESCRIPTION' => static::locMessage('SETTINGS_SERV_DESCRIPTION'),
                'ITEMS' => $services,
            ],
        ];
    }

}