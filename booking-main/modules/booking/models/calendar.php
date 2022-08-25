<?php
/**
 * @filesource modules/booking/models/calendar.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Calendar;

use Kotchasan\Http\Request;

/**
 * คืนค่าข้อมูลปฏิทิน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * คืนค่าข้อมูลปฏิทินเป็น JSON
     *
     * @param Request $request
     *
     * @return \static
     */
    public function toJSON(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && $request->isAjax()) {
            // ค่าที่ส่งมา
            $year = $request->post('year')->toInt();
            $month = $request->post('month')->toInt();
            if ($month == 12) {
                $next = ($year + 1).'-1-1';
            } else {
                $next = $year.'-'.($month + 1).'-1';
            }
            $events = array();
            // โหลดโมดูลที่ติดตั้งแล้ว
            $modules = \Gcms\Modules::create();
            foreach ($modules->getControllers('Calendar') as $className) {
                if (method_exists($className, 'get')) {
                    // โหลดค่าติดตั้งโมดูล
                    $className::get($year, $month, $next, $events);
                }
            }
            // คืนค่า JSON
            echo json_encode($events);
        }
    }
}
