<?php

namespace Mozaika\Delivery\Models;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Entity;

Loc::loadMessages(__FILE__);

/**
 * Class PickpointTable
 * @package Mozaika\Delivery\Models
 */
class ZoneTable extends Entity\DataManager {
    use \Mozaika\DevEnv\Traits\EntityCreateUpdate;

    /**
     * DB table name for entity.
     * @return string
     */
    public static function getTableName() : string
    {
        return 'mozaika_delivery_zones';
    }

    /**
     * Entity map definition.
     * @return array
     */
    public static function getMap() : array
    {
        return [
            new Fields\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => Loc::getMessage('ENTITY_ZONE_FIELD_ID'),
            ]),
            new Fields\TextField('SERVICE_CODE', [
                'required' => true,
                'title' => Loc::getMessage('ENTITY_ZONE_FIELD_SERVICE_CODE'),
            ]),
            new Fields\TextField('NAME', [
                'required' => true,
                'title' => Loc::getMessage('ENTITY_ZONE_FIELD_NAME'),
            ]),
            (new Fields\ArrayField('RATES', [
                'title' => Loc::getMessage('ENTITY_ZONE_FIELD_RATES'),
            ]))->configureSerializationPhp(),
            new Fields\DatetimeField('DATE_INSERT', [
                'title' => Loc::getMessage('ENTITY_ZONE_FIELD_DATE_INSERT'),
            ]),
            new Fields\DatetimeField('DATE_UPDATE', [
                'title' => Loc::getMessage('ENTITY_ZONE_FIELD_DATE_UPDATE'),
            ]),
        ];
    }

}