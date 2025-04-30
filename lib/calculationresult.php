<?php
namespace Mozaika\Delivery;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class CalculationResult extends \Bitrix\Sale\Delivery\CalculationResult
{

    /*
     * ALARM!!! Следить чтобы внутренние поля инициализировались либо пустыми значениями empty(xx) = true, либо это должны быть всегда непустые значения (например
     * булев тип хранить как строку Y/N, она никогда не будет empty)
     * Ибо, неизвестно по какому ретроградному меркурию эти слоупоки кастомизировали сериализацию этого объекта и в нее не попадают значения, которые empty() = true
     * В итоге, если взять булево значение с инициализацией true или число с инициализацией отличной от нуля, то потом при установке этих свойств в пустые значения
     * они не попадают в сериализацию и восстанавливаются уже как дефолтные...
     *
     * bitrix/modules/sale/lib/resultserializable.php:26
     *
     * foreach($result as $name => $value)
     *     if(empty($value))
     *         unset($result[$name]);
     */

    protected $compatible = 'Y';
    protected $dynamicTitle = '';
    protected $dynamicDescription = '';
    protected $dynamicDescription2 = '';
    protected $widget = '';
    protected $deliveryIntervals = [];
    protected $addressNeed = 'N';
    protected $promo = '';
    protected $groupTitle = '';
    protected $realServiceId = 0;
    protected $propsValues = [];
    protected $priceFrom = 0;
    protected $priceTo = 0;
    protected $priceSale = 0;
    protected $pointsLocality = 0;
    protected $pointNearest = 0;
    protected $canPostPay = 'Y';
    protected $targetId = -1;

    public function setIncompatible () : void
    {
        $this->compatible = 'N';
    }

    public function setDynamicDescription (string $description) : void
    {
        $this->dynamicDescription = $description;
    }

    public function setDynamicDescription2 (string $description) : void
    {
        $this->dynamicDescription2 = $description;
    }

    public function setDynamicTitle (string $title) : void
    {
        $this->dynamicTitle = $title;
    }

    public function setWidget (string $widget) : void
    {
        $this->widget = $widget;
    }

    public function setAddressNeed (bool $need) : void
    {
        $this->addressNeed = $need ? 'Y' : 'N';
    }

    public function setCanPostPay (bool $can) : void
    {
        $this->canPostPay = $can ? 'Y' : 'N';
    }

    public function setPromo (string $promo) : void
    {
        $this->promo = $promo;
    }

    public function setGroupTitle (string $title) : void
    {
        $this->groupTitle = $title;
    }

    public function setRealServiceId (int $serviceId) : void
    {
        $this->realServiceId = $serviceId;
    }

    public function setDeliveryIntervals (array $intervals) : void
    {
        $this->deliveryIntervals = $intervals;
    }

    public function addDeliveryInterval (array $interval) : void
    {
        $this->deliveryIntervals[] = $interval;
    }

    public function setPropsValues (array $value) : void
    {
        $this->propsValues = $value;
    }

    public function setPropValue (string $prop, string $value) : void
    {
        $this->propsValues[$prop] = $value;
    }

    public function setPriceFrom (float $value) : void
    {
        $this->priceFrom = $value;
    }

    public function setPriceTo (float $value) : void
    {
        $this->priceTo = $value;
    }

    public function setPriceSale (float $value) : void
    {
        $this->priceSale = $value;
    }

    public function setPointsLocality (int $value) : void
    {
        $this->pointsLocality = $value;
    }

    public function setPointNearest (float $value) : void
    {
        $this->pointNearest = $value;
    }

    public function setTarget (int $value) : void
    {
        $this->targetId = $value;
    }

    public function getDynamicTitle () : string
    {
        return $this->dynamicTitle;
    }

    public function getDynamicDescription () : string
    {
        return $this->dynamicDescription;
    }

    public function getDynamicDescription2 () : string
    {
        return $this->dynamicDescription2;
    }

    public function getWidget () : string
    {
        return $this->widget;
    }

    public function getAddressNeed () : bool
    {
        return $this->addressNeed == 'Y';
    }

    public function getCanPostPay () : bool
    {
        return $this->canPostPay == 'Y';
    }

    public function getPromo () : string
    {
        return $this->promo;
    }

    public function getGroupTitle () : string
    {
        return $this->groupTitle;
    }

    public function getRealServiceId () : int
    {
        return $this->realServiceId;
    }

    public function getDeliveryIntervals () : array
    {
        return $this->deliveryIntervals;
    }

    public function getPropsValues () : array
    {
        return $this->propsValues;
    }

    public function getPropsValue (string $key) : string
    {
        return $this->propsValues[$key] ?? '';
    }

    public function getPriceFrom () : float
    {
        return $this->priceFrom ?: $this->deliveryPrice;
    }

    public function getPriceTo () : float
    {
        return $this->priceTo ?: $this->deliveryPrice ?: $this->priceFrom;
    }

    public function getDeliveryPrice () : float
    {
        return $this->deliveryPrice ?: $this->priceFrom ?: $this->priceTo;
    }

    public function getPriceSale () : float
    {
        return $this->priceSale < 0 ? 0 : ($this->priceSale ?: $this->getDeliveryPrice());
    }

    public function getPointsLocality () : int
    {
        return $this->pointsLocality;
    }

    public function getPointNearest () : float
    {
        return $this->pointNearest;
    }

    public function getTarget () : int
    {
        return $this->targetId;
    }

    public function isCompatible () : bool
    {
        return $this->compatible == 'Y';
    }

    public function getHumanPeriod () : string
    {
        $period = (int)$this->getPeriodFrom();
        if (empty($period)) {
            return '';
        }
        $daysForms = explode(',', Loc::getMessage('MOZAIKA_DELIVERY_CALCULATIONRESULT_DAYS_DECLENSIONS_R'));
        return Loc::getMessage('MOZAIKA_DELIVERY_CALCULATIONRESULT_HUMAN_PERIOD', [
            '#PERIOD#' => $period,
            '#DAYS#' => \Mozaika\DevEnv\DevUtils::declensionNumRus($period, $daysForms),
        ]);
    }

}