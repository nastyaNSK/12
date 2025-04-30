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
class PickpointTable extends Entity\DataManager {
    use \Mozaika\DevEnv\Traits\EntityCreateUpdate;

    /**
     * DB table name for entity.
     * @return string
     */
    public static function getTableName() : string
    {
        return 'mozaika_delivery_pickpoints';
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
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_ID'),
            ]),
            new Fields\TextField('SERVICE_CODE', [
                'required' => true,
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_SERVICE_CODE'),
            ]),
            new Fields\TextField('SERVICE_ID', [
                'required' => true,
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_SERVICE_ID'),
            ]),
            new Fields\TextField('SERVICE_NAME', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_SERVICE_NAME'),
            ]),
            new Fields\IntegerField('RATE_ZONE', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_RATE_ZONE'),
            ]),
            new Fields\TextField('MAP_MARKER', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_MAP_MARKER'),
            ]),
            new Fields\TextField('NAME', [
                'required' => true,
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_NAME'),
            ]),
            new Fields\IntegerField('PHOTO', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_PHOTO'),
            ]),
            new Fields\IntegerField('LOCALITY_ID', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOCALITY_ID'),
            ]),
            new Fields\FloatField('LOC_LAT', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_LAT'),
            ]),
            new Fields\FloatField('LOC_LNG', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_LAT'),
            ]),
            new Fields\FloatField('LOC_LAT_ORIG', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_LAT_ORIG'),
            ]),
            new Fields\FloatField('LOC_LNG_ORIG', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_LAT_ORIG'),
            ]),
            new Fields\TextField('LOC_COUNTRY', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_COUNTRY'),
            ]),
            new Fields\TextField('LOC_REGION', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_REGION'),
            ]),
            new Fields\TextField('LOC_CITY', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_CITY'),
            ]),
            new Fields\TextField('LOC_ZIPCODE', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_ZIPCODE'),
            ]),
            new Fields\TextField('LOC_KLADR', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_KLADR'),
            ]),
            new Fields\TextField('LOC_FIAS', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_FIAS'),
            ]),
            new Fields\TextField('LOC_OKATO', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_OKATO'),
            ]),
            new Fields\TextField('LOC_OKTMO', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_OKTMO'),
            ]),
            new Fields\TextField('LOC_ADDRESS', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_ADDRESS'),
            ]),
            new Fields\TextField('LOC_SUBWAY', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_SUBWAY'),
            ]),
            new Fields\TextField('LOC_CUSTOM', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_LOC_CUSTOM'),
            ]),
            new Fields\TextField('ROUTE_HUMAN', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_ROUTE_HUMAN'),
            ]),
            new Fields\TextField('WORK_TIME', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_WORK_TIME'),
            ]),
            new Fields\BooleanField('CAN_CASH', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_CASH'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            new Fields\BooleanField('CAN_CARD', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_CARD'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            new Fields\BooleanField('CAN_LOYALTYCARD', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_LOYALTYCARD'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            new Fields\BooleanField('CAN_DRESSING', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_DRESSING'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            new Fields\BooleanField('CAN_RETURN', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_RETURN'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            new Fields\BooleanField('CAN_ACCEPT', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_ACCEPT'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            new Fields\BooleanField('CAN_PARTIAL', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CAN_PARTIAL'),
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            (new Fields\ArrayField('MAX_DIMENSIONS', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_MAX_DIMENSIONS'),
            ]))->configureSerializationPhp(),
            new Fields\FloatField('MAX_WEIGHT', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_MAX_WEIGHT'),
            ]),
            (new Fields\ArrayField('CONTACT_PHONES', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CONTACT_PHONES'),
            ]))->configureSerializationPhp(),
            (new Fields\ArrayField('CONTACT_MAILS', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CONTACT_MAILS'),
            ]))->configureSerializationPhp(),
            new Fields\TextField('CONTACT_CUSTOM', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CONTACT_CUSTOM'),
            ]),
            new Fields\TextField('TRANSIT_DAYS', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_CONTACT_TRANSIT_DAYS'),
            ]),
            (new Fields\ArrayField('RAW_DATA', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_RAW_DATA'),
            ]))->configureSerializationPhp(),
            new Fields\DatetimeField('DATE_INSERT', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_DATE_INSERT'),
            ]),
            new Fields\DatetimeField('DATE_UPDATE', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_DATE_UPDATE'),
            ]),
            new Fields\BooleanField('ACTIVE', [
                'title' => Loc::getMessage('ENTITY_PICKPOINT_FIELD_ACTIVE'),
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
        ];
    }

}
