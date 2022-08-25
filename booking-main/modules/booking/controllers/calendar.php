<?php
/**
 * @filesource modules/booking/controllers/calendar.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Calendar;

use Kotchasan\Database\Sql;
use Kotchasan\Date;
use Kotchasan\Language;
use Kotchasan\Text;

/**
 * module=home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller
{
    /**
     * คืนค่าข้อมูลปฏิทิน (booking)
     * สำหรับแสดงในปฏิทินหน้าแรก
     *
     * @param int $year
     * @param int $month
     * @param string $next
     * @param array $events
     *
     * @return mixed
     */
    public static function get($year, $month, $next, &$events)
    {
        // Query เดือนที่เลือก
        $query = \Kotchasan\Model::createQuery()
            ->select('V.id', 'V.topic', 'V.begin', 'V.end', 'R.color')
            ->from('reservation V')
            ->join('rooms R', 'INNER', array('R.id', 'V.room_id'))
            ->where(array('V.status', 1))
            ->andWhere(array(
                Sql::create("(DATE(V.`begin`)<='$year-$month-1' AND DATE(V.`end`)>'$next')"),
                Sql::create("(YEAR(V.`begin`)='$year' AND MONTH(V.`begin`)='$month')"),
                Sql::create("(YEAR(V.`end`)='$year' AND MONTH(V.`end`)='$month')")
            ), 'OR')
            ->order('V.begin')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            $events[] = array(
                'id' => $item->id.'_booking',
                'title' => self::title($item),
                'start' => $item->begin,
                'end' => $item->end,
                'color' => $item->color,
                'class' => 'icon-calendar'
            );
        }
    }

    /**
     * คืนค่าเวลาจอง
     *
     * @param object $item
     *
     * @return string
     */
    private static function title($item)
    {
        if (
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $item->begin, $begin) &&
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $item->end, $end)
        ) {
            if ($begin[1] == $end[1]) {
                $return = '{LNG_Time} '.Date::format($item->begin, 'TIME_FORMAT').' {LNG_to} '.Date::format($item->end, 'TIME_FORMAT');
            } else {
                $return = Date::format($item->begin).' {LNG_to} '.Date::format($item->end);
            }
            return Language::trans($return).' '.Text::unhtmlspecialchars($item->topic);
        }
    }
}
