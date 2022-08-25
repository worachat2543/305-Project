<?php
/**
 * @filesource modules/booking/views/tools.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Tools;

use Kotchasan\Date;

/**
 * ฟังก์ชั่นแสดงผล Booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * คืนค่าช่วงเวลาจอง
     *
     * @param array $order
     *
     * @return string
     */
    public static function toDate($order)
    {
        if (
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $order['begin'], $begin) &&
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $order['end'], $end)
        ) {
            if ($begin[1] == $end[1]) {
                return Date::format($order['begin'], 'd M Y').' {LNG_Time} '.Date::format($order['begin'], 'TIME_FORMAT').' {LNG_to} '.Date::format($order['end'], 'TIME_FORMAT');
            } else {
                return Date::format($order['begin']).' {LNG_to} '.Date::format($order['end']);
            }
        }
    }
}
