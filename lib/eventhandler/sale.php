<?php
namespace Mozaika\Delivery\EventHandler;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Mozaika\Delivery\Service;

Loc::loadMessages(__FILE__);

class Sale
{

    const CUSTOM_EVENTS = [];
    const DELIVERY_HANDLERS = [
        Service\EDost\ServiceHandler::class,
        Service\EMS\ServiceHandler::class,
        Service\RussianPost\ServiceHandler::class,
        Service\SberLogistics\PickPointHandler::class,
        Service\SberLogistics\CourierHandler::class,
        Service\Yandex\PickPointHandler::class,
        Service\Yandex\CourierHandler::class,
        Service\RussianPost\PickPointHandler::class,
        Service\DHL\ServiceHandler::class,
        Service\BoxBerry\PickPointHandler::class,
        Service\BoxBerry\CourierHandler::class,
        Service\Ozon\PickPointHandler::class,
        Service\Ozon\CourierHandler::class,
        Service\FivePost\PickPointHandler::class,
        Service\Dalli\CourierHandler::class,
        Service\UniversalPickPointHandler::class,
        Service\UniversalCourierHandler::class,
        Service\GroupHandler::class,
//        Service\Algocom\CourierHandler::class,     Обработчик оставляем но пока не используем, данные получаемые через API невозможно нормально использовать, они не корректны. Нужен рефакторинг.
//        Service\MeaSoft\CourierHandler::class,     Обработчик оставляем но пока не используем, данные получаемые через API невозможно нормально использовать, они не корректны. Нужен рефакторинг.
    ];

    /**
     * Add delivery methods
     * @param Event $event
     * @return EventResult
     */
    public static function onSaleDeliveryHandlersClassNamesBuildList_event (Event $event) : EventResult
    {
        $path = str_replace(Application::getDocumentRoot(), '', dirname(__DIR__));
        $handlers = [];
        foreach (self::DELIVERY_HANDLERS as $handler) {
            $handlers['\\' . $handler] = strtolower(str_replace(['Mozaika\\Delivery', '\\'], [$path, '/'], $handler)) . '.php';
        }
        return new EventResult(EventResult::SUCCESS, $handlers);
    }

    /**
     * Add custom types fields to delivery settings
     * @param Event $event
     * @return EventResult
     */
    public static function registerInputTypes_event (Event $event) : EventResult
    {
        \CJSCore::Init(['jquery2']);
        return new EventResult(EventResult::SUCCESS, [
            'MOZAIKA_TARIFF_SCALE' => [
                'CLASS' => \Mozaika\Delivery\TariffScaleType::class,
                'NAME' => Loc::getMessage('INPUT_MOZAIKA_TARIFF_SCALE_NAME'),
            ],
        ]);
    }

}