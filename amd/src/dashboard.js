// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Interactive executive dashboard controller for local_adminreport.
 *
 * Dispatches Left-Pane navigation, renders ApexCharts pie series,
 * and handles client-side schedule and trainee filtering.
 *
 * @module     local_adminreport/dashboard
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'local_adminreport/charts'], function($, Ajax, Notification, Charts) {
    'use strict';

    var isRtl = false;
    var currentPeriod = 'week';
    var currentTab = 'plans';
    var deliveredPiesData = null;

    /**
     * Switch Left-Pane active tab and panel.
     *
     * @param {string} targetSelector
     * @param {string} tabName
     */
    function switchTab(targetSelector, tabName) {
        if (!targetSelector) {
            return;
        }

        // Deactivate all nav pills and panels.
        $('#v-pills-tab .nav-link').removeClass('active').attr('aria-selected', 'false');
        $('.tab-content > .tab-pane').removeClass('show active');

        // Activate matching pill and panel.
        var activeBtn = $('#v-pills-tab button[data-bs-target="' + targetSelector + '"], #v-pills-tab button[data-target="' + targetSelector + '"]');
        if (activeBtn.length) {
            activeBtn.addClass('active').attr('aria-selected', 'true');
        }
        $(targetSelector).addClass('show active');

        currentTab = tabName || 'plans';
        $('#custom-form-tab').val(currentTab);

        // Update URL query state without full page reload.
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', currentTab);
            window.history.replaceState({}, '', url.toString());
        }

        // If Delivered Programs pane is activated, render the 4 pie charts.
        if (targetSelector === '#v-pills-delivered') {
            if (deliveredPiesData) {
                Charts.renderDeliveredPies(deliveredPiesData, isRtl);
            }
            setTimeout(function() {
                Charts.reflowAll();
            }, 100);
        }
    }

    return {
        /**
         * Initialize the dashboard controller.
         *
         * @param {Object} config
         */
        init: function(config) {
            try {
                isRtl = (config && config.isRtl) || false;
                currentPeriod = (config && config.initialPeriod) || 'week';
                currentTab = (config && config.initialTab) || 'plans';

                if (config && config.initialData && config.initialData.delivered_pies) {
                    deliveredPiesData = config.initialData.delivered_pies;
                }

                // If starting on the delivered tab, render pies immediately.
                if (currentTab === 'delivered' && deliveredPiesData) {
                    Charts.renderDeliveredPies(deliveredPiesData, isRtl);
                }

                // Left-Pane navigation click handler.
                $('#v-pills-tab').on('click', '.nav-link', function(e) {
                    var target = $(this).attr('data-bs-target') || $(this).attr('data-target');
                    var tabName = $(this).attr('data-tab-name');
                    if (target) {
                        e.preventDefault();
                        switchTab(target, tabName);
                    }
                });

                // Toggle custom date range panel.
                $('#btn-custom-period').on('click', function(e) {
                    e.preventDefault();
                    $('#custom-date-controls').toggleClass('d-none');
                });

                // Schedule table client-side real-time search.
                $('#schedule-search-input').on('keyup', function() {
                    var val = $(this).val().toLowerCase();
                    $('#weekly-runs-table tbody tr').filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                    });
                });

                // Trainees table client-side real-time search.
                $('#trainees-search-input').on('keyup', function() {
                    var val = $(this).val().toLowerCase();
                    $('#trainees-report-table tbody tr').filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                    });
                });

                // Executive Print button.
                $('#btn-print-dashboard').on('click', function(e) {
                    e.preventDefault();
                    window.print();
                });

            } catch (err) {
                console.error('local_adminreport: Dashboard initialization error:', err);
            }
        }
    };
});

