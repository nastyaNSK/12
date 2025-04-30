<?php

namespace Mozaika\Delivery\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Localization\Loc;
use Mozaika\DaData\API;
use Mozaika\DevEnv\ModuleOption;

Loc::loadMessages(__FILE__);

class DaData extends Controller
{

    /**
     * Configure actions methods
     * @return array
     */
    public function configureActions() : array
    {
        // Set default filters to all methods
        $parentActions = array_filter(get_class_methods(get_parent_class($this)), function($method){
            return substr($method, -6) === 'Action';
        });
        $selfActions = array_filter(get_class_methods($this), function($method){
            return substr($method, -6) === 'Action';
        });
        $methods = array_map(function($method){
            return substr($method, 0, -6);
        }, array_diff($selfActions, $parentActions));
        return array_fill_keys($methods, ['prefilters' => [
            new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
            new ActionFilter\Csrf(),
        ]]);
    }

    /**
     * Get instance of DaDataAPI object
     * @return Mozaika\DaData\API
     */
    protected static function getDaData() : API
    {
        return new API([
            'suggestions_endpoint' => ModuleOption::get('dadata_api_suggestions_endpoint'),
            'token' => ModuleOption::get('dadata_api_token'),
        ]);
    }

    /**
     * Get city suggestions list
     * @param string $query
     * @return array
     */
    public static function getCitySuggestionsAction(string $query) : array
    {
        $suggestions = [];
        $data = static::getDaData()->getCitySuggestions($query);
        foreach ($data['suggestions'] ?? [] as $item) {
            $item['text'] = implode(', ', array_filter(array_unique([
                $item['data']['settlement_with_type'] ?? '',
                $item['data']['city_with_type'] ?? '',
                $item['data']['area_with_type'] ?? '',
                $item['data']['region_with_type'] ?? '',
                $item['data']['country'] ?? '',
            ])));
            $item['id'] = md5($item['text']);
            $suggestions[$item['id']] = $item;
        }
        $suggestions = array_values($suggestions);
        return $suggestions ?: [[
            'id' => 'not-found',
            'text' => Loc::getMessage('MOZAIKA_DELIVERY_CONTROLLER_DADATA_NOT_FOUND_CITY'),
        ]];
    }

    /**
     * Get clarify of city
     * @param string $query
     * @return array
     */
    public static function getCityClarifyAction(string $query) : array
    {
        $data = static::getDaData()->getClarifiedCity($query)['data'] ?? [];
        if (!empty($data)) {
            $data['text'] = implode(', ', array_filter(array_unique([
                $data['data']['settlement_with_type'] ?? '',
                $data['data']['city_with_type'] ?? '',
                $data['data']['area_with_type'] ?? '',
                $data['data']['region_with_type'] ?? '',
                $data['data']['country'] ?? '',
            ])));
            $data['id'] = md5($data['text']);
        }
        return $data;
    }

    /**
     * Get zipcode suggestions list
     * @param string $query
     * @return array
     */
    public static function getZipSuggestionsAction(string $query) : array
    {
        $suggestions = [];
        $data = static::getDaData()->getZipSuggestions($query);
        foreach ($data['suggestions'] ?? [] as $item) {
            $item['postal_code'] = $query;
            $item['text'] = implode(', ', array_filter(array_unique([
                $item['data']['settlement_with_type'] ?? '',
                $item['data']['city_with_type'] ?? '',
                $item['data']['area_with_type'] ?? '',
                $item['data']['region_with_type'] ?? '',
                $item['data']['country'] ?? '',
            ])));
            $item['location_id'] = md5($item['text']);
            $item['id'] = md5($item['value']);
            $suggestions[$item['id']] = $item;
        }
        $suggestions = array_values($suggestions);
        return $suggestions;
    }

    /**
     * Get street suggestions list
     * @param string $query
     * @param string $city
     * @return array
     */
    public static function getStreetSuggestionsAction(string $query, string $city) : array
    {
        $suggestions = [];
        $data = static::getDaData()->getStreetSuggestions($query, $city);
        foreach ($data['suggestions'] ?? [] as $item) {
            $item['text'] = implode(', ', array_filter(array_unique([
                $item['data']['settlement_with_type'] ?? '',
                $item['data']['city_with_type'] ?? '',
                $item['data']['area_with_type'] ?? '',
                $item['data']['region_with_type'] ?? '',
                $item['data']['country'] ?? '',
            ])));
            $item['location_id'] = md5($item['text']);
            $item['location_text'] = $item['text'];
            $item['id'] = md5($item['value']);
            $item['text'] = $item['value'];
            $suggestions[$item['id']] = $item;
        }
        $suggestions = array_values($suggestions);
        return $suggestions;
    }

    /**
     * Get house suggestions list
     * @param string $query
     * @param string $street
     * @return array
     */
    public static function getHouseSuggestionsAction(string $query, string $street) : array
    {
        $suggestions = [];
        $data = static::getDaData()->getHouseSuggestions($query, $street);
        foreach ($data['suggestions'] ?? [] as $item) {
            $item['text'] = implode(', ', array_filter(array_unique([
                $item['data']['settlement_with_type'] ?? '',
                $item['data']['city_with_type'] ?? '',
                $item['data']['area_with_type'] ?? '',
                $item['data']['region_with_type'] ?? '',
                $item['data']['country'] ?? '',
            ])));
            $item['location_id'] = md5($item['text']);
            $item['location_text'] = $item['text'];
            $item['id'] = md5($item['value']);
            $item['text'] = $item['value'];
            $suggestions[$item['id']] = $item;
        }
        $suggestions = array_values($suggestions);
        return $suggestions;
    }

}
