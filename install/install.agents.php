<?php
return [
    [
        'HANDLER' => '\\Mozaika\\Delivery\\Agent::syncPickpoints();',
        'PERIODICAL' => 'Y',
        'PERIOD' => 60,
        'ACTIVE' => 'Y',
    ],
    [
        'HANDLER' => '\\Mozaika\\Delivery\\Agent::calcLocalities();',
        'PERIODICAL' => 'Y',
        'PERIOD' => 60,
        'ACTIVE' => 'Y',
    ],
    [
        'HANDLER' => '\\Mozaika\\Delivery\\Agent::splitPickpoints();',
        'PERIODICAL' => 'Y',
        'PERIOD' => 1440,
        'ACTIVE' => 'Y',
    ],
];