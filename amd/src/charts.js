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
 * Chart rendering component for local_adminreport using ApexCharts.
 *
 * Provides native Arabic RTL support, brand palette, and memory cleanup.
 *
 * @module     local_adminreport/charts
 * @copyright  2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_adminreport/apexcharts'], function(ApexCharts) {
    'use strict';

    var chartInstances = {};

    var palette = [
        '#1d4ed8', // Primary Blue
        '#0d9488', // Teal
        '#0284c7', // Sky
        '#f59e0b', // Amber
        '#6366f1', // Indigo
        '#10b981', // Emerald
        '#8b5cf6', // Violet
        '#ec4899', // Pink
        '#64748b'  // Slate
    ];

    /**
     * Resolve ApexCharts constructor safely.
     *
     * @return {Function|null}
     */
    function getApex() {
        if (typeof ApexCharts !== 'undefined' && ApexCharts) {
            return ApexCharts;
        }
        if (typeof window !== 'undefined' && window.ApexCharts) {
            return window.ApexCharts;
        }
        return null;
    }

    /**
     * Safely destroy an existing chart instance.
     *
     * @param {string} elementId
     */
    function destroyChart(elementId) {
        if (chartInstances[elementId]) {
            try {
                chartInstances[elementId].destroy();
            } catch (e) {
                // Ignore destruction error.
            }
            delete chartInstances[elementId];
        }
    }

    return {
        /**
         * Render Locations Donut Chart.
         *
         * @param {string} elementId
         * @param {Object} data {labels: [], series: []}
         * @param {boolean} isRtl
         */
        renderLocationsChart: function(elementId, data, isRtl) {
            destroyChart(elementId);
            var el = document.getElementById(elementId);
            if (!el) {
                return;
            }

            if (!data || !data.series || data.series.length === 0) {
                el.innerHTML = '<div class="text-center text-muted p-4">' +
                    (isRtl ? 'لا توجد بيانات متاحة للمواقع' : 'No location data available') + '</div>';
                return;
            }

            var Apex = getApex();
            if (!Apex) {
                console.warn('local_adminreport: ApexCharts is not available.');
                return;
            }

            var options = {
                chart: {
                    type: 'donut',
                    height: 320,
                    fontFamily: 'inherit',
                    toolbar: { show: false }
                },
                series: data.series,
                labels: data.labels,
                colors: palette,
                legend: {
                    position: 'bottom',
                    horizontalAlign: 'center',
                    fontFamily: 'inherit',
                    formatter: function(seriesName, opts) {
                        return seriesName + ': ' + opts.w.globals.series[opts.seriesIndex];
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return Math.round(val) + '%';
                    },
                    dropShadow: { enabled: false }
                },
                tooltip: {
                    theme: 'light',
                    y: {
                        formatter: function(val) {
                            return val + (isRtl ? ' متدرب' : ' trainees');
                        }
                    }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: isRtl ? 'الإجمالي' : 'Total',
                                    formatter: function(w) {
                                        return w.globals.seriesTotals.reduce(function(a, b) {
                                            return a + b;
                                        }, 0);
                                    }
                                }
                            }
                        }
                    }
                }
            };

            try {
                var chart = new Apex(el, options);
                chart.render();
                chartInstances[elementId] = chart;
            } catch (err) {
                console.error('local_adminreport: Failed to render locations chart:', err);
            }
        },

        /**
         * Render Program Classifications Stacked Column Chart.
         *
         * @param {string} elementId
         * @param {Object} data {categories: [], runs: [], trainees: []}
         * @param {boolean} isRtl
         */
        renderClassificationsChart: function(elementId, data, isRtl) {
            destroyChart(elementId);
            var el = document.getElementById(elementId);
            if (!el) {
                return;
            }

            if (!data || !data.categories || data.categories.length === 0) {
                el.innerHTML = '<div class="text-center text-muted p-4">' +
                    (isRtl ? 'لا توجد بيانات لتصنيفات البرامج' : 'No classification data available') + '</div>';
                return;
            }

            var Apex = getApex();
            if (!Apex) {
                console.warn('local_adminreport: ApexCharts is not available.');
                return;
            }

            var options = {
                chart: {
                    type: 'bar',
                    height: 320,
                    fontFamily: 'inherit',
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '50%',
                        borderRadius: 4
                    }
                },
                series: [
                    {
                        name: isRtl ? 'عدد البرامج المنفذة' : 'Runs Delivered',
                        data: data.runs
                    },
                    {
                        name: isRtl ? 'المتدربون' : 'Trainees',
                        data: data.trainees
                    }
                ],
                colors: ['#2563eb', '#10b981'],
                xaxis: {
                    categories: data.categories,
                    labels: {
                        rotate: -25,
                        style: { fontFamily: 'inherit' }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: isRtl ? 'right' : 'left',
                    fontFamily: 'inherit'
                },
                dataLabels: {
                    enabled: false
                },
                grid: {
                    borderColor: '#f1f5f9'
                }
            };

            try {
                var chart = new Apex(el, options);
                chart.render();
                chartInstances[elementId] = chart;
            } catch (err) {
                console.error('local_adminreport: Failed to render classifications chart:', err);
            }
        },

        /**
         * Render Monthly 6-Month Historical Trends Chart.
         *
         * @param {string} elementId
         * @param {Object} data {categories: [], runs: [], trainees: []}
         * @param {boolean} isRtl
         */
        renderTrendsChart: function(elementId, data, isRtl) {
            destroyChart(elementId);
            var el = document.getElementById(elementId);
            if (!el) {
                return;
            }

            if (!data || !data.categories || data.categories.length === 0) {
                el.innerHTML = '<div class="text-center text-muted p-4">' +
                    (isRtl ? 'لا توجد بيانات مسار تاريخي متاحة' : 'No trend data available') + '</div>';
                return;
            }

            var Apex = getApex();
            if (!Apex) {
                console.warn('local_adminreport: ApexCharts is not available.');
                return;
            }

            var options = {
                chart: {
                    height: 320,
                    type: 'line',
                    fontFamily: 'inherit',
                    toolbar: { show: false }
                },
                stroke: {
                    width: [2, 3],
                    curve: 'smooth'
                },
                plotOptions: {
                    bar: {
                        columnWidth: '35%',
                        borderRadius: 4
                    }
                },
                series: [
                    {
                        name: isRtl ? 'عدد البرامج المنفذة' : 'Runs Delivered',
                        type: 'column',
                        data: data.runs
                    },
                    {
                        name: isRtl ? 'المتدربون' : 'Trainees',
                        type: 'line',
                        data: data.trainees
                    }
                ],
                colors: ['#93c5fd', '#0d9488'],
                xaxis: {
                    categories: data.categories,
                    labels: { style: { fontFamily: 'inherit' } }
                },
                yaxis: [
                    {
                        title: {
                            text: isRtl ? 'عدد البرامج' : 'Runs',
                            style: { fontFamily: 'inherit' }
                        },
                        opposite: isRtl
                    },
                    {
                        opposite: !isRtl,
                        title: {
                            text: isRtl ? 'عدد المتدربين' : 'Trainees',
                            style: { fontFamily: 'inherit' }
                        }
                    }
                ],
                legend: {
                    position: 'top',
                    horizontalAlign: 'center',
                    fontFamily: 'inherit'
                },
                grid: {
                    borderColor: '#f1f5f9'
                }
            };

            try {
                var chart = new Apex(el, options);
                chart.render();
                chartInstances[elementId] = chart;
            } catch (err) {
                console.error('local_adminreport: Failed to render trends chart:', err);
            }
        }
    };
});
