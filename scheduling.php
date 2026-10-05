<!DOCTYPE html>
<html>
<head>
	<meta content="text/html; charset=utf-8" http-equiv="content-type">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.css">
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.js"></script>
	<style>
	.async-hide {
	   opacity: 0 !important;
	}
	</style>
	<script async src="assets/js/js.js" type="text/javascript">
	</script>
	<script async data-id="CC6UAQBC77U7GVKHLC4G" src="assets/js/main.MTdjYzNiZDU2Mw.js" type="text/javascript">
	</script>
	<script async src="assets/js/js_002.js" type="text/javascript">
	</script>
	<script async src="assets/js/js_003.js" type="text/javascript">
	</script>
	<script async src="assets/js/insight.old.min.js">
	</script>
	<script async src="assets/js/main.74d80534.js">
	</script>
	<script async src="assets/js/events.js" type="text/javascript">
	</script>
	<script async src="assets/js/insight.min.js" type="text/javascript">
	</script>
	<script async src="assets/js/core.js" type="text/javascript">
	</script>
	<script async src="assets/js/hotjar-99526.js" type="text/javascript">
	</script>
	<script async src="assets/js/analytics.js" type="text/javascript">
	</script>
	<script async src="assets/js/gtm.js">
	</script>
	<script async src="assets/js/analytics.js">
	</script>
	<script>
	           (function (a, s, y, n, c, h, i, d, e) {
	                   s.className += " " + y;
	                   h.start = 1 * new Date();
	                   h.end = i = function () {
	                     s.className = s.className.replace(RegExp(" ?" + y), "");
	                   };
	                   (a[n] = a[n] || []).hide = h;
	                   setTimeout(function () {
	                     i();
	                     h.end = null;
	                   }, c);
	                   h.timeout = c;
	                 })(window, document.documentElement, "async-hide", "dataLayer", 4000, {
	                   "GTM-K9BGS8K": true,
	                 });
	</script>
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
          url: "events/save_event.php",
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
          url: "events/save_event.php",
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
        fetch('events/events.json.php')
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
	<meta charset="UTF-8">
	<link href="https://demos.creative-tim.com/material-tailwind-dashboard-react/img/favicon.png" rel="icon" type="image/svg+xml">
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<meta content="#253238" name="theme-color">
	<title>BRITE ONLINE</title>
	<link href="assets/css/css2.css" rel="stylesheet">
	<link href="assets/css/all.min.css" rel="stylesheet">
	<script data-site="demos.creative-tim.com" defer="defer" src="assets/js/nepcha-analytics.js">
	</script>
	<script src="assets/js/index-7892861d.js" type="module">
	</script>
	<link href="assets/css/index-82567377.css" rel="stylesheet">
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
	<style id="apexcharts-css">
	@
	keyframes opaque { 0% {
	   opacity: 0
	}

	to {
	   opacity: 1
	}

	}
	@
	keyframes resizeanim { 0%,to { opacity:0
	   
	}

	}
	.apexcharts-canvas {
	   position: relative;
	   user-select: none
	}

	.apexcharts-canvas ::-webkit-scrollbar {
	   -webkit-appearance: none;
	   width: 6px
	}

	.apexcharts-canvas ::-webkit-scrollbar-thumb {
	   border-radius: 4px;
	   background-color: rgba(0, 0, 0, .5);
	   box-shadow: 0 0 1px rgba(255, 255, 255, .5);
	   -webkit-box-shadow: 0 0 1px rgba(255, 255, 255, .5)
	}

	.apexcharts-inner {
	   position: relative
	}

	.apexcharts-text tspan {
	   font-family: inherit
	}

	.legend-mouseover-inactive {
	   transition: .15s ease all;
	   opacity: .2
	}

	.apexcharts-legend-text {
	   padding-left: 15px;
	   margin-left: -15px;
	}

	.apexcharts-series-collapsed {
	   opacity: 0
	}

	.apexcharts-tooltip {
	   border-radius: 5px;
	   box-shadow: 2px 2px 6px -4px #999;
	   cursor: default;
	   font-size: 14px;
	   left: 62px;
	   opacity: 0;
	   pointer-events: none;
	   position: absolute;
	   top: 20px;
	   display: flex;
	   flex-direction: column;
	   overflow: hidden;
	   white-space: nowrap;
	   z-index: 12;
	   transition: .15s ease all
	}

	.apexcharts-tooltip.apexcharts-active {
	   opacity: 1;
	   transition: .15s ease all
	}

	.apexcharts-tooltip.apexcharts-theme-light {
	   border: 1px solid #e3e3e3;
	   background: rgba(255, 255, 255, .96)
	}

	.apexcharts-tooltip.apexcharts-theme-dark {
	   color: #fff;
	   background: rgba(30, 30, 30, .8)
	}

	.apexcharts-tooltip * {
	   font-family: inherit
	}

	.apexcharts-tooltip-title {
	   padding: 6px;
	   font-size: 15px;
	   margin-bottom: 4px
	}

	.apexcharts-tooltip.apexcharts-theme-light .apexcharts-tooltip-title {
	   background: #eceff1;
	   border-bottom: 1px solid #ddd
	}

	.apexcharts-tooltip.apexcharts-theme-dark .apexcharts-tooltip-title {
	   background: rgba(0, 0, 0, .7);
	   border-bottom: 1px solid #333
	}

	.apexcharts-tooltip-text-goals-value, .apexcharts-tooltip-text-y-value,
	   .apexcharts-tooltip-text-z-value {
	   display: inline-block;
	   margin-left: 5px;
	   font-weight: 600
	}

	.apexcharts-tooltip-text-goals-label:empty,
	   .apexcharts-tooltip-text-goals-value:empty,
	   .apexcharts-tooltip-text-y-label:empty,
	   .apexcharts-tooltip-text-y-value:empty,
	   .apexcharts-tooltip-text-z-value:empty, .apexcharts-tooltip-title:empty
	   {
	   display: none
	}

	.apexcharts-tooltip-text-goals-label,
	   .apexcharts-tooltip-text-goals-value {
	   padding: 6px 0 5px
	}

	.apexcharts-tooltip-goals-group, .apexcharts-tooltip-text-goals-label,
	   .apexcharts-tooltip-text-goals-value {
	   display: flex
	}

	.apexcharts-tooltip-text-goals-label:not(:empty),
	   .apexcharts-tooltip-text-goals-value:not(:empty) {
	   margin-top: -6px
	}

	.apexcharts-tooltip-marker {
	   width: 12px;
	   height: 12px;
	   position: relative;
	   top: 0;
	   margin-right: 10px;
	   border-radius: 50%
	}

	.apexcharts-tooltip-series-group {
	   padding: 0 10px;
	   display: none;
	   text-align: left;
	   justify-content: left;
	   align-items: center
	}

	.apexcharts-tooltip-series-group.apexcharts-active .apexcharts-tooltip-marker
	   {
	   opacity: 1
	}

	.apexcharts-tooltip-series-group.apexcharts-active,
	   .apexcharts-tooltip-series-group:last-child {
	   padding-bottom: 4px
	}

	.apexcharts-tooltip-series-group-hidden {
	   opacity: 0;
	   height: 0;
	   line-height: 0;
	   padding: 0 !important
	}

	.apexcharts-tooltip-y-group {
	   padding: 6px 0 5px
	}

	.apexcharts-custom-tooltip, .apexcharts-tooltip-box {
	   padding: 4px 8px
	}

	.apexcharts-tooltip-boxPlot {
	   display: flex;
	   flex-direction: column-reverse
	}

	.apexcharts-tooltip-box>div {
	   margin: 4px 0
	}

	.apexcharts-tooltip-box span.value {
	   font-weight: 700
	}

	.apexcharts-tooltip-rangebar {
	   padding: 5px 8px
	}

	.apexcharts-tooltip-rangebar .category {
	   font-weight: 600;
	   color: #777
	}

	.apexcharts-tooltip-rangebar .series-name {
	   font-weight: 700;
	   display: block;
	   margin-bottom: 5px
	}

	.apexcharts-xaxistooltip, .apexcharts-yaxistooltip {
	   opacity: 0;
	   pointer-events: none;
	   color: #373d3f;
	   font-size: 13px;
	   text-align: center;
	   border-radius: 2px;
	   position: absolute;
	   z-index: 10;
	   background: #eceff1;
	   border: 1px solid #90a4ae
	}

	.apexcharts-xaxistooltip {
	   padding: 9px 10px;
	   transition: .15s ease all
	}

	.apexcharts-xaxistooltip.apexcharts-theme-dark {
	   background: rgba(0, 0, 0, .7);
	   border: 1px solid rgba(0, 0, 0, .5);
	   color: #fff
	}

	.apexcharts-xaxistooltip:after, .apexcharts-xaxistooltip:before {
	   left: 50%;
	   border: solid transparent;
	   content: " ";
	   height: 0;
	   width: 0;
	   position: absolute;
	   pointer-events: none
	}

	.apexcharts-xaxistooltip:after {
	   border-color: transparent;
	   border-width: 6px;
	   margin-left: -6px
	}

	.apexcharts-xaxistooltip:before {
	   border-color: transparent;
	   border-width: 7px;
	   margin-left: -7px
	}

	.apexcharts-xaxistooltip-bottom:after, .apexcharts-xaxistooltip-bottom:before
	   {
	   bottom: 100%
	}

	.apexcharts-xaxistooltip-top:after, .apexcharts-xaxistooltip-top:before
	   {
	   top: 100%
	}

	.apexcharts-xaxistooltip-bottom:after {
	   border-bottom-color: #eceff1
	}

	.apexcharts-xaxistooltip-bottom:before {
	   border-bottom-color: #90a4ae
	}

	.apexcharts-xaxistooltip-bottom.apexcharts-theme-dark:after,
	   .apexcharts-xaxistooltip-bottom.apexcharts-theme-dark:before {
	   border-bottom-color: rgba(0, 0, 0, .5)
	}

	.apexcharts-xaxistooltip-top:after {
	   border-top-color: #eceff1
	}

	.apexcharts-xaxistooltip-top:before {
	   border-top-color: #90a4ae
	}

	.apexcharts-xaxistooltip-top.apexcharts-theme-dark:after,
	   .apexcharts-xaxistooltip-top.apexcharts-theme-dark:before {
	   border-top-color: rgba(0, 0, 0, .5)
	}

	.apexcharts-xaxistooltip.apexcharts-active {
	   opacity: 1;
	   transition: .15s ease all
	}

	.apexcharts-yaxistooltip {
	   padding: 4px 10px
	}

	.apexcharts-yaxistooltip.apexcharts-theme-dark {
	   background: rgba(0, 0, 0, .7);
	   border: 1px solid rgba(0, 0, 0, .5);
	   color: #fff
	}

	.apexcharts-yaxistooltip:after, .apexcharts-yaxistooltip:before {
	   top: 50%;
	   border: solid transparent;
	   content: " ";
	   height: 0;
	   width: 0;
	   position: absolute;
	   pointer-events: none
	}

	.apexcharts-yaxistooltip:after {
	   border-color: transparent;
	   border-width: 6px;
	   margin-top: -6px
	}

	.apexcharts-yaxistooltip:before {
	   border-color: transparent;
	   border-width: 7px;
	   margin-top: -7px
	}

	.apexcharts-yaxistooltip-left:after, .apexcharts-yaxistooltip-left:before
	   {
	   left: 100%
	}

	.apexcharts-yaxistooltip-right:after, .apexcharts-yaxistooltip-right:before
	   {
	   right: 100%
	}

	.apexcharts-yaxistooltip-left:after {
	   border-left-color: #eceff1
	}

	.apexcharts-yaxistooltip-left:before {
	   border-left-color: #90a4ae
	}

	.apexcharts-yaxistooltip-left.apexcharts-theme-dark:after,
	   .apexcharts-yaxistooltip-left.apexcharts-theme-dark:before {
	   border-left-color: rgba(0, 0, 0, .5)
	}

	.apexcharts-yaxistooltip-right:after {
	   border-right-color: #eceff1
	}

	.apexcharts-yaxistooltip-right:before {
	   border-right-color: #90a4ae
	}

	.apexcharts-yaxistooltip-right.apexcharts-theme-dark:after,
	   .apexcharts-yaxistooltip-right.apexcharts-theme-dark:before {
	   border-right-color: rgba(0, 0, 0, .5)
	}

	.apexcharts-yaxistooltip.apexcharts-active {
	   opacity: 1
	}

	.apexcharts-yaxistooltip-hidden {
	   display: none
	}

	.apexcharts-xcrosshairs, .apexcharts-ycrosshairs {
	   pointer-events: none;
	   opacity: 0;
	   transition: .15s ease all
	}

	.apexcharts-xcrosshairs.apexcharts-active, .apexcharts-ycrosshairs.apexcharts-active
	   {
	   opacity: 1;
	   transition: .15s ease all
	}

	.apexcharts-ycrosshairs-hidden {
	   opacity: 0
	}

	.apexcharts-selection-rect {
	   cursor: move
	}

	.svg_select_boundingRect, .svg_select_points_rot {
	   pointer-events: none;
	   opacity: 0;
	   visibility: hidden
	}

	.apexcharts-selection-rect+g .svg_select_boundingRect,
	   .apexcharts-selection-rect+g .svg_select_points_rot {
	   opacity: 0;
	   visibility: hidden
	}

	.apexcharts-selection-rect+g .svg_select_points_l,
	   .apexcharts-selection-rect+g .svg_select_points_r {
	   cursor: ew-resize;
	   opacity: 1;
	   visibility: visible
	}

	.svg_select_points {
	   fill: #efefef;
	   stroke: #333;
	   rx: 2
	}

	.apexcharts-svg.apexcharts-zoomable.hovering-zoom {
	   cursor: crosshair
	}

	.apexcharts-svg.apexcharts-zoomable.hovering-pan {
	   cursor: move
	}

	.apexcharts-menu-icon, .apexcharts-pan-icon, .apexcharts-reset-icon,
	   .apexcharts-selection-icon, .apexcharts-toolbar-custom-icon,
	   .apexcharts-zoom-icon, .apexcharts-zoomin-icon,
	   .apexcharts-zoomout-icon {
	   cursor: pointer;
	   width: 20px;
	   height: 20px;
	   line-height: 24px;
	   color: #6e8192;
	   text-align: center
	}

	.apexcharts-menu-icon svg, .apexcharts-reset-icon svg,
	   .apexcharts-zoom-icon svg, .apexcharts-zoomin-icon svg,
	   .apexcharts-zoomout-icon svg {
	   fill: #6e8192
	}

	.apexcharts-selection-icon svg {
	   fill: #444;
	   transform: scale(.76)
	}

	.apexcharts-theme-dark .apexcharts-menu-icon svg, .apexcharts-theme-dark .apexcharts-pan-icon svg,
	   .apexcharts-theme-dark .apexcharts-reset-icon svg,
	   .apexcharts-theme-dark .apexcharts-selection-icon svg,
	   .apexcharts-theme-dark .apexcharts-toolbar-custom-icon svg,
	   .apexcharts-theme-dark .apexcharts-zoom-icon svg,
	   .apexcharts-theme-dark .apexcharts-zoomin-icon svg,
	   .apexcharts-theme-dark .apexcharts-zoomout-icon svg {
	   fill: #f3f4f5
	}

	.apexcharts-canvas .apexcharts-reset-zoom-icon.apexcharts-selected svg,
	   .apexcharts-canvas .apexcharts-selection-icon.apexcharts-selected svg,
	   .apexcharts-canvas .apexcharts-zoom-icon.apexcharts-selected svg {
	   fill: #008ffb
	}

	.apexcharts-theme-light .apexcharts-menu-icon:hover svg,
	   .apexcharts-theme-light .apexcharts-reset-icon:hover svg,
	   .apexcharts-theme-light .apexcharts-selection-icon:not(.apexcharts-selected):hover svg,
	   .apexcharts-theme-light .apexcharts-zoom-icon:not(.apexcharts-selected):hover svg,
	   .apexcharts-theme-light .apexcharts-zoomin-icon:hover svg,
	   .apexcharts-theme-light .apexcharts-zoomout-icon:hover svg {
	   fill: #333
	}

	.apexcharts-menu-icon, .apexcharts-selection-icon {
	   position: relative
	}

	.apexcharts-reset-icon {
	   margin-left: 5px
	}

	.apexcharts-menu-icon, .apexcharts-reset-icon, .apexcharts-zoom-icon {
	   transform: scale(.85)
	}

	.apexcharts-zoomin-icon, .apexcharts-zoomout-icon {
	   transform: scale(.7)
	}

	.apexcharts-zoomout-icon {
	   margin-right: 3px
	}

	.apexcharts-pan-icon {
	   transform: scale(.62);
	   position: relative;
	   left: 1px;
	   top: 0
	}

	.apexcharts-pan-icon svg {
	   fill: #fff;
	   stroke: #6e8192;
	   stroke-width: 2
	}

	.apexcharts-pan-icon.apexcharts-selected svg {
	   stroke: #008ffb
	}

	.apexcharts-pan-icon:not(.apexcharts-selected):hover svg {
	   stroke: #333
	}

	.apexcharts-toolbar {
	   position: absolute;
	   z-index: 11;
	   max-width: 176px;
	   text-align: right;
	   border-radius: 3px;
	   padding: 0 6px 2px;
	   display: flex;
	   justify-content: space-between;
	   align-items: center
	}

	.apexcharts-menu {
	   background: #fff;
	   position: absolute;
	   top: 100%;
	   border: 1px solid #ddd;
	   border-radius: 3px;
	   padding: 3px;
	   right: 10px;
	   opacity: 0;
	   min-width: 110px;
	   transition: .15s ease all;
	   pointer-events: none
	}

	.apexcharts-menu.apexcharts-menu-open {
	   opacity: 1;
	   pointer-events: all;
	   transition: .15s ease all
	}

	.apexcharts-menu-item {
	   padding: 6px 7px;
	   font-size: 12px;
	   cursor: pointer
	}

	.apexcharts-theme-light .apexcharts-menu-item:hover {
	   background: #eee
	}

	.apexcharts-theme-dark .apexcharts-menu {
	   background: rgba(0, 0, 0, .7);
	   color: #fff
	}

	@media screen and (min-width:768px) {
	   .apexcharts-canvas:hover .apexcharts-toolbar {
	       opacity: 1
	   }
	}

	.apexcharts-canvas .apexcharts-element-hidden, .apexcharts-datalabel.apexcharts-element-hidden,
	   .apexcharts-hide .apexcharts-series-points {
	   opacity: 0
	}

	.apexcharts-hidden-element-shown {
	   opacity: 1;
	   transition: 0.25s ease all;
	}

	.apexcharts-datalabel, .apexcharts-datalabel-label,
	   .apexcharts-datalabel-value, .apexcharts-datalabels,
	   .apexcharts-pie-label {
	   cursor: default;
	   pointer-events: none
	}

	.apexcharts-pie-label-delay {
	   opacity: 0;
	   animation-name: opaque;
	   animation-duration: .3s;
	   animation-fill-mode: forwards;
	   animation-timing-function: ease
	}

	.apexcharts-annotation-rect, .apexcharts-area-series .apexcharts-area,
	   .apexcharts-area-series .apexcharts-series-markers .apexcharts-marker.no-pointer-events,
	   .apexcharts-gridline, .apexcharts-line, .apexcharts-line-series .apexcharts-series-markers .apexcharts-marker.no-pointer-events,
	   .apexcharts-point-annotation-label, .apexcharts-radar-series path,
	   .apexcharts-radar-series polygon, .apexcharts-toolbar svg,
	   .apexcharts-tooltip .apexcharts-marker,
	   .apexcharts-xaxis-annotation-label, .apexcharts-yaxis-annotation-label,
	   .apexcharts-zoom-rect {
	   pointer-events: none
	}

	.apexcharts-marker {
	   transition: .15s ease all
	}

	.resize-triggers {
	   animation: 1ms resizeanim;
	   visibility: hidden;
	   opacity: 0;
	   height: 100%;
	   width: 100%;
	   overflow: hidden
	}

	.contract-trigger:before, .resize-triggers, .resize-triggers>div {
	   content: " ";
	   display: block;
	   position: absolute;
	   top: 0;
	   left: 0
	}

	.resize-triggers>div {
	   height: 100%;
	   width: 100%;
	   background: #eee;
	   overflow: auto
	}

	.contract-trigger:before {
	   overflow: hidden;
	   width: 200%;
	   height: 200%
	}

	.apexcharts-bar-goals-markers {
	   pointer-events: none
	}

	.apexcharts-bar-shadows {
	   pointer-events: none
	}

	.apexcharts-rangebar-goals-markers {
	   pointer-events: none
	}
	</style>
	</script>
