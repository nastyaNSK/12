<?php

namespace Mozaika\Delivery\Service;

use Bitrix\Main\Application;
use Bitrix\Main\IO\File;
use Bitrix\Main\Localization\Loc;
use Mozaika\DevEnv\DevUtils;
use Mozaika\DevEnv\ModuleOption;
use Mozaika\Delivery\Models\PickpointTable;
use Mozaika\Delivery\Models\ZoneTable;

Loc::loadMessages(__FILE__);

class BasePickPointSyncer
{
    const SYNC_INTERVAL = 79200; // 22 hours

    protected $handler;
    protected $config;
    protected $existsPoints;
    protected $existsZones;

    public function __construct (BasePickPointHandler $handler)
    {
        $this->handler = $handler;
        $this->config = $handler->getFullConfig();
    }

    /**
     * Disable all pickpoint not founded in sync list
     */
    protected function disableNotFoundPoints() : void
    {
        $items = array_column(array_filter($this->existsPoints, function($item){
            return $item['ACTIVE'] !== 'N';
        }), 'ID');
        if (!empty($items)) {
            PickpointTable::updateMulti($items, ['ACTIVE' => 'N']);
        }
    }

    /**
     * Mapping fields
     * @param array $fields
     * @return array
     */
    protected function mapOnePoint(array $fields) : array
    {
        return $fields;
    }

    /**
     * Sync one point (add or update)
     * @param array $fields
     */
    protected function syncOnePoint(array $fields) : void
    {

        $fields = $this->mapOnePoint($fields);

        if (empty($fields['SERVICE_ID'])) {
            return;
        }
        $fields = static::formatFields($fields);
        if (isset($this->existsPoints[$fields['SERVICE_ID']])) {
            $id = $this->existsPoints[$fields['SERVICE_ID']]['ID'];
            $hashNew = static::makeHash($fields);
            $hashOld = static::makeHash($this->existsPoints[$fields['SERVICE_ID']]);
            unset($this->existsPoints[$fields['SERVICE_ID']]);
            if ($hashNew !== $hashOld) {
                unset($fields['LOC_LAT'], $fields['LOC_LNG']);
                PickpointTable::update($id, $fields);
            }
            return;
        }
        PickpointTable::add($fields);
    }

    /**
     * Sync one point partial fields
     * @param int $id
     * @param array $fields
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\IO\FileNotFoundException
     * @throws \Bitrix\Main\IO\FileOpenException
     */
    protected function syncOnePointPartial(int $id, array $fields) : void
    {
        // Init, find point
        $ref = array_column($this->existsPoints, 'SERVICE_ID', 'ID');
        $point = $this->existsPoints[$ref[$id]];
        if (empty($point)) {
            return;
        }

        // Work photo
        $existsHash = empty($point['PHOTO']) ? '' : md5_file(Application::getDocumentRoot() . \CFile::GetPath($point['PHOTO']));
        if (!empty($fields['PHOTO'])) {
            $file = new File($fields['PHOTO']);
            unset($fields['PHOTO']);
            if ($file->isExists()) {
                $hash = md5_file($file->getPath());
                $type = explode('/', $file->getContentType());
                if ($type[0] == 'image' && $hash !== $existsHash) {
                    $fields['PHOTO'] = \CFile::SaveFile([
                        'name' => 'pickpoint-' . $id . '.' . $type[1],
                        'size' => $file->getSize(),
                        'tmp_name' => $file->getPath(),
                        'type' => $file->getContentType(),
                        'MODULE_ID' => DevUtils::moduleIdByNamespace(),
                    ], 'mzk.dlvr.pickpoint');
                }
            }
        }
        if (!empty($fields['PHOTO']) && !empty($point['PHOTO'])) {
            \CFile::Delete($point['PHOTO']);
        }

        // Work fields
        $saveFields = [];
        foreach ($fields as $field => $value) {
            if (!empty($value) && $value != $point[$field]) {
                $saveFields[$field] = $value;
            }
        }

        // Save
        if (!empty($saveFields)) {
            PickpointTable::update($id, $saveFields);
        }
    }

    /**
     * Add new rate zone
     * @param string $name
     * @param array $rate
     * @return array
     */
    protected function addZone(string $name, array $rate = []) : array
    {
        $rates = [];
        foreach ($rate as $weight => $price) {
            $rates[number_format($weight, 2, '.', '')] = number_format($price, 2, '.', '');
        }
        ksort($rates);
        $fields = [
            'SERVICE_CODE' => $this->handler::SERVICE_CODE,
            'NAME' => $name,
            'RATES' => $rates,
        ];
        $result = ZoneTable::add($fields);
        $fields['ID'] = $result->getId();
        $this->existsZones[$name] = $fields;
        return $fields;
    }

    /**
     * Load exists rate zones by service
     */
    protected function loadExistsZones() : void
    {
        $this->existsZones = [];
        $list = ZoneTable::getList([
            'filter' => ['=SERVICE_CODE' => $this->handler::SERVICE_CODE],
        ]);
        while ($zone = $list->fetch()) {
            $this->existsZones[$zone['NAME']] = $zone;
        }
    }

