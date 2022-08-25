<?php
/**
 * @filesource modules/booking/views/order.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Order;

use Kotchasan\Date;
use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=booking-order
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มแก้ไข การจอง (admin)
     *
     * @param object $index
     *
     * @return string
     */
    public function render($index)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/booking/model/order/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Details of} {LNG_Booking}'
        ));
        $groups = $fieldset->add('groups');
        // room_id
        $groups->add('select', array(
            'id' => 'room_id',
            'labelClass' => 'g-input icon-office',
            'itemClass' => 'width50',
            'label' => '{LNG_Room name}',
            'options' => \Booking\Room\Model::toSelect(),
            'value' => $index->room_id
        ));
        // attendees
        $groups->add('number', array(
            'id' => 'attendees',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'width50',
            'label' => '{LNG_Attendees number}',
            'value' => $index->attendees
        ));
        // topic
        $fieldset->add('text', array(
            'id' => 'topic',
            'labelClass' => 'g-input icon-edit',
            'itemClass' => 'item',
            'label' => '{LNG_Topic}',
            'maxlength' => 150,
            'value' => isset($index->topic) ? $index->topic : ''
        ));
        $groups = $fieldset->add('groups');
        // name
        $groups->add('text', array(
            'id' => 'name',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Contact name}',
            'disabled' => true,
            'value' => $index->name
        ));
        // phone
        $groups->add('text', array(
            'id' => 'phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'disabled' => true,
            'value' => $index->phone
        ));
        // ตัวเลือก select
        $category = \Booking\Category\Model::init();
        foreach (Language::get('BOOKING_SELECT', array()) as $key => $label) {
            if (!$category->isEmpty($key)) {
                $fieldset->add('select', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-menus',
                    'itemClass' => 'item',
                    'label' => $label,
                    'options' => $category->toSelect($key),
                    'value' => isset($index->{$key}) ? $index->{$key} : 0
                ));
            }
        }
        // textbox
        foreach (Language::get('BOOKING_TEXT', array()) as $key => $label) {
            $fieldset->add('text', array(
                'id' => $key,
                'labelClass' => 'g-input icon-edit',
                'itemClass' => 'item',
                'label' => $label,
                'maxlength' => 250,
                'value' => isset($index->{$key}) ? $index->{$key} : ''
            ));
        }
        $groups = $fieldset->add('groups');
        // begin
        $groups->add('datetime', array(
            'id' => 'begin',
            'label' => '{LNG_Begin date}/{LNG_Begin time}',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'title' => '{LNG_Begin date}',
            'value' => $index->begin
        ));
        // end
        $groups->add('datetime', array(
            'id' => 'end',
            'label' => '{LNG_End date}/{LNG_End time}',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'title' => '{LNG_End date}',
            'value' => $index->end
        ));
        // ตัวเลือก checkbox
        foreach (Language::get('BOOKING_OPTIONS', array()) as $key => $label) {
            if (!$category->isEmpty($key)) {
                $fieldset->add('checkboxgroups', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-list',
                    'itemClass' => 'item',
                    'label' => $label,
                    'options' => $category->toSelect($key),
                    'value' => isset($index->{$key}) ? explode(',', $index->{$key}) : array()
                ));
            }
        }
        // comment
        $fieldset->add('textarea', array(
            'id' => 'comment',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Other}',
            'rows' => 3,
            'value' => $index->comment
        ));
        // status
        $fieldset->add('select', array(
            'id' => 'status',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Status}',
            'options' => Language::get('BOOKING_STATUS'),
            'value' => $index->status
        ));
        if ($index->status > 0) {
            $groups = $fieldset->add('groups');
            // approver
            $groups->add('text', array(
                'id' => 'approver',
                'label' => '{LNG_Approver}',
                'labelClass' => 'g-input icon-customer',
                'itemClass' => 'width50',
                'disabled' => true,
                'value' => $index->approver_name
            ));
            // approved_date
            $groups->add('text', array(
                'id' => 'approved_date',
                'label' => '{LNG_Approval date}',
                'labelClass' => 'g-input icon-calendar',
                'itemClass' => 'width50',
                'disabled' => true,
                'value' => Date::format($index->approved_date)
            ));
        }
        // reason
        $fieldset->add('text', array(
            'id' => 'reason',
            'labelClass' => 'g-input icon-question',
            'itemClass' => 'item',
            'label' => '{LNG_Reason}',
            'maxlength' => 128,
            'value' => $index->reason
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button ok large icon-save',
            'value' => '{LNG_Save}'
        ));
        $fieldset->add('checkbox', array(
            'id' => 'send_mail',
            'labelClass' => 'inline-block middle',
            'label' => '&nbsp;{LNG_Send a notification message to the person concerned}',
            'value' => 1
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $index->id
        ));
        // Javascript
        $form->script('initBookingOrder();');
        // คืนค่า HTML
        return $form->render();
    }
}
