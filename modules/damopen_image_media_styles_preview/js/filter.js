/**
 * @file
 * JavaScript code for Media Assets.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Handles select filter functionality.
   *
   * @todo Rewrite to Backbone.js!
   *
   * @type {Object}
   */
  Drupal.behaviors.platformFilter = {
    attach: function (context, settings) {
      var $table = $('table.social-media-assets');

      $('#edit-platform').on('change', function (e) {
        var selected = e.target.value;
        $table.find('tr:hidden').show();

        // If selected 'All', always show every row.
        if (selected === '_none') {
          return;
        }

        // Otherwise filter.
        $table.find('tbody tr').filter(':not([data-platform="' + selected + '"])').hide();
      });
    }
  };

}(jQuery, Drupal));
