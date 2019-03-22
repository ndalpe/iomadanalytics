define(
    [
        'jquery',
        'core/ajax',
        'core/templates',
        '/report/iomadanalytics/amd/src/Chart.min.js'
    ],
    function($, ajax, templates, Chart) {
    return {
        init: function() {
            $(document).ready(function() {

                // Holds the country abbr
                // TODO: Ajax this
                var countries = ['ID', 'MY'],
                pluginPath = '/report/iomadanalytics/templates/',
                gradesGraph = null,
                ProgressChartId = '',
                body = $("body");

                // Add listener to country's checkbox in the country selector
                for (var i = 0; i < countries.length; i++) {toggleChekbox(countries[i]);}

                // refresh the grades graph when a company checkbox is checked/unchecked
                $(".country_company input").change(function(){refreshGradesGraph();});

                // Blocks :
                // Progress of the past 12 months,
                // Average Final Test Result,
                // and Global Courses Progress
                if (body.hasClass('view_stats_all')) {
                    // Global Courses Progress
                    $.getJSON(pluginPath+"systemoverview_rendered.json", function(systemOverviewData) {
                        for (var country in countries) {
                            for (var graph in systemOverviewData[countries[country]]) {

                                // Define chart ID name
                                // chart + block name in template + country code uppercase
                                var chartHtmlId = "chart-"+graph+"-"+countries[country];

                                // Chart data
                                var chartData = systemOverviewData[countries[country]][graph];

                                // Make the actual chart
                                makeStackedGraph(chartHtmlId,chartData);
                            }
                        }
                    });

                    // Progress of the past 12 months
                    $.getJSON(pluginPath+"allCtryProgressYearBlock.json", function(gData){
                        new Chart(document.getElementById("chart-progressyear").getContext("2d"), gData);
                    });
                }

                // Make the initial final grades graph of all companies without filter
                refreshGradesGraph();

                function makeStackedGraph(graphCanvasId, data) {

                    // check if the HTML Element exists
                    var chartElement = document.getElementById(graphCanvasId);
                    if (chartElement !== null) {
                        // Create the chart
                        new Chart(chartElement.getContext("2d"), {
                            type: 'horizontalBar',
                            data: data,
                            options: {
                                labels: {display: false},
                                legend: {display: false},
                                title: {display: false},
                                tooltips: {mode: 'index', intersect: false},
                                responsive: true,
                                scales: {
                                    xAxes: [{stacked: true, display: false}],
                                    yAxes: [{stacked: true, display: false, barThickness: 8}]
                                }
                            }
                        });
                    } else {
                        console.log(graphCanvasId + " not found");
                    }
                }

                /**
                 * Ajax a new dataset according active country and filter
                */
                function refreshGradesGraph() {

                    // Freeze all checkbox and button while the graph is being refreshed
                    freezeControl(true);

                    var companies = getSelectedCompanies();

                    // get the selected filter
                    var filters = [];
                    filters.push($("#selectedFilter").val());

                    // build param object
                    var params = JSON.stringify({companies:companies, filters:filters});

                    var promises = ajax.call(
                        [{methodname:'report_iomadanalytics_filters', args:{filters:params}}]
                    );

                    promises[0]
                    .done(function(response) {

                        // update graph details block
                        updateGraphDetails(filters, companies);

                        var graphsData = $.parseJSON(response);

                        // Make the grades graph with newly received data
                        makeGradesGraph(graphsData.grades);

                        // Make the progress graph with newly received data
                        makeProgressGraph(graphsData.progress);

                        // unfreeze controls
                        freezeControl(false);
                    })
                    .fail(function(response) {

                        // unfreeze controls
                        freezeControl(false);
                    });

                }

                function makeProgressGraph(progressGraphData) {
                    // remove previously created pie chart
                    Chart.helpers.each(Chart.instances, function(instance){
                        var n = instance.chart.canvas.id;
                        if (n.includes('chart-progress-') === true) {
                            instance.chart.destroy();
                        }
                    });

                    // remove extra markup
                    $("#progressGraph").html('');

                    for (i in progressGraphData.datasets.companies) {
                        ProgressChartId = 'chart-progress-'+progressGraphData.datasets.companies[i].id;

                        $("#progressGraph").append('<div class="col-md-4"><canvas id="'+ProgressChartId+'"></canvas></div>');

                        new Chart(document.getElementById(ProgressChartId).getContext("2d"), {
                                type:'pie',
                                data:progressGraphData.datasets.companies[i].graph,
                                options:{
                                    legend: false,
                                    title: {text:progressGraphData.datasets.companies[i].company, display:true, position:'bottom'},
                                    tooltips: {
                                        enabled: true,
                                        mode: 'single',
                                        callbacks: {
                                            label: function(tooltip, data) {
                                                return data.labels[tooltip.index].replace('&nbsp;', ' ') + ' : ' +
                                                       data.datasets[tooltip.datasetIndex].data[tooltip.index] + '%';
                                            }
                                        }
                                    }
                                }
                            }
                        );
                    }
                }

                /**
                 * Creates the grade bar chart according to new dataset passed in attributes
                 * param: gradesGraphData JSON - The new graph's dataset
                */
                function makeGradesGraph(gradesGraphData) {
                    // if the graph object exists, destroy it
                    // to avoid hover glitch with previous datasets
                    if (gradesGraph!==null) {
                        gradesGraph.destroy();
                    }

                    // create the graph
                    gradesGraph = new Chart(document.getElementById("chart-grades").getContext("2d"),
                        {type:'bar', data:gradesGraphData, options: {
                            scales: {yAxes: [{ticks: {beginAtZero:true}}]},
                            tooltips: {
                                enabled: true,
                                mode: 'single',
                                callbacks: {
                                    label: function(tooltip, data) {
                                        return data.datasets[tooltip.datasetIndex].label + ' : ' + tooltip.yLabel + '%';
                                    }
                                }
                            }
                        }}
                    );
                }

                function updateGraphDetails(filters, companies) {

                    // Define filter name
                    // If no filter is selected, #selectedFilter is set to all
                    // if a filter is selected, #selectedFilter is set to the filter's id
                    var filterName = '';
                    if ($("#selectedFilter").val() == 'all') {
                        filterName = 'None';
                    } else {
                        filterName = $("<div>").html($("button[data-filterid='"+filters+"'").html()).text();
                    }

                    templates.render(
                        'report_iomadanalytics/filter_selection_details',
                        {filter:filterName, CompanyList:getSelectedCompanyName(companies)}
                    )
                    .then(function(html, js){
                        templates.replaceNodeContents('.filterSelectionDetails', html, js);
                    })
                    .fail(function(ex){
                        console.log(ex);
                    });
                }

                /**
                 * Check/uncheck all company under a country
                 * param: country string - The country's 2 letters abbr (ie.: ID or MY or ...)
                */
                function toggleChekbox(country) {
                    // check/uncheck all checkbox of the country
                    $("input#"+country).click(function() {
                        // Set the company checkbox state eq to the country's checkbox state
                        $('.country-'+country+' input').prop('checked', $(this).prop('checked'));

                        // refresh grades graph with new country selection
                        refreshGradesGraph();
                    });
                }

                /**
                 * Disable the country selector and filters button
                 * param: state bool - whether the input should be disable or not
                */
                function freezeControl(state) {
                    // Company Admin
                    // Do not unfreeze the controls if there is only one company
                    // otherwise it would be possible to refresh the graphs with no company ID
                    if (body.hasClass('view_stats_company') === true) {
                        $(".country_company input, .country input").attr('disabled', true);
                    }

                    // Country Admin
                    // Do not allow unselect all company by unchecking the country checkbox
                    if (body.hasClass('view_stats_country') === true) {
                        $(".country input").attr('disabled', true);
                        $(".country_company input").attr('disabled', state);
                    }

                    // Almighty
                    // Check/Uncheck everything
                    if (body.hasClass('view_stats_all') === true) {
                        $(".country_company input, .country input").attr('disabled', state);
                    }

                    // filters button
                    $("#filters_tab button").attr('disabled', state);
                }

                /**
                 * Set the selected filter in hidden element
                */
                $('#filters_tab button').click(function(){
                    $("#selectedFilter").val($(this).data('filterid'));
                    resetFilterBtnStyle();
                    $(this).html('<i class="fa fa-check"></i> '+$(this).html()).addClass('bkgBtn');

                    // refresh the grades graph with the new filter
                    refreshGradesGraph();
                });

                /**
                 * Remove the check mark and bkg color on the selected filter button
                */
                function resetFilterBtnStyle() {
                    $('#filters_tab button').each(function(){
                        $(this).html(
                            $("<div>").html($(this).html()).text()
                        )
                        .removeClass('bkgBtn');
                    });
                }

                // vertical tabs
                $("div.bhoechie-tab-menu>div.list-group>a").click(function(e) {
                    e.preventDefault();
                    $(this).siblings('a.active').removeClass("active");
                    $(this).addClass("active");
                    var index = $(this).index();
                    $("div.bhoechie-tab>div.bhoechie-tab-content").removeClass("active");
                    $("div.bhoechie-tab>div.bhoechie-tab-content").eq(index).addClass("active");
                });

                /**
                 * Return a csv string of selected companies' shortname
                 * param: array companies - The array of selected companies
                */
                function getSelectedCompanyName(companies) {
                    var c=[];
                    $.each(companies, function(index) {
                        c.push($("#"+companies[index]).val());
                    });

                    return c.join(', ').toUpperCase();
                }

                /**
                 * Return an array of selected companies
                */
                function getSelectedCompanies() {
                    // Get selected companies
                    var companies = [];
                    $(".country_company input").each(function(){
                        if (this.checked) {
                            companies.push($(this).attr('id'));
                        }
                    });
                    return companies;
                }
            }); // end document.ready
        } // end: init
    };
});
