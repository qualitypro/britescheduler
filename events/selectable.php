<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.css">
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var calendarEl = document.getElementById('calendar');
      var selectedEvent;
      var calendar;

      function initCalendar(events) {
        calendar = new FullCalendar.Calendar(calendarEl, {
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
          },
          navLinks: true,
          selectable: true,
          selectMirror: true,
          select: function (arg) {
            openAddDialog(arg.start, arg.end);
            calendar.unselect();
          },
          eventClick: function (arg) {
            selectedEvent = arg.event;
            openUpdateDialog();
          },
          editable: true,
          dayMaxEvents: true,
          events: events
        });

        calendar.render();

        return calendar;
      }

      function openAddDialog(start, end) {
        var dialog = $("#event-dialog").dialog({
          modal: true,
          width: 400,
          title: "Add Event",
          open: function () {
            $("#event-id").val('');
            $("#user-id").val('');
            $("#start-date").datepicker({
              dateFormat: 'mm/dd/yy'
            }).val(formatDate(start));
            $("#end-date").datepicker({
              dateFormat: 'mm/dd/yy'
            }).val(formatDate(end));
            $("#start-time").val(formatTime(start));
            $("#end-time").val(formatTime(end));
            $("#description").val('');
            $("#repeat-type").val('0');
            $("#is-public").prop('checked', false);
            $("#is-active").prop('checked', false);
          },
          buttons: {
            "Add": function () {
              addEvent();
              dialog.dialog("close");
            },
            "Cancel": function () {
              dialog.dialog("close");
            }
          }
        });
      }

      function openUpdateDialog() {
        var dialog = $("#event-dialog").dialog({
          modal: true,
          width: 400,
          title: "Edit Event",
          open: function () {
            $("#event-id").val(selectedEvent.extendedProps.event_id);
            $("#user-id").val(selectedEvent.extendedProps.user_id);
            $("#start-date").datepicker({
              dateFormat: 'mm/dd/yy'
            }).val(formatDate(selectedEvent.start));
            $("#end-date").datepicker({
              dateFormat: 'mm/dd/yy'
            }).val(formatDate(selectedEvent.end));
            $("#start-time").val(formatTime(selectedEvent.start));
            $("#end-time").val(formatTime(selectedEvent.end));
            $("#description").val(selectedEvent.extendedProps.description);
            $("#repeat-type").val(selectedEvent.extendedProps.repeat_type);
            $("#is-public").prop('checked', selectedEvent.extendedProps.is_public);
            $("#is-active").prop('checked', selectedEvent.extendedProps.is_active);
          },
          buttons: {
            "Update": function () {
              updateEvent();
              dialog.dialog("close");
            },
            "Cancel": function () {
              dialog.dialog("close");
            }
          }
        });
      }

      function formatDate(date) {
        return date ? (date.getMonth() + 1).toString().padStart(2, '0') + '/' + date.getDate().toString().padStart(2, '0') + '/' + date.getFullYear() : '';
      }

      function formatTime(date) {
        return date ? date.toISOString().split('T')[1].slice(0, 5) : '';
      }

      function addEvent() {
        var eventData = {
          user_id: $("#user-id").val(),
          start_date: $("#start-date").val(),
          end_date: $("#end-date").val(),
          start_time: $("#start-time").val(),
          end_time: $("#end-time").val(),
          description: $("#description").val(),
          repeat_type: $("#repeat-type").val(),
          is_public: $("#is-public").is(':checked'),
          is_active: $("#is-active").is(':checked')
        };

        $.ajax({
          url: "save_event.php",
          type: "POST",
          data: eventData,
          success: function (response) {
            fetchAndRenderEvents();
          },
          error: function (error) {
            console.error("Error adding event:", error);
          }
        });
      }

      function updateEvent() {
        var eventData = {
          event_id: $("#event-id").val(),
          user_id: $("#user-id").val(),
          start_date: $("#start-date").val(),
          end_date: $("#end-date").val(),
          start_time: $("#start-time").val(),
          end_time: $("#end-time").val(),
          description: $("#description").val(),
          repeat_type: $("#repeat-type").val(),
          is_public: $("#is-public").is(':checked'),
          is_active: $("#is-active").is(':checked')
        };

        $.ajax({
          url: "save_event.php",
          type: "POST",
          data: eventData,
          success: function (response) {
            fetchAndRenderEvents(true);
          },
          error: function (error) {
            console.error("Error updating event:", error);
          }
        });
      }

      function fetchAndRenderEvents(setView) {
        fetch('events.json.php')
          .then(response => response.json())
          .then(events => {
            if (calendar) {
              calendar.destroy();
            }
            calendar = initCalendar(events.events);
            if (setView) {
              if (selectedEvent) {
                calendar.gotoDate(selectedEvent.start);
              } else if (events.events.length > 0) {
                calendar.gotoDate(events.events[events.events.length - 1].start);
              }
            }
          })
          .catch(error => console.error('Error fetching events:', error));
      }

      fetchAndRenderEvents();
    });
  </script>

  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, Helvetica Neue, Helvetica, sans-serif;
      font-size: 14px;
    }

    #calendar {
      width: 100%;
      height: 100vh;
    }

    form {
      max-width: 400px;
      margin: 20px auto;
    }

    form label {
      display: block;
      margin-bottom: 5px;
    }

    form input,
    form select,
    form textarea {
      width: 100%;
      margin-bottom: 10px;
      padding: 8px;
      box-sizing: border-box;
    }

    form input[type="checkbox"] {
      width: auto;
    }

    .ui-dialog {
      box-sizing: border-box;
    }
  </style>
</head>

<body>

  <div id='calendar'></div>
  <div id="event-dialog" style="display: none;">
    <form>
      <input type="hidden" id="event-id" name="event_id">
      <input type="hidden" id="user-id" name="user_id">
      <label for="start-date">Start Date:</label>
      <input type="text" id="start-date" name="start_date" required>
      <label for="end-date">End Date:</label>
      <input type="text" id="end-date" name="end_date">
      <label for="start-time">Start Time:</label>
      <input type="time" id="start-time" name="start_time" required>
      <label for="end-time">End Time:</label>
      <input type="time" id="end-time" name="end_time">
      <label for="description">Description:</label>
      <textarea id="description" name="description" required></textarea>
      <label for="repeat-type">Repeat Type:</label>
      <select id="repeat-type" name="repeat_type">
        <option value="0">None</option>
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
      </select>
      <label for="is-public">Is Public:</label>
      <input type="checkbox" id="is-public" name="is_public">
      <label for="is-active">Is Active:</label>
      <input type="checkbox" id="is-active" name="is_active">
    </form>
  </div>

</body>

</html>
