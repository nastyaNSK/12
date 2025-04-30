<?php

namespace Mozaika\Delivery\EventHandler;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Web\Uri;
use Seven\Common\LogWrapper;

Loc::loadMessages(__FILE__);

class Main
{

    const CUSTOM_EVENTS = [];

    /**
     * Add delivery methods
     * @param Event $event
     * @return EventResult
     */
    public static function OnAdminContextMenuShow_event (array &$items) : void
    {
        $server = Application::getInstance()->getContext()->getServer();
        if (strpos($server->getRequestUri(), '/bitrix/admin/sale_order_view.php') !== false) {
            $uri = new Uri($server->getRequestUri());
            $uri->setPath('/bitrix/admin/mozaika.delivery-order-change-pickpoint.php');
            $items[] = [
                'TEXT' => Loc::getMessage('MENU_CHANGE_PICKPOINT_TITLE'),
                'TITLE' => Loc::getMessage('MENU_CHANGE_PICKPOINT_DESCRIPTION'),
                'LINK' => $uri->getUri(),
            ];
        }
    }

    /**
     * Add system log type
     * @return array
     */
    public static function OnEventLogGetAuditTypes_event () : array
    {
        return [
            'MZKDLVR_API' => Loc::getMessage('EVENT_LOG_TYPE_MZKDLVR_API'),
        ];
    }

}
