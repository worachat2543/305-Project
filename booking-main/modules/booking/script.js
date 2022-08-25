function initBookingCalendar(min, max) {
  var y = new Date().getFullYear();
  new Calendar("booking-calendar", {
    minYear: Math.min(min, y),
    maxYear: Math.max(max, y),
    url: WEB_URL + "index.php/booking/model/calendar/toJSON",
    onclick: function(d) {
      send(
        WEB_URL + "index.php/booking/model/index/action",
        "action=detail&id=" + this.id,
        doFormSubmit
      );
    }
  });
  forEach($E('room_links').getElementsByTagName('a'), function() {
    callClick(this, function() {
      send(
        WEB_URL + "index.php/booking/model/rooms/action",
        'action=detail&id=' + this.id.replace('room_', ''),
        doFormSubmit,
        this
      );
    });
  });
}

function initBookingOrder() {
  initCalendarRange("begin", "end");
  if ($E('send_mail')) {
    var status = $E('status').value;
    $G('status').addEvent('change', function() {
      $E('send_mail').checked = status != this.value;
    });
  }
}

function initCalendarRange(minDate, maxDate, minChanged) {
  var loading = true;
  if ($E(minDate) && $E(maxDate)) {
    $G(minDate).addEvent('change', function() {
      if (loading == false && this.value != '') {
        $E(maxDate).min = this.value;
        if (Object.isFunction(minChanged)) {
          minChanged.call($E(minDate), $E(maxDate));
        }
      }
    });
    $G(maxDate).addEvent('change', function() {
      if (loading == false && this.value != '') {
        $E(minDate).max = this.value;
      }
    });
    window.setTimeout(function() {
      loading = false;
    }, 1);
  }
}