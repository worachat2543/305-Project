<?php
/**
 * @filesource modules/booking/models/room.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Room;

/**
 * โมเดลสำหรับ (rooms.php)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ห้องประชุม ใส่ลงใน select
     *
     * @return array
     */
    public static function toSelect()
    {
        $query = static::createQuery()
            ->select('id', 'name')
            ->from('rooms')
            ->where(array('published', 1))
            ->order('name')
            ->cacheOn();
        $result = array();
        foreach ($query->execute() as $item) {
            $result[$item->id] = $item->name;
        }
        return $result;
    }
}
