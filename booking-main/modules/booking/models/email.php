<?php
/**
 * @filesource modules/booking/models/email.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Email;

use Kotchasan\Date;
use Kotchasan\Language;

/**
 * ส่งอีเมลและ LINE ไปยังผู้ที่เกี่ยวข้อง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ส่งอีเมลและ LINE แจ้งการทำรายการ
     *
     * @param array $order
     *
     * @return string
     */
    public static function send($order)
    {
        $lines = array();
        $emails = array();
        $name = '';
        $mailto = '';
        $line_uid = '';
        // ตรวจสอบรายชื่อผู้รับ
        if (self::$cfg->demo_mode) {
            // โหมดตัวอย่าง ส่งหาผู้ทำรายการและแอดมินเท่านั้น
            $where = array(
                array('id', array($order['member_id'], 1))
            );
        } else {
            // ส่งหาผู้ทำรายการและผู้ที่เกี่ยวข้อง
            $where = array(
                array('id', $order['member_id']),
                array('status', 1),
                array('permission', 'LIKE', '%,can_approve_room,%')
            );
        }
        // ตรวจสอบรายชื่อผู้รับ
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'username', 'name', 'line_uid')
            ->from('user')
            ->where(array('active', 1))
            ->andWhere($where, 'OR')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            if ($item->id == $order['member_id']) {
                // ผู้จอง
                $name = $item->name;
                $mailto = $item->username;
                $line_uid = $item->line_uid;
            } else {
                // เจ้าหน้าที่
                $emails[] = $item->name.'<'.$item->username.'>';
                if ($item->line_uid != '') {
                    $lines[] = $item->line_uid;
                }
            }
        }
        // สถานะการจอง
        $status = Language::find('BOOKING_STATUS', '', $order['status']);
        // ข้อมูลห้อง
        $room = self::room($order['room_id'], $order['approver']);
        // ข้อความ
        $msg = array(
            '{LNG_Book a meeting} ['.self::$cfg->web_title.']',
            '{LNG_Contact name} : '.$name,
            '{LNG_Room name} : '.$room->name,
            '{LNG_Attendees number} : '.$order['attendees'],
            '{LNG_Topic} : '.$order['topic'],
            '{LNG_Booking date} : '.\Booking\Tools\View::toDate($order),
            '{LNG_Status} : '.$status
        );
        if (!empty($order['approved_date'])) {
            $msg[] = '{LNG_Approver} : '.$room->approver_name;
            $msg[] = '{LNG_Approval date} : '.Date::format($order['approved_date']);
        }
        if (!empty($order['reason'])) {
            $msg[] = '{LNG_Reason} : '.$order['reason'];
        }
        $msg[] = 'URL : '.WEB_URL.'index.php?module=booking';
        // ข้อความของ user
        $msg = Language::trans(implode("\n", $msg));
        // ข้อความของแอดมิน
        $admin_msg = $msg.'-order&id='.$order['id'];
        // ส่งข้อความ
        $ret = array();
        // ส่ง LINE
        if (!empty(self::$cfg->line_api_key)) {
            $err = \Gcms\Line::send($admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        // LINE ส่วนตัว
        if (!empty($lines)) {
            $err = \Gcms\Line::sendTo($lines, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty($line_uid)) {
            $err = \Gcms\Line::sendTo($line_uid, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (self::$cfg->noreply_email != '') {
            // หัวข้ออีเมล
            $subject = '['.self::$cfg->web_title.'] '.Language::get('Book a meeting').' '.$status;
            // ส่งอีเมลไปยังผู้ทำรายการเสมอ
            $err = \Kotchasan\Email::send($name.'<'.$mailto.'>', self::$cfg->noreply_email, $subject, nl2br($msg));
            if ($err->error()) {
                // คืนค่า error
                $ret[] = strip_tags($err->getErrorMessage());
            }
            // รายละเอียดในอีเมล (แอดมิน)
            $admin_msg = nl2br($admin_msg);
            foreach ($emails as $item) {
                // ส่งอีเมล
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $admin_msg);
                if ($err->error()) {
                    // คืนค่า error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            // ส่งอีเมลสำเร็จ หรือ error การส่งเมล
            return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
        } else {
            // ไม่มีอีเมลต้องส่ง
            return Language::get('Saved successfully');
        }
    }

    /**
     * คืนค่าข้อมูลห้อง
     *
     * @param int $room_id
     * @param int $approver
     *
     * @return object
     */
    private static function room($room_id, $approver)
    {
        // เลขห้อง
        $select = array('V.name');
        if ($approver > 0) {
            $q1 = \Kotchasan\Model::createQuery()
                ->select('name')
                ->from('user')
                ->where(array('id', $approver));
            $select[] = array(array($q1, 'approver_name'));
        } else {
            $select[] = '"" approver_name';
        }
        // Query
        $query = \Kotchasan\Model::createQuery()
            ->from('rooms V')
            ->where(array('V.id', $room_id))
            ->cacheOn();
        return $query->first($select);
    }
}
