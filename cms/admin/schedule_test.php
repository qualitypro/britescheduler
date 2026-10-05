<?php
require_once('../config/config.php');
require_once('../includes/header.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get the user ID from the session
$userid = $_SESSION['user_id'];
?>

<!-- Include jQuery and jQuery UI libraries -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<h1>Schedule Event</h1>

<!-- Modal for scheduling/updating an event -->
<div id="eventDialog" title="Schedule/Update Event">
    <form id="eventForm">
        <label for="date">Date:</label>
        <input type="text" name="date" id="selectedDate" readonly>

        <label for="time">Time:</label>
        <input type="time" name="time" id="selectedTime">

        <!-- Existing form fields -->
        <label for="description">Description:</label>
        <textarea name="description" id="description" rows="4" cols="50"></textarea>
        
        <label for="completed_status">Completed Status:</label>
        <select name="completed_status" id="completed_status">
            <option value="1">Yes</option>
            <option value="0">No</option>
        </select>

        <label>Repeating:</label>
        <label><input type="radio" name="repeating" value="none" checked> None</label>
        <label><input type="radio" name="repeating" value="week"> Week</label>
        <label><input type="radio" name="repeating" value="month"> Month</label>
        <label><input type="radio" name="repeating" value="year"> Year</label>

        <input type="hidden" name="userid" value="<?php echo $userid; ?>"> <!-- Change user_id to userid -->

        <button type="button" onclick="saveEvent()">Save Event</button>
        <button type="button" onclick="deleteEvent()">Delete Event</button>
    </form>
</div>

<div id="calendarContainer">
    <h2 id="currentMonth">Calendar</h2>
    <div id="calendar"></div>
    <div id="pagination">
        <button onclick="previousMonth()">Previous</button>
        <button onclick="nextMonth()">Next</button>
    </div>
</div>

<script>
    // Variables to keep track of the current month and year
    var currentMonth = new Date().getMonth() + 1; // Adding 1 because getMonth() returns zero-based month
    var currentYear = new Date().getFullYear();

    // Variables to store the selected event details
    var selectedEventDate = null;
    var selectedEventTime = null;
    var selectedEventDescription = null;

    // AJAX function to update the calendar
    function updateCalendar() {
        $.ajax({
            type: 'GET',
            url: 'ajax_calendar.php',
            data: { month: currentMonth, year: currentYear },
            success: function (response) {
                $('#calendar').html(response);
                makeDatesClickable(); // Make dates clickable after updating the calendar
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    }

    // Function to make dates clickable and show the dialog
    function makeDatesClickable() {
        $('.day').each(function () {
            var date = $(this).data('date');
            var time = $(this).data('time'); // Added to retrieve time
            var description = $(this).data('description');
			var completed_status = $(this).data('completed_status');

            // Append event descriptions to the calendar
            if (description) {
                $(this).append('<div class="event-description">' + description + '</div>');
            }

            $(this).wrap('<a href="#" onclick="handleDateClick(\'' + date + '\', \'' + time + '\', \'' + description + '\'); return false;"></a>');
        });
    }

    // Function to handle date click
    function handleDateClick(date, time, description) {
        selectedEventDate = date;
        selectedEventTime = time;
        selectedEventDescription = description;

        // Populate the dialog fields with the selected event details
        $('#selectedDate').val(date);
        $('#selectedTime').val(time); // Set the time input
        $('#description').val(description);
 		$('#completed_status').val(completed_status);
 
        $('#eventDialog').dialog('open');
    }

    // Function to save/update an event
    function saveEvent() {
        // Collect data from the dialog form
        var eventData = {
            date: $('#selectedDate').val(),
            time: $('#selectedTime').val(),
            description: $('#description').val(),
            completed_status: $('#completed_status').val(),
            repeating: $('input[name=repeating]:checked').val(),
            userid: $('#eventForm input[name=userid]').val()
        };

        $.ajax({
            type: 'POST',
            url: 'ajax_schedule_event.php',
            data: eventData,
            success: function (response) {
                //alert(response);
                closeEventDialog(); // Close the dialog after saving/updating an event
                updateCalendar(); // Update the calendar after saving/updating an event
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    }

    // Function to delete an event
    function deleteEvent() {
        if (confirm('Are you sure you want to delete this event?')) {
            // Collect data from the dialog form
            var eventData = {
                date: $('#selectedDate').val(),
                time: $('#selectedTime').val(),
                description: $('#description').val(),
                completed_status: $('#completed_status').val(),
                repeating: $('input[name=repeating]:checked').val(),
                userid: $('#eventForm input[name=userid]').val(),
                delete: true
            };

            $.ajax({
                type: 'POST',
                url: 'ajax_schedule_event.php',
                data: eventData,
                success: function (response) {
                    alert(response);
                    closeEventDialog(); // Close the dialog after deleting an event
                    updateCalendar(); // Update the calendar after deleting an event
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
        }
    }

    // Function to close the event dialog
    function closeEventDialog() {
        selectedEventDate = null;
        selectedEventTime = null;
        selectedEventDescription = null;
        $('#eventDialog').dialog('close');
    }

    // Function to go to the previous month
    function previousMonth() {
        currentMonth--;
        if (currentMonth < 1) {
            currentMonth = 12;
            currentYear--;
        }
        updateCalendar();
    }

    // Function to go to the next month
    function nextMonth() {
        currentMonth++;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear++;
        }
        updateCalendar();
    }

    // Initialize the event dialog
    $(document).ready(function () {
        $('#eventDialog').dialog({
            autoOpen: false,
            modal: true,
            width: 400,
            buttons: {
                "Close": function () {
                    closeEventDialog();
                }
            }
        });

        updateCalendar();
    });
</script>

<?php
require_once('../includes/footer.php');
?>
