<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scheduling Page</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <!-- Include the Timepicker CSS file -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.9.1/jquery.timepicker.css" integrity="sha512-6Cm0Yxgqgkfc3Xh1TL3OV9K2HXFtWXQ3TmUEtOZJhVC9fcf8+PmdcrgKVtmZ5D+46sGRyOnFCtkc1xREh2lO+g==" crossorigin="anonymous" referrerpolicy="no-referrer" />    
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <!-- Include the Timepicker JS file -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.9.1/jquery.timepicker.js" integrity="sha512-ohQJfwKCqZBnC4RWb7R9FaCLYHHqjReRodx/UTpaGDq3HigY3j7qnHS2uxJ54LvLSTm0Hv/U3ycdcoGmPt6IuA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>

<div id="calendar"></div>
<div id="event-dialog" style="display: none;">
    <form id="event-form">
        <input type="hidden" name="event_id" id="eventid" value="1">
        <!-- <input type="hidden" name="user_id" id="user_id" value="<?php echo $_SESSION['user_id']; ?>">-->
        <input type="hidden" name="user_id" id="user_id" value="1">

        <table>
            <tr>
                <td><label for="start_date">Start Date:</label></td>
                <td><input type="text" name="start_date" id="start_date" required></td>
            </tr>
            <tr>
                <td><label for="end_date">End Date:</label></td>
                <td><input type="text" name="end_date" id="end_date" required></td>
            </tr>
            <tr>
                <td><label for="start_time">Start Time:</label></td>
                <td><input type="text" name="start_time" id="start_time" class="timepicker" required></td>
            </tr>
            <tr>
                <td><label for="end_time">End Time:</label></td>
                <td><input type="text" name="end_time" id="end_time" class="timepicker" required></td>
            </tr>
            <tr>
                <td><label for="description">Description:</label></td>
                <td><textarea name="description" id="description" required></textarea></td>
            </tr>
            <tr>
                <td><label for="repeat_type">Repeat:</label></td>
                <td>
                    <select name="repeat_type" id="repeat_type">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td><label for="is_public">Is Public:</label></td>
                <td>
                    <select name="is_public" id="is_public">
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td><label for="is_active">Is Active:</label></td>
                <td>
                    <select name="is_active" id="is_active">
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </td>
            </tr>
        </table>
    </form>
</div>

<script>
    $(document).ready(function () {
        // Initialize the datepicker
        $("#start_date, #end_date").datepicker();

        // Initialize the timepicker with 12-hour time format
        $(".timepicker").timepicker({
            //timeFormat: 'h:mm p', // Use 'h:mm p' for 12-hour format with AM/PM
            //step: 15 // Optional: Set the step for minutes
        });

        // Initialize the calendar
        $("#calendar").datepicker({
            onSelect: function (date) {
                openEventDialog(date);
            }
        });

        // Open the event dialog
        function openEventDialog(date) {
            $("#start_date, #end_date").val(date);
            $("#event-dialog").dialog({
                title: "Event Details",
                modal: true,
                width: 400,
                buttons: {
                    "Save": function () {
                        saveEvent();
                        $(this).dialog("close");
                    },
                    "Delete": function () {
                        deleteEvent();
                        $(this).dialog("close");
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                }
            });
        }

        // Save event using AJAX
        function saveEvent() {
            // Separate date and time components for form submission
            var start_date = $("#start_date").val();
            var start_time = $("#start_time").val();
            var end_date = $("#end_date").val();
            var end_time = $("#end_time").val();

            var formData = $("#event-form").serializeArray();

            $.ajax({
                url: "save_event.php",
                type: "POST",
                data: formData,
                success: function (response) {
                    // Handle success
                    console.log(response);
                },
                error: function (error) {
                    // Handle error
                    console.log(error);
                }
            });
        }

        // Delete event using AJAX
        function deleteEvent() {
            var formData = $("#event-form").serialize();
            $.ajax({
                url: "delete_event.php",
                type: "POST",
                data: formData,
                success: function (response) {
                    // Handle success
                    console.log(response);
                },
                error: function (error) {
                    // Handle error
                    console.log(error);
                }
            });
        }
    });
</script>

</body>
</html>
