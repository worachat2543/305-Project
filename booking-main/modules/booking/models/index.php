<?php
/**
 * @filesource modules/booking/models/index.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Index;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('V.member_id', $params['member_id'])
        );
        if ($params['room_id'] > 0) {
            $where[] = array('V.room_id', $params['room_id']);
        }
        if ($params['status'] > -1) {
            $where[] = array('V.status', $params['status']);
        }
        if (!empty($params['from'])) {
            $where[] = Sql::BETWEEN($params['from'], Sql::DATE('V.begin'), Sql::DATE('V.end'));
        }
        $sql = Sql::create('(CASE WHEN NOW() BETWEEN V.`begin` AND V.`end` THEN 1 WHEN NOW() > V.`end` THEN 2 ELSE 0 END) AS `today`');
        $select = array('V.topic', 'V.id', 'V.room_id');
        $query = static::createQuery()
            ->from('reservation V')
            ->join('rooms R', 'LEFT', array('R.id', 'V.room_id'));
        $n = 1;
        foreach (Language::get('BOOKING_SELECT', array()) as $key => $label) {
            $on = array(
                array('M'.$n.'.reservation_id', 'V.id'),
                array('M'.$n.'.name', $key)
            );
            $query->join('reservation_data M'.$n, 'LEFT', $on);
            $select[] = 'M'.$n.'.value '.$label;
            ++$n;
        }
        $select = array_merge($select, array('V.begin', 'V.end', 'V.status', 'V.reason', $sql, 'R.color'));
        return $query->select($select)->where($where);
    }

    /**
     * รับค่าจาก action (index.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = array();
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            // สมาชิก
            $login = Login::isMember();
            // ค่าที่ส่งมา
            $action = $request->post('action')->toString();
            // Database
            $db = $this->db();
            // Table
            $reservation_table = $this->getTableName('reservation');
            if ($action === 'cancel' && $login) {
                // ยกเลิกการจอง
                $q1 = Sql::create('(CASE WHEN NOW() BETWEEN V.`begin` AND V.`end` THEN 1 WHEN NOW() > V.`end` THEN 2 ELSE 0 END) AS `today`');
                $search = static::createQuery()
                    ->from('reservation V')
                    ->where(array('V.id', $request->post('id')->toInt()))
                    ->toArray()
                    ->first('V.*', $q1);
                if ($search && $login['id'] == $search['member_id']) {
                    // ยกเลิกการจองโดยผู้จอง
                    $search['status'] = 3;
                    $save['approver'] = $login['id'];
                    $save['approved_date'] = date('Y-m-d H:i:s');
                    // อัปเดต
                    $db->update($reservation_table, $search['id'], array('status' => $search['status']));
                    // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                    $ret['alert'] = \Booking\Email\Model::send($search);
                    // reload
                    $ret['location'] = 'reload';
                }
            } elseif ($action === 'delete' && $login && !empty(self::$cfg->booking_delete)) {
                // ลบรายการที่ยกเลิกการจองแล้ว
                $search = $db->first($reservation_table, $request->post('id')->toInt());
                if ($search && $search->status == 3 && $login['id'] == $search->member_id) {
                    // ลบ
                    $db->delete($reservation_table, $search->id);
                    // ลบเรียบร้อย
                    $ret['alert'] = Language::get('Successfully deleted');
                    // reload
                    $ret['location'] = 'reload';
                }
            } elseif ($action === 'detail') {
                // แสดงรายละเอียดการจอง
                $search = $this->bookDetail($request->post('id')->toInt());
                if ($search) {
                    $ret['modal'] = \Booking\Detail\View::create()->booking($search);
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }

    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล array ไม่พบคืนค่า null
     *
     * @param int $id
     *
     * @return array|null
     */
    public function bookDetail($id)
    {
        $query = $this->db()->createQuery()
            ->from('reservation V')
            ->join('rooms R', 'LEFT', array('R.id', 'V.room_id'))
            ->join('user U', 'LEFT', array('U.id', 'V.member_id'))
            ->join('user A', 'LEFT', array('A.id', 'V.approver'))
            ->where(array('V.id', $id))
            ->toArray();
        $select = array('V.*', 'R.name', 'U.name contact', 'U.phone', 'R.color', 'A.name approver_name');
        $n = 1;
        foreach (Language::get('ROOM_CUSTOM_TEXT', array()) as $key => $label) {
            $query->join('rooms_meta M'.$n, 'LEFT', array(array('M'.$n.'.room_id', 'R.id'), array('M'.$n.'.name', $key)));
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }
        foreach (Language::get('BOOKING_SELECT', array()) + Language::get('BOOKING_OPTIONS', array()) + Language::get('BOOKING_TEXT', array()) as $key => $label) {
            $query->join('reservation_data M'.$n, 'LEFT', array(array('M'.$n.'.reservation_id', 'V.id'), array('M'.$n.'.name', $key)));
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }
        return $query->first($select);
    }

    /**
     * ฟังก์ชั่นตรวจสอบว่าสามารถยกเลิกได้หรือไม่
     *
     * @param int $today
     * @param int $status
     *
     * @return bool
     */
    public static function canCancle($today, $status)
    {
        if (self::$cfg->booking_cancellation == 2) {
            // ก่อนหมดเวลาจอง
            return in_array($today, array(0, 1)) && in_array($status, array(0, 1)) ? true : false;
        } elseif (self::$cfg->booking_cancellation == 1) {
            // ก่อนถึงเวลาจอง
            return $today == 0 && in_array($status, array(0, 1)) ? true : false;
        } else {
            // สถานะรอตรวจสอบ
            return $status == 0 ? true : false;
        }
    }
}
