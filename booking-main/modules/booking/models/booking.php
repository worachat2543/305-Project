<?php
/**
 * @filesource modules/booking/models/booking.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Booking;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int   $id
     * @param int   $room_id
     * @param array $login
     *
     * @return object|null
     */
    public static function get($id, $room_id, $login)
    {
        if ($login) {
            if (empty($id)) {
                // ใหม่
                return (object) array(
                    'id' => 0,
                    'room_id' => $room_id,
                    'status' => 0,
                    'today' => 0,
                    'name' => $login['name'],
                    'member_id' => $login['id'],
                    'phone' => $login['phone']
                );
            } else {
                // แก้ไข อ่านรายการที่เลือก
                $sql = Sql::create('(CASE WHEN NOW() BETWEEN V.`begin` AND V.`end` THEN 1 WHEN NOW() > V.`end` THEN 2 ELSE 0 END) AS `today`');
                $query = static::createQuery()
                    ->from('reservation V')
                    ->join('user U', 'LEFT', array('U.id', 'V.member_id'))
                    ->where(array('V.id', $id));
                $select = array('V.*', 'U.name', 'U.phone', $sql);
                $n = 1;
                foreach (Language::get('BOOKING_SELECT', array()) + Language::get('BOOKING_OPTIONS', array()) as $key => $label) {
                    $query->join('reservation_data M'.$n, 'LEFT', array(array('M'.$n.'.reservation_id', 'V.id'), array('M'.$n.'.name', $key)));
                    $select[] = 'M'.$n.'.value '.$key;
                    ++$n;
                }
                return $query->first($select);
            }
        }
        // ไม่ได้เข้าระบบ
        return null;
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (booking.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, สมาชิก
        if ($request->initSession() && $request->isSafe()) {
            if ($login = Login::isMember()) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'room_id' => $request->post('room_id')->toInt(),
                        'attendees' => $request->post('attendees')->toInt(),
                        'topic' => $request->post('topic')->topic(),
                        'comment' => $request->post('comment')->textarea(),
                        'begin' => $request->post('begin')->date(),
                        'end' => $request->post('end')->date()
                    );
                    $user = array(
                        'phone' => $request->post('phone')->topic()
                    );
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($request->post('id')->toInt(), 0, $login);
                    // ใหม่, เจ้าของ ยังไม่ได้อนุมัติ และ วันนี้ไม่ใช่วันจอง
                    if ($index && ($index->id == 0 || ($login['id'] == $index->member_id && $index->status == 0 && $index->today == 0))) {
                        if ($save['attendees'] == 0) {
                            // ไม่ได้กรอก attendees
                            $ret['ret_attendees'] = 'Please fill in';
                        }
                        if ($save['topic'] == '') {
                            // ไม่ได้กรอก topic
                            $ret['ret_topic'] = 'Please fill in';
                        }
                        if ($user['phone'] == '') {
                            // ไม่ได้กรอก phone
                            $ret['ret_phone'] = 'Please fill in';
                        }
                        if (empty($save['begin'])) {
                            // ไม่ได้กรอก begin
                            $ret['ret_begin'] = 'Please fill in';
                        } else {
                            $save['begin'] .= ':01';
                        }
                        if (empty($save['end'])) {
                            // ไม่ได้กรอก end
                            $ret['ret_end'] = 'Please fill in';
                        } else {
                            $save['end'] .= ':00';
                        }
                        if ($save['end'] > $save['begin']) {
                            // ตรวจสอบห้องว่าง
                            if (!\Booking\Checker\Model::availability($save)) {
                                $ret['ret_begin'] = Language::get('Room are not available at select time');
                            }
                        } else {
                            // วันที่ ไม่ถูกต้อง
                            $ret['ret_end'] = Language::get('End date must be greater than begin date');
                        }
                        $datas = array();
                        // ตัวแปรสำหรับตรวจสอบการแก้ไข
                        $options_check = array();
                        foreach (Language::get('BOOKING_SELECT', array()) as $key => $label) {
                            $options_check[] = $key;
                            $value = $request->post($key)->toInt();
                            if ($value > 0) {
                                $datas[$key] = $value;
                            }
                        }
                        foreach (Language::get('BOOKING_TEXT', array()) as $key => $label) {
                            $options_check[] = $key;
                            $value = $request->post($key)->topic();
                            if ($value != '') {
                                $datas[$key] = $value;
                            }
                        }
                        foreach (Language::get('BOOKING_OPTIONS', array()) as $key => $label) {
                            $options_check[] = $key;
                            $values = $request->post($key, array())->toInt();
                            if (!empty($values)) {
                                $datas[$key] = implode(',', $values);
                            }
                        }
                        if (empty($ret)) {
                            // Database
                            $db = $this->db();
                            if ($index->id == 0) {
                                // ใหม่
                                $save['status'] = self::$cfg->booking_status;
                                if ($save['status'] == 0) {
                                    // รอตรวจสอบ
                                    $save['approver'] = 0;
                                    $save['approved_date'] = null;
                                } else {
                                    $save['approver'] = $login['id'];
                                    $save['approved_date'] = date('Y-m-d H:i:s');
                                }
                                $save['member_id'] = $login['id'];
                                $save['create_date'] = date('Y-m-d H:i:s');
                                $index->id = $db->insert($this->getTableName('reservation'), $save);
                                // ใหม่ ส่งอีเมลเสมอ
                                $changed = true;
                                // กลับไปหน้ารายการจอง
                                $params = array('module' => 'booking', 'status' => $save['status']);
                            } else {
                                // แก้ไข
                                $db->update($this->getTableName('reservation'), $index->id, $save);
                                // ตรวจสอบการแก้ไข
                                $changed = false;
                                if (self::$cfg->booking_notifications == 1) {
                                    foreach ($save as $key => $value) {
                                        if ($value != $index->{$key}) {
                                            $changed = true;
                                            break;
                                        }
                                    }
                                    if (!$changed) {
                                        foreach ($options_check as $key) {
                                            if (isset($datas[$key])) {
                                                if ($datas[$key] != $index->{$key}) {
                                                    $changed = true;
                                                    break;
                                                }
                                            } elseif ($index->{$key} != '') {
                                                $changed = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                                $save['member_id'] = $index->member_id;
                                $save['status'] = $index->status;
                                // กลับไปหน้ารายการจอง
                                $params = array('module' => 'booking');
                            }
                            if ($index->phone != $user['phone']) {
                                if (self::$cfg->booking_notifications) {
                                    $changed = true;
                                }
                                // อัปเดตเบอร์โทรสมาชิก
                                $db->update($this->getTableName('user'), $login['id'], $user);
                            }
                            // รายละเอียดการจอง
                            $reservation_data = $this->getTableName('reservation_data');
                            $db->delete($reservation_data, array('reservation_id', $index->id), 0);
                            foreach ($datas as $key => $value) {
                                if ($value != '') {
                                    $db->insert($reservation_data, array(
                                        'reservation_id' => $index->id,
                                        'name' => $key,
                                        'value' => $value
                                    ));
                                }
                                $save[$key] = $value;
                            }
                            if (empty($ret) && $changed) {
                                // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                                $save['id'] = $index->id;
                                $ret['alert'] = \Booking\Email\Model::send($save);
                            } else {
                                // ไม่ส่งอีเมล
                                $ret['alert'] = Language::get('Saved successfully');
                            }
                            $ret['location'] = $request->getUri()->postBack('index.php', $params);
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
