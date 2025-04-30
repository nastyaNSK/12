<?php

namespace Mozaika\Delivery;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\Input;

Loc::loadMessages(__FILE__);

class TariffScaleType extends Input\Base
{

    public static function isMultiple($value)
    {
        return false;
    }

    public static function getEditHtmlSingle($name, array $input, $value)
    {
        $uid = md5($name . '.mzk.salt');
        $html = '<input type="hidden" name="' . $name . '" id="value-' . $uid . '">';

        // Parse value
        $steps = explode(';', $value);
        $grid = [];
        foreach ($steps as $step) {
            [$weight, $price] = explode(':', $step);
            $grid[number_format((float)$weight, 3, '.', '')] = number_format((float)$price, 2, '.', '');
        }
        ksort($grid);
        if (empty($grid)) {
            $grid = ['0.000' => '0.00'];
        }

        // Grid HTML
        $html .= '<table id="table-' . $uid . '"><thead><tr><th>' . Loc::getMessage('COLUMN_' . ($input['KEY_CAPTION'] ?? 'WEIGHT')) . '</th><th>' . Loc::getMessage('COLUMN_PRICE') . '</th><th>&nbsp;</th></tr></thead><tbody>';
        foreach ($grid as $weight => $price) {
            $html .= '
                <tr>
                    <td><input type="text" class="weight" value="' . $weight . '"></td>
                    <td><input type="text" class="price" value="' . $price . '"></td>
                    <td><button type="button" class="del">' . Loc::getMessage('BUTTON_DEL_CAPTION') . '</button></td>
                </tr>
            ';
        }
        $html .= '</tbody><tfoot><tr><td colspan="3"><button type="button" class="add">' . Loc::getMessage('BUTTON_ADD_CAPTION') . '</button></td></tr></tfoot></table>
            <script>
                jQuery(function($){
                    $("#table-' . $uid . '")
                        .on("click", "button.add", function(){
                            var $last = $(this).closest("table").find("tbody tr:last"),
                                $clone = $last.clone();
                            $clone.find("input").val("");
                            $last.after($clone);
                        })
                        .on("click", "button.del", function(){
                            var $parent = $(this).closest("tbody"),
                                $row = $(this).closest("tr"),
                                $items = $row.parent().find("tr");
                            if ($items.length > 1) {
                                $row.remove();                                
                            }
                            else {
                                $row.find("input").val("");
                            }
                            $parent.find("input:first").trigger("input");
                        })
                        .on("input", "input", function(){
                            var value = [];
                            $(this).closest("table").find("tbody tr").each(function(){
                                var w = $(this).find("input.weight").val() * 1,
                                    p = $(this).find("input.price").val() * 1;
                                if (p > 0) {
                                    value.push(w + ":" + p);
                                }
                                $("#value-' . $uid . '").val(value.join(";"));
                            });
                        })
                        .find("input:first").trigger("input");
                });
            </script>         
        ';
        return $html;
    }

    public static function getErrorSingle(array $input, $value)
    {
        return [];
    }

}