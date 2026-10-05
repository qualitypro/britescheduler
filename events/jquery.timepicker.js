/*!
 * jQuery Timepicker
 * A jQuery timepicker plugin inspired by Google Calendar.
 * https://github.com/jonthornton/jquery-timepicker
 *
 * @version 1.11.16
 * @requires jQuery v1.7+
 * @author Jon Thornton
 * @license MIT License
 */
(function (factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['jquery'], factory);
    } else if (typeof exports === 'object') {
        // Node/CommonJS style for Browserify
        module.exports = factory(require('jquery'));
    } else {
        // Browser globals
        factory(jQuery);
    }
}(function ($) {
    // plugin code...

    // Add your timepicker plugin code here

    // For example, you can use the following simple code as a starting point:

    // $.fn.timepicker = function () {
    //     // Your timepicker code here...
    // };

    // Make sure to consult the official documentation of the timepicker plugin
    // you are using for more details and customization options.
}));
