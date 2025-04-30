<?php

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

if (Bitrix\Main\Loader::includeModule('mozaika.devenv')) {
    include(__DIR__ . '/install.class.php');
}
