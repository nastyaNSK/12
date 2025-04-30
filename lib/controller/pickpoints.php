<?php
namespace Mozaika\Delivery\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Localization\Loc;
use Mozaika\Delivery\Models\PickpointTable;

Loc::loadMessages(__FILE__);

class Pickpoints extends Controller
{
    use \Mozaika\DevEnv\Traits\ConfigureActionsDefault;

    /**
     * Get all active pickpoints info
     * @return array
     */
    public static function loadAllAction() : array
    {
        // Cache
        $cacheId = md5(__METHOD__);
        $cache = Cache::createInstance();
        if ($cache->initCache(3600, $cacheId, 'mozaika.delivery/getPoints')) {
            return $cache->getVars();
        }

        // Load
        $path = str_replace(Application::getDocumentRoot(), '', dirname(dirname(__DIR__)) . '/assets/img/map-markers/');
        $points = [];
        $list = PickpointTable::getList([
            'select' => ['ID', 'SERVICE_NAME', 'SERVICE_CODE', 'SERVICE_ID', 'MAP_MARKER', 'RATE_ZONE', 'NAME', 'LOC_LAT', 'LOC_LNG', 'LOC_ADDRESS', 'CAN_CASH', 'CAN_CARD', 'WORK_TIME', 'CONTACT_PHONES', 'TRANSIT_DAYS'],
            'filter' => [
                '=ACTIVE' => 'Y',
            ],
            'cache' => ['ttl' => 1800],
        ]);
        while ($point = $list->fetch()) {
            $point['MAP_MARKER'] = $path . $point['MAP_MARKER'] . '.svg';
            $points[$point['ID']] = $point;
        }

        // Result
        if (!empty($points)) {
            $cache->startDataCache();
            $cache->endDataCache($points);
        }
        return $points;
    }

    /**
     * Configure actions methods
     * @return array
     */
    public function configureActions() : array
    {
        return $this->simpleAutoConfigureActions([
            'prefilters' => [], //TODO: clean params for default filters
        ]);
    }

    /**
     * Get active pickpoint info
     * @return array
     */
    public static function getPickpoint($id) : array
    {
        // Cache
        $cacheId = md5(__METHOD__.$id);
        $cache = Cache::createInstance();
        if ($cache->initCache(3600, $cacheId, 'mozaika.delivery/getPoints')) {
            return $cache->getVars();
        }

        // Load
        $path = str_replace(Application::getDocumentRoot(), '', dirname(dirname(__DIR__)) . '/assets/img/map-markers/');
        $points = [];
        $list = PickpointTable::getList([
            'select' => ['ID', 'SERVICE_NAME', 'SERVICE_CODE', 'SERVICE_ID', 'MAP_MARKER', 'RATE_ZONE', 'NAME', 'LOC_LAT', 'LOC_LNG', 'LOC_ADDRESS', 'CAN_CASH', 'CAN_CARD', 'WORK_TIME', 'CONTACT_PHONES', 'TRANSIT_DAYS'],
            'filter' => [
                '=ACTIVE' => 'Y',
                "=LOCALITY_ID" => $id
            ],
            'cache' => ['ttl' => 1800],
        ]);
        while ($point = $list->fetch()) {
            $point['MAP_MARKER'] = $path . $point['MAP_MARKER'] . '.svg';
            $points[$point['ID']] = $point;
        }

        // Result
        if (!empty($points)) {
            $cache->startDataCache();
            $cache->endDataCache($points);
        }
        return $points;
    }

}