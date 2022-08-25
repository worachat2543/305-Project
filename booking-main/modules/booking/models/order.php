<?php
/**
 * @filesource modules/booking/models/order.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Order;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=booking-order
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        $query = static::createQuery()
            ->from('reservation V')
            ->join('user U', 'LEFT', array('U.id', 'V.member_id'))
            ->join('user A', 'LEFT', array('A.id', 'V.approver'))
            ->where(array('V.id', $id));
        $select = array('V.*', 'U.name', 'U.phone', 'U.username', 'A.name approver_name');
        $n = 1;
        foreach (Language::get('BOOKING_SELECT', array()) + Language::get('BOOKING_OPTIONS', array()) + Language::get('BOOKING_TEXT', array()) as $key => $label) {
            $query->join('reservation_data M'.$n, 'LEFT', array(array('M'.$n.'.reservation_id', 'V.id'), array('M'.$n.'.name', $key)));
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }
        return $query->first($select);
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (order.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, สามารถอนุมัติได้
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_approve_room')) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'room_id' => $request->post('room_id')->toInt(),
                        'attendees' => $request->post('attendees')->toInt(),
                        'topic' => $request->post('topic')->topic(),
                        'comment' => $request->post('comment')->textarea(),
                        'begin' => $request->post('begin')->date(),
                        'end' => $request->post('end')->date(),
                        'status' => $request->post('status')->toInt(),
                        'reason' => $request->post('reason')->topic()
                    );
                    $datas = array();
                    foreach (Language::get('BOOKING_SELECT', array()) as $key => $label) {
                        $value = $request->post($key)->toInt();
                        if ($value > 0) {
                            $datas[$key] = $value;
                        }
                    }
                    foreach (Language::get('BOOKING_TEXT', array()) as $key => $label) {
                        $value = $request->post($key)->topic();
                        if ($value != '') {
                            $datas[$key] = $value;
                        }
                    }
                    foreach (Language::get('BOOKING_OPTIONS', array()) as $key => $label) {
                        $values = $request->post($key, array())->toInt();
                        if (!empty($values)) {
                            $datas[$key] = implode(',', $values);
                        }
                    }
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($request->post('id')->toInt());
                    if ($index) {
                        if ($save['attendees'] == 0) {
                            // ไม่ได้กรอก attendees
                            $ret['ret_attendees'] = 'Please fill in';
                        }
                        if ($save['topic'] == '') {
                            // ไม่ได้กรอก topic
                            $ret['ret_topic'] = 'Please fill in';
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
                            // ตรวจสอบห้องว่าง เฉพาะรายการที่จะอนุมัติ
                            if ($save['status'] == self::$cfg->booking_approved_status && !\Booking\Checker\Model::availability($save, $index->id)) {
                                $ret['ret_begin'] = Language::get('Room are not available at select time');
                            }
                        } else {
                            // วันที่ ไม่ถูกต้อง
                            $ret['ret_end'] = Language::get('End date must be greater than begin date');
                        }
                        // ตาราง
                        $reservation_table = $this->getTableName('reservation');
                        $reservation_data = $this->getTableName('reservation_data');
                        // Database
                        $db = $this->db();
                        if (empty($ret)) {
                            $save['approver'] = $index->approver;
                            $save['approved_date'] = $index->approved_date;
                            if ($save['status'] != $index->status) {
                                if ($save['status'] == 0) {
                                    $save['approver'] = 0;
                                    $save['approved_date'] = null;
                                } else {
                                    $save['approver'] = $login['id'];
                                    $save['approved_date'] = date('Y-m-d H:i:s');
                                }
                            }
                            // save
                            $db->update($reservation_table, $index->id, $save);
                            // รายละเอียดการจอง
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
                            if ($request->post('send_mail')->toBoolean()) {
                                // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                                $save['id'] = $index->id;
                                $save['member_id'] = $index->member_id;
                                $ret['alert'] = \Booking\Email\Model::send($save);
                            } else {
                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                            }
                            $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'booking-report', 'status' => $index->status));
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
