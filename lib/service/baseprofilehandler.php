<?php

namespace Mozaika\Delivery\Service;

class BaseProfileHandler extends BaseServiceHandler
{

    const SERVICE_CODE = 'empty';
    const PROFILE_CODE = 'empty';

    protected static $canHasProfiles = false;
    protected static $isProfile = true;

}