<?php
/**
 * @filesource modules/booking/views/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Booking\Settings;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=booking-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มตั้งค่า
     *
     * @return string
     */
    public function render()
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/booking/model/settings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset');
        // booking_w
        $fieldset->add('text', array(
            'id' => 'booking_w',
            'labelClass' => 'g-input icon-width',
            'itemClass' => 'item',
            'label' => '{LNG_Size of} {LNG_Image} ({LNG_Width})',
            'comment' => '{LNG_Image size is in pixels} ({LNG_resized automatically})',
            'value' => isset(self::$cfg->booking_w) ? self::$cfg->booking_w : 500
        ));
        // booking_status
        $fieldset->add('select', array(
            'id' => 'booking_status',
            'labelClass' => 'g-input icon-valid',
            'itemClass' => 'item',
            'label' => '{LNG_Initial booking status}',
            'options' => Language::get('BOOKING_STATUS'),
            'value' => self::$cfg->booking_status
        ));
        // booking_approving
        $fieldset->add('select', array(
            'id' => 'booking_approving',
            'labelClass' => 'g-input icon-write',
            'itemClass' => 'item',
            'label' => '{LNG_Approving/editing reservations}',
            'options' => Language::get('APPROVING_RESERVATIONS'),
            'value' => self::$cfg->booking_approving
        ));
        // booking_cancellation
        $fieldset->add('select', array(
            'id' => 'booking_cancellation',
            'labelClass' => 'g-input icon-warning',
            'itemClass' => 'item',
            'label' => '{LNG_Cancellation}',
            'options' => Language::get('CANCEL_RESERVATIONS'),
            'value' => self::$cfg->booking_cancellation
        ));
        // booking_delete
        $fieldset->add('select', array(
            'id' => 'booking_delete',
            'labelClass' => 'g-input icon-delete',
            'itemClass' => 'item',
            'label' => '{LNG_Delete items that have been canceled by the booker}',
            'options' => Language::get('BOOLEANS'),
            'value' => self::$cfg->booking_delete
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-comments',
            'title' => '{LNG_Notification}'
        ));
        // booking_notifications
        $fieldset->add('select', array(
            'id' => 'booking_notifications',
            'labelClass' => 'g-input icon-email',
            'itemClass' => 'item',
            'label' => '{LNG_Notify relevant parties when booking details are modified by customers}',
            'options' => Language::get('BOOLEANS'),
            'value' => self::$cfg->booking_notifications
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