</head>
<body>
	<div id="root">
		<div class="min-h-screen bg-blue-gray-50/50">
			<aside class="bg-white shadow-sm -translate-x-80 fixed inset-0 z-50 my-4 ml-4 h-[calc(100vh-32px)] w-72 rounded-xl transition-transform duration-300 xl:translate-x-0 border border-blue-gray-100">
				<div class="relative">
					<a class="py-6 px-8 text-center" href="#/">
					<h6 class="block antialiased tracking-normal font-sans text-base font-semibold leading-relaxed text-blue-gray-900">BRITE ONLINE</h6></a> <button class="align-middle select-none font-sans font-medium text-center uppercase transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none w-8 max-w-[32px] h-8 max-h-[32px] rounded-lg text-xs text-white hover:bg-white/10 active:bg-white/30 absolute right-0 top-0 grid rounded-br-none rounded-tl-none xl:hidden" type="button"><span class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2"><svg aria-hidden="true" class="h-5 w-5 text-white" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
					<path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"></path></svg></span></button>
				</div>
				<div class="m-4">
					<ul class="mb-4 flex flex-col gap-1">
						<li>
							<a class="" href="dashboard.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"></path>
							<path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">dashboard</p></button></a>
						</li>
						<li>
							<a class="" href="profile.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653zm-12.54-1.285A7.486 7.486 0 0112 15a7.486 7.486 0 015.855 2.812A8.224 8.224 0 0112 20.25a8.224 8.224 0 01-5.855-2.438zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">profile</p></button></a>
						</li>
						<li>
							<a class="" href="tables.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M1.5 5.625c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v12.75c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 18.375V5.625zM21 9.375A.375.375 0 0020.625 9h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zM10.875 18.75a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5zM3.375 15h7.5a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375zm0-3.75h7.5a.375.375 0 00.375-.375v-1.5A.375.375 0 0010.875 9h-7.5A.375.375 0 003 9.375v1.5c0 .207.168.375.375.375z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">tables</p></button></a>
						</li>
						<li>
							<a class="" href="clients.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M1.5 5.625c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v12.75c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 18.375V5.625zM21 9.375A.375.375 0 0020.625 9h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zM10.875 18.75a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5zM3.375 15h7.5a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375zm0-3.75h7.5a.375.375 0 00.375-.375v-1.5A.375.375 0 0010.875 9h-7.5A.375.375 0 003 9.375v1.5c0 .207.168.375.375.375z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">clients</p></button></a>
						</li>
						<li>
							<a class="" href="contractors.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M1.5 5.625c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v12.75c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 18.375V5.625zM21 9.375A.375.375 0 0020.625 9h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zM10.875 18.75a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5zM3.375 15h7.5a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375zm0-3.75h7.5a.375.375 0 00.375-.375v-1.5A.375.375 0 0010.875 9h-7.5A.375.375 0 003 9.375v1.5c0 .207.168.375.375.375z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">contractors</p></button></a>
						</li>
						<li>
							<a class="active" href="scheduling.php"></a> <a class="active" href="contractors.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg bg-gradient-to-tr from-gray-900 to-gray-800 text-white shadow-md shadow-gray-900/10 hover:shadow-lg hover:shadow-gray-900/20 active:opacity-[0.85] w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M1.5 5.625c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v12.75c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 18.375V5.625zM21 9.375A.375.375 0 0020.625 9h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zm0 3.75a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 00.375-.375v-1.5zM10.875 18.75a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375h7.5zM3.375 15h7.5a.375.375 0 00.375-.375v-1.5a.375.375 0 00-.375-.375h-7.5a.375.375 0 00-.375.375v1.5c0 .207.168.375.375.375zm0-3.75h7.5a.375.375 0 00.375-.375v-1.5A.375.375 0 0010.875 9h-7.5A.375.375 0 003 9.375v1.5c0 .207.168.375.375.375z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">scheduling</p></button></a>
						</li>
						<li>
							<a class="" href="notifications.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 01.67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 11-.671-1.34l.041-.022zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">notifications</p></button></a>
						</li>
					</ul>
					<ul class="mb-4 flex flex-col gap-1">
						<li class="mx-3.5 mt-4 mb-2">
							<p class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-black uppercase opacity-75">auth pages</p>
						</li>
						<li>
							<a class="" href="sign-in.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path d="M5.507 4.048A3 3 0 017.785 3h8.43a3 3 0 012.278 1.048l1.722 2.008A4.533 4.533 0 0019.5 6h-15c-.243 0-.482.02-.715.056l1.722-2.008z"></path>
							<path clip-rule="evenodd" d="M1.5 10.5a3 3 0 013-3h15a3 3 0 110 6h-15a3 3 0 01-3-3zm15 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm2.25.75a.75.75 0 100-1.5.75.75 0 000 1.5zM4.5 15a3 3 0 100 6h15a3 3 0 100-6h-15zm11.25 3.75a.75.75 0 100-1.5.75.75 0 000 1.5zM19.5 18a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" fill-rule="evenodd"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">sign in</p></button></a>
						</li>
						<li>
							<a class="" href="sign-up.php"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 w-full flex items-center gap-4 px-4 capitalize" type="button"><svg aria-hidden="true" class="w-5 h-5 text-inherit" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path d="M5.566 4.657A4.505 4.505 0 016.75 4.5h10.5c.41 0 .806.055 1.183.157A3 3 0 0015.75 3h-7.5a3 3 0 00-2.684 1.657zM2.25 12a3 3 0 013-3h13.5a3 3 0 013 3v6a3 3 0 01-3 3H5.25a3 3 0 01-3-3v-6zM5.25 7.5c-.41 0-.806.055-1.184.157A3 3 0 016.75 6h10.5a3 3 0 012.683 1.657A4.505 4.505 0 0018.75 7.5H5.25z"></path></svg>
							<p class="block antialiased font-sans text-base leading-relaxed text-inherit font-medium capitalize">sign up</p></button></a>
						</li>
					</ul>
				</div>
			</aside>
			<div class="p-4 xl:ml-80">
				<nav class="block w-full max-w-full bg-transparent text-white shadow-none rounded-xl transition-all px-0 py-1">
					<div class="flex flex-col-reverse justify-between gap-6 md:flex-row md:items-center">
						<div class="capitalize">
							<nav aria-label="breadcrumb" class="w-max">
								<ol class="flex flex-wrap items-center w-full bg-opacity-60 rounded-md bg-transparent p-0 transition-all">
									<li class="flex items-center text-blue-gray-900 antialiased font-sans text-sm font-normal leading-normal cursor-pointer transition-colors duration-300 hover:text-light-blue-500">
										<a href="#/dashboard">
										<p class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-normal opacity-50 transition-all hover:text-blue-500 hover:opacity-100">dashboard</p></a><span class="text-blue-gray-500 text-sm antialiased font-sans font-normal leading-normal mx-2 pointer-events-none select-none">/</span>
									</li>
									<li class="flex items-center text-blue-gray-900 antialiased font-sans text-sm font-normal leading-normal cursor-pointer transition-colors duration-300 hover:text-light-blue-500">
										<p class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-normal">tables</p>
									</li>
								</ol>
							</nav>
							<h6 class="block antialiased tracking-normal font-sans text-base font-semibold leading-relaxed text-blue-gray-900">tables</h6>
						</div>
						<div class="flex items-center">
							<div class="mr-auto md:mr-4 md:w-56">
								<div class="relative w-full min-w-[200px] h-10">
									<input class="peer w-full h-full bg-transparent text-blue-gray-700 font-sans font-normal outline outline-0 focus:outline-0 disabled:bg-blue-gray-50 disabled:border-0 transition-all placeholder-shown:border placeholder-shown:border-blue-gray-200 placeholder-shown:border-t-blue-gray-200 border focus:border-2 border-t-transparent focus:border-t-transparent text-sm px-3 py-2.5 rounded-[7px] border-blue-gray-200 focus:border-gray-900" placeholder=""><label class="flex w-full h-full select-none pointer-events-none absolute left-0 font-normal !overflow-visible truncate peer-placeholder-shown:text-blue-gray-500 leading-tight peer-focus:leading-tight peer-disabled:text-transparent peer-disabled:peer-placeholder-shown:text-blue-gray-500 transition-all -top-1.5 peer-placeholder-shown:text-sm text-[11px] peer-focus:text-[11px] before:content[' '] before:block before:box-border before:w-2.5 before:h-1.5 before:mt-[6.5px] before:mr-1 peer-placeholder-shown:before:border-transparent before:rounded-tl-md before:border-t peer-focus:before:border-t-2 before:border-l peer-focus:before:border-l-2 before:pointer-events-none before:transition-all peer-disabled:before:border-transparent after:content[' '] after:block after:flex-grow after:box-border after:w-2.5 after:h-1.5 after:mt-[6.5px] after:ml-1 peer-placeholder-shown:after:border-transparent after:rounded-tr-md after:border-t peer-focus:after:border-t-2 after:border-r peer-focus:after:border-r-2 after:pointer-events-none after:transition-all peer-disabled:after:border-transparent peer-placeholder-shown:leading-[3.75] text-gray-500 peer-focus:text-gray-900 before:border-blue-gray-200 peer-focus:before:!border-gray-900 after:border-blue-gray-200 peer-focus:after:!border-gray-900">Search</label>
								</div>
							</div><button class="relative align-middle select-none font-sans font-medium text-center uppercase transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none w-10 max-w-[40px] h-10 max-h-[40px] rounded-lg text-xs text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 grid xl:hidden" type="button"><span class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2"><svg aria-hidden="true" class="h-6 w-6 text-blue-gray-500" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M3 6.75A.75.75 0 013.75 6h16.5a.75.75 0 010 1.5H3.75A.75.75 0 013 6.75zM3 12a.75.75 0 01.75-.75h16.5a.75.75 0 010 1.5H3.75A.75.75 0 013 12zm0 5.25a.75.75 0 01.75-.75h16.5a.75.75 0 010 1.5H3.75a.75.75 0 01-.75-.75z" fill-rule="evenodd"></path></svg></span></button> <a href="#/auth/sign-in"><button class="align-middle select-none font-sans font-bold text-center transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none text-xs py-3 rounded-lg text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 hidden items-center gap-1 px-4 xl:flex normal-case" type="button"><svg aria-hidden="true" class="h-5 w-5 text-blue-gray-500" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653zm-12.54-1.285A7.486 7.486 0 0112 15a7.486 7.486 0 015.855 2.812A8.224 8.224 0 0112 20.25a8.224 8.224 0 01-5.855-2.438zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" fill-rule="evenodd"></path></svg> Sign In</button> <button class="relative align-middle select-none font-sans font-medium text-center uppercase transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none w-10 max-w-[40px] h-10 max-h-[40px] rounded-lg text-xs text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30 grid xl:hidden" type="button"><span class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2"><svg aria-hidden="true" class="h-5 w-5 text-blue-gray-500" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653zm-12.54-1.285A7.486 7.486 0 0112 15a7.486 7.486 0 015.855 2.812A8.224 8.224 0 0112 20.25a8.224 8.224 0 01-5.855-2.438zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" fill-rule="evenodd"></path></svg></span></button></a> <button aria-expanded="false" aria-haspopup="menu" class="relative align-middle select-none font-sans font-medium text-center uppercase transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none w-10 max-w-[40px] h-10 max-h-[40px] rounded-lg text-xs text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30" id=":r2:" type="button"><span class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2"><svg aria-hidden="true" class="h-5 w-5 text-blue-gray-500" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M5.25 9a6.75 6.75 0 0113.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 01-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 11-7.48 0 24.585 24.585 0 01-4.831-1.244.75.75 0 01-.298-1.205A8.217 8.217 0 005.25 9.75V9zm4.502 8.9a2.25 2.25 0 104.496 0 25.057 25.057 0 01-4.496 0z" fill-rule="evenodd"></path></svg></span></button> <button class="relative align-middle select-none font-sans font-medium text-center uppercase transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none w-10 max-w-[40px] h-10 max-h-[40px] rounded-lg text-xs text-blue-gray-500 hover:bg-blue-gray-500/10 active:bg-blue-gray-500/30" type="button"><span class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2"><svg aria-hidden="true" class="h-5 w-5 text-blue-gray-500" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path clip-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 00-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 00-2.282.819l-.922 1.597a1.875 1.875 0 00.432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 000 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 00-.432 2.385l.922 1.597a1.875 1.875 0 002.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 002.28-.819l.923-1.597a1.875 1.875 0 00-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 000-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 00-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 00-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 00-1.85-1.567h-1.843zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" fill-rule="evenodd"></path></svg></span></button>
						</div>
					</div>
				</nav><button class="align-middle select-none font-sans font-medium text-center uppercase transition-all disabled:opacity-50 disabled:shadow-none disabled:pointer-events-none w-12 max-w-[48px] h-12 max-h-[48px] text-sm bg-white text-blue-gray-900 shadow-md hover:shadow-lg hover:shadow-blue-gray-500/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none fixed bottom-8 right-8 z-40 rounded-full shadow-blue-gray-900/10" type="button"><span class="absolute top-1/2 left-1/2 transform -translate-y-1/2 -translate-x-1/2"><svg aria-hidden="true" class="h-5 w-5" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
				<path clip-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 00-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 00-2.282.819l-.922 1.597a1.875 1.875 0 00.432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 000 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 00-.432 2.385l.922 1.597a1.875 1.875 0 002.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 002.28-.819l.923-1.597a1.875 1.875 0 00-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 000-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 00-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 00-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 00-1.85-1.567h-1.843zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" fill-rule="evenodd"></path></svg></span></button>
				<div class="mt-12 mb-8 flex flex-col gap-12">
					<div class="relative flex flex-col bg-clip-border rounded-xl bg-white text-gray-700 shadow-md">
						<div class="relative bg-clip-border mx-4 rounded-xl overflow-hidden bg-gradient-to-tr from-gray-900 to-gray-800 text-white shadow-gray-900/20 shadow-lg -mt-6 mb-8 p-6">
							<h6 class="block antialiased tracking-normal font-sans text-base font-semibold leading-relaxed text-white">Scheduling Table</h6>
						</div>
						<div class="p-6 overflow-x-scroll px-0 pt-0 pb-2">
							<table class="w-full min-w-[640px] table-auto">
								<thead>
									<tr>
										<td></td>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>
										
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
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
					<div class="relative flex flex-col bg-clip-border rounded-xl bg-white text-gray-700 shadow-md">
						<div class="relative bg-clip-border mx-4 rounded-xl overflow-hidden bg-gradient-to-tr from-gray-900 to-gray-800 text-white shadow-gray-900/20 shadow-lg -mt-6 mb-8 p-6">
							<h6 class="block antialiased tracking-normal font-sans text-base font-semibold leading-relaxed text-white">Projects Table</h6>
						</div>
						<div class="p-6 overflow-x-scroll px-0 pt-0 pb-2"></div>
					</div>
				</div>
				<div class="text-blue-gray-600">
					<footer class="py-2">
						<div class="flex w-full flex-wrap items-center justify-center gap-6 px-2 md:justify-between">
							<p class="block antialiased font-sans text-sm leading-normal font-normal text-inherit">© 2023, made with <svg aria-hidden="true" class="-mt-0.5 inline-block h-3.5 w-3.5 text-red-600" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"></path></svg> <a class="transition-colors hover:text-blue-500 font-bold" href="https://localhost" target="_blank">BRITE online</a> for a cleaner world!.</p>
							<ul class="flex items-center gap-4">
								<li>
									<a class="block antialiased font-sans text-sm leading-normal py-0.5 px-1 font-normal text-inherit transition-colors hover:text-blue-500" href="https://localhost" target="_blank">BRITE Online</a>
								</li>
								<li>
									<a class="block antialiased font-sans text-sm leading-normal py-0.5 px-1 font-normal text-inherit transition-colors hover:text-blue-500" href="https://localhost/presentation" target="_blank">About Us</a>
								</li>
								<li>
									<a class="block antialiased font-sans text-sm leading-normal py-0.5 px-1 font-normal text-inherit transition-colors hover:text-blue-500" href="https://localhost/blog" target="_blank">Blog</a>
								</li>
								<li>
									<a class="block antialiased font-sans text-sm leading-normal py-0.5 px-1 font-normal text-inherit transition-colors hover:text-blue-500" href="https://localhost/license" target="_blank">License</a>
								</li>
							</ul>
						</div>
					</footer>
				</div>
			</div>
		</div>
	</div>
</body>
</html>