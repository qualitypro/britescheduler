<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <script src='./dist/index.global.js'></script>
  <script>

    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');

      // Fetch events from events.json.php
      fetch('events.json.php')
        .then(response => response.json())
        .then(events => {
          // Initialize FullCalendar with the fetched events
          var calendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            navLinks: true,
            selectable: true,
            selectMirror: true,
            select: function(arg) {
              var eventId = prompt('Event ID:');
              if (eventId) {
                calendar.addEvent({
                  title: 'New Event',
                  start: arg.start,
                  end: arg.end,
                  allDay: arg.allDay,
                  extendedProps: {
                    event_id: eventId
                  }
                });
              }
              calendar.unselect();
            },
            eventClick: function(arg) {
              // Prompt only the event_id after clicking an event
              alert(`Event ID: ${arg.event.extendedProps.event_id}`);
            },
            editable: true,
            dayMaxEvents: true,
            events: events.events // Use the fetched events
          });

          calendar.render();
        })
        .catch(error => console.error('Error fetching events:', error));
    });

  </script>
  <style>
    body {
      margin: 40px 10px;
      padding: 0;
      font-family: Arial, Helvetica Neue, Helvetica, sans-serif;
      font-size: 14px;
    }

    #calendar {
      max-width: 1100px;
      margin: 0 auto;
    }
  </style>
</head>
<body>

  <div id='calendar'></div>
  <div id="event-dialog" style="display: none;">
    <form>
      <input type="hidden" id="event-id" name="event_id">
      <input type="hidden" id="user-id" name="user_id">
      Start Date: <input type="date" id="start-date" name="start_date" required><br>
      End Date: <input type="date" id="end-date" name="end_date"><br>
      Start Time: <input type="time" id="start-time" name="start_time" required><br>
      End Time: <input type="time" id="end-time" name="end_time"><br>
      Description: <textarea id="description" name="description" required></textarea><br>
      Repeat Type: <input type="text" id="repeat-type" name="repeat_type"><br>
      Is Public: <input type="checkbox" id="is-public" name="is_public"><br>
      Is Active: <input type="checkbox" id="is-active" name="is_active"><br>
    </form>
  </div>
</body>
</html>
