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
 * Interactive dashboard controller for local_adminreport.
 *
 * Dispatches AJAX requests, updates KPI cards, re-renders ApexCharts,
 * and filters the operational schedule table.
 *
 * @module     local_adminreport/dashboard
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'local_adminreport/charts'], function($, Ajax, Notification, Charts) {
    'use strict';

    var currentPeriod = 'week';
    var isRtl = false;

    /**
     * Update KPI cards with new metrics.
     *
     * @param {Object} metrics
     * @param {Object} ytdMetrics
     */
    function updateKPICards(metrics, ytdMetrics) {
        $('#kpi-runs-count').text(metrics.runs_count || 0);
        $('#kpi-trainees-count').text(metrics.participations_count || 0);
        $('#kpi-hours-count').text(metrics.total_training_hours ? metrics.total_training_hours.toLocaleString() : 0);
        $('#kpi-completion-rate').text((metrics.completion_rate || 0) + '%');
        $('#kpi-avg-grade').text((metrics.avg_grade || 0) + '%');

        if (ytdMetrics) {
            $('#kpi-ytd-runs').text(ytdMetrics.runs_count || 0);
            $('#kpi-ytd-trainees').text(ytdMetrics.participations_count || 0);
            $('#kpi-ytd-hours').text(ytdMetrics.total_training_hours ? ytdMetrics.total_training_hours.toLocaleString() : 0);
        }
    }

    /**
     * Update operational schedule table rows.
     *
     * @param {Array} runs
     */
    function updateRunsTable(runs) {
        var tbody = $('#operational-runs-table tbody');
        tbody.empty();

        if (!runs || runs.length === 0) {
            tbody.append('<tr><td colspan="10" class="text-center text-muted p-4">' +
                (isRtl ? 'لا توجد دورات تدريبية مطابقة في هذه الفترة' : 'No matching program runs for this period') +
                '</td></tr>');
            return;
        }

        runs.forEach(function(r) {
            var row = $('<tr>');
            row.append($('<td>').html('<strong>' + $('<div>').text(r.run_code).html() + '</strong>'));
            row.append($('<td>').html('<a href="' + M.cfg.wwwroot + '/course/view.php?id=' + r.courseid + '">' +
                $('<div>').text(r.coursename).html() + '</a>'));
            row.append($('<td>').text(r.orgname));
            row.append($('<td>').text(r.typename));
            row.append($('<td>').text(r.locname));
            row.append($('<td>').text(r.startdate + ' - ' + r.enddate));
            row.append($('<td>').text(r.classroom));
            row.append($('<td>').text(r.trainer_name));
            row.append($('<td>').addClass('text-center').html('<span class="badge bg-light text-dark">' + r.participants + '</span>'));
            row.append($('<td>').html('<span class="badge ' + r.badge_class + '">' + r.status_label + '</span>'));
            tbody.append(row);
        });
    }

    /**
     * Update all charts with fresh data.
     *
     * @param {Object} chartData
     */
    function updateCharts(chartData) {
        if (!chartData) {
            return;
        }
        try {
            Charts.renderLocationsChart('chart-locations-container', chartData.locations, isRtl);
        } catch (e) {
            console.error('local_adminreport: Failed to render locations chart:', e);
        }
        try {
            Charts.renderClassificationsChart('chart-classifications-container', chartData.classifications, isRtl);
        } catch (e) {
            console.error('local_adminreport: Failed to render classifications chart:', e);
        }
        try {
            Charts.renderTrendsChart('chart-trends-container', chartData.trends, isRtl);
        } catch (e) {
            console.error('local_adminreport: Failed to render trends chart:', e);
        }
    }

    /**
     * Fetch report data via AJAX and refresh UI.
     */
    function loadReportData() {
        var filterOrg = parseInt($('#filter-org').val(), 10) || 0;
        var filterType = parseInt($('#filter-type').val(), 10) || 0;
        var filterLoc = parseInt($('#filter-location').val(), 10) || 0;

        var startDate = 0;
        var endDate = 0;
        if (currentPeriod === 'custom') {
            var sVal = $('#custom-start-date').val();
            var eVal = $('#custom-end-date').val();
            if (sVal) {
                startDate = Math.floor(new Date(sVal).getTime() / 1000);
            }
            if (eVal) {
                endDate = Math.floor(new Date(eVal).getTime() / 1000);
            }
        }

        // Show loading spinner overlay.
        $('#dashboard-loading-overlay').removeClass('d-none').addClass('is-active');

        Ajax.call([{
            methodname: 'local_adminreport_get_report_data',
            args: {
                period_type: currentPeriod,
                start_date: startDate,
                end_date: endDate,
                org_dim_id: filterOrg,
                type_dim_id: filterType,
                loc_dim_id: filterLoc
            }
        }])[0].done(function(response) {
            $('#dashboard-loading-overlay').addClass('d-none').removeClass('is-active');
            if (response && response.data) {
                var data = JSON.parse(response.data);
                updateKPICards(data.metrics, data.ytd_metrics);
                updateCharts(data.charts);
                updateRunsTable(data.runs);

                // Update early warning indicator.
                if (data.total_at_risk > 0) {
                    $('#early-warning-banner').removeClass('d-none');
                    $('#early-warning-count').text(data.total_at_risk);
                } else {
                    $('#early-warning-banner').addClass('d-none');
                }
            }
        }).fail(function(ex) {
            $('#dashboard-loading-overlay').addClass('d-none').removeClass('is-active');
            Notification.exception(ex);
        });
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

                // Initial render of charts from embedded server payload.
                if (config && config.initialData && config.initialData.charts) {
                    updateCharts(config.initialData.charts);
                }

                // Period Tab Click.
                $('.period-btn').on('click', function(e) {
                    e.preventDefault();
                    $('.period-btn').removeClass('active btn-primary').addClass('btn-outline-primary');
                    $(this).addClass('active btn-primary').removeClass('btn-outline-primary');

                    currentPeriod = $(this).data('period');

                    if (currentPeriod === 'custom') {
                        $('#custom-date-controls').removeClass('d-none');
                    } else {
                        $('#custom-date-controls').addClass('d-none');
                        loadReportData();
                    }
                });

                // Custom date inputs change.
                $('#apply-custom-dates').on('click', function() {
                    loadReportData();
                });

                // Filter dropdowns change.
                $('#filter-org, #filter-type, #filter-location').on('change', function() {
                    loadReportData();
                });

                // Reset filters.
                $('#btn-reset-filters').on('click', function() {
                    $('#filter-org').val('0');
                    $('#filter-type').val('0');
                    $('#filter-location').val('0');
                    loadReportData();
                });

                // Client-side search in operational table.
                $('#table-search-input').on('keyup', function() {
                    var val = $(this).val().toLowerCase();
                    $('#operational-runs-table tbody tr').filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                    });
                });

                // Print button.
                $('#btn-print-dashboard').on('click', function() {
                    window.print();
                });
            } catch (err) {
                console.error('local_adminreport: Dashboard initialization error:', err);
            }
        }
    };
});