    /**
     * Load exists pickpoints by service
     */
    protected function loadExistsPoints() : void
    {
        $this->existsPoints = [];
        $list = PickpointTable::getList([
            'filter' => ['=SERVICE_CODE' => $this->handler::SERVICE_CODE],
        ]);
        while ($point = $list->fetch()) {
            unset($point['RAW_DATA'], $point['DATE_INSERT'], $point['DATE_UPDATE']);
            $this->existsPoints[$point['SERVICE_ID']] = $point;
        }
    }

    /**
     * Get time of last sync from options
     * @return int
     */
    protected function getLastSync() : int
    {
        return (int)ModuleOption::get($this->handler::SERVICE_CODE . '_last_sync', 0);
    }

    /**
     * Save time of last sync in options
     */
    protected function updateLastSync() : void
    {
        ModuleOption::set($this->handler::SERVICE_CODE . '_last_sync', time());
    }

    /**
     * Recive list of points by source
     * @return array
     */
    protected function getPointsData() : array
    {
        return [];
    }

    /**
     * Sync list of rates
     * @return bool
     */
    protected function syncZonesRates() : bool
    {
        return true;
    }

    /**
     * Sync list of points
     * @return bool
     */
    protected function syncPointsList() : bool
    {
        // Get actual points data
        $points = $this->getPointsData();
        if (empty($points)) {
            return false;
        }

        // Init exists points
        $this->loadExistsPoints();

        // Sync each point
        foreach ($points as $point) {
            $this->syncOnePoint($point);
        }

        // Disable other points
        $this->disableNotFoundPoints();

        return true;
    }

    /**
     * Start sync process
     */
    public function run() : void
    {
        // Check last sync time
        $last = $this->getLastSync();
        if ($last > time() - static::SYNC_INTERVAL) {
            return;
        }

        // Sync data and set last update time
        if ($this->syncPointsList() && $this->syncZonesRates()) {
            $this->updateLastSync();
        }
    }

    /**
     * Normalize russian phone number
     * @param array $phones
     * @return array
     */
    protected static function normalizePhones(array $phones) : array
    {
        return array_filter(array_map(function($phone){
            $phone = preg_replace('#\D#', '', $phone);
            $phone = preg_replace('#^[78]?(\d{10})$#', '$1', $phone);
            if (strlen($phone) != 10) {
                return '';
            }
            $phone = '+7 ' . preg_replace('#^(\d{3})(\d{3})(\d{2})(\d{2})$#', '($1) $2-$3-$4', $phone);
            return str_replace('+7 (800) ', '8 (800) ', $phone);
        }, $phones));
    }

    /**
     * Format fields values for point model
     * @param array $fields
     * @return array
     */
    protected static function formatFields(array $fields) : array
    {
        $fields['LOC_COUNTRY'] = strtoupper($fields['LOC_COUNTRY'] ?? '');
        $fields['LOC_FIAS'] = strtoupper($fields['LOC_FIAS'] ?? '');
        $fields['LOC_KLADR'] = strtoupper($fields['LOC_KLADR'] ?? '');
        $fields['LOC_ZIPCODE'] = strtoupper($fields['LOC_ZIPCODE'] ?? '');

        $fields['LOC_LAT_ORIG'] = $fields['LOC_LAT_ORIG'] ?? $fields['LOC_LAT'] ?? 0;
        $fields['LOC_LNG_ORIG'] = $fields['LOC_LNG_ORIG'] ?? $fields['LOC_LNG'] ?? 0;

        foreach (['CAN_CASH', 'CAN_CARD', 'CAN_LOYALTYCARD', 'CAN_DRESSING', 'CAN_RETURN', 'CAN_ACCEPT', 'ACTIVE'] as $key) {
            $fields[$key] = in_array(strtoupper($fields[$key] ?? ''), ['1', 'Y', 'YES', 'T', 'TRUE', 'Д', 'ДА']) ? 'Y' : 'N';
        }

        $fields['CONTACT_PHONES'] = static::normalizePhones($fields['CONTACT_PHONES'] ?? []);
        $fields['CONTACT_MAILS'] = $fields['CONTACT_MAILS'] ?? [];

        return $fields;
    }

    /**
     * Make hash by point data to check change
     * @param array $fields
     * @return string
     */
    protected static function makeHash(array $fields) : string
    {
        unset($fields['ID'], $fields['RAW_DATA']);
        $fields = array_map(function($item){
            return is_array($item) ? $item : strtoupper(trim($item));
        }, $fields);

        $fields['LOC_LAT'] = number_format($fields['LOC_LAT_ORIG'] ?? $fields['LOC_LAT'] ?? 0, 8, '.', '');
        $fields['LOC_LNG'] = number_format($fields['LOC_LNG_ORIG'] ?? $fields['LOC_LNG'] ?? 0, 8, '.', '');
        $fields['MAX_WEIGHT'] = number_format($fields['MAX_WEIGHT'] ?? 0, 2, '.', '');

        $fields['MAX_DIMENSIONS'] = $fields['MAX_DIMENSIONS'] ?? [0, 0, 0];
        sort($fields['MAX_DIMENSIONS']);
        $fields['MAX_DIMENSIONS'] = array_map(function($item){
            return number_format($item, 2, '.', '');
        }, $fields['MAX_DIMENSIONS']);

        $fields = array_filter($fields);
        unset($fields['LOC_LAT_ORIG'], $fields['LOC_LNG_ORIG']);
        ksort($fields);
        return md5(serialize($fields));
    }

}