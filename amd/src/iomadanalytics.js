define(
    [
        'jquery',
        'core/ajax',
        '/report/iomadanalytics/amd/src/Chart.min.js'
    ],
    function($, ajax, Chart) {
    return {
        init: function() {
            $(document).ready(function() {

                // Holds the country abbr
                // TODO: Ajax this
                var countries = ['ID', 'MY'],
                pluginPath = '/report/iomadanalytics/templates/',
                gradesGraph = null;
                // ProgressChartId = '';

                // Make the default final grades graph of all companies
                $.getJSON(pluginPath+"graph_grades_all_companies.json", function(gData){
                    makeGradesGraph(gData);
                });

                // Make the default progress graph of all companies
                // $.getJSON(pluginPath+"graph_progress_all_companies.json", function(gData){
                //     for (i in gData.companies) {
                //         ProgressChartId = 'chart-progress-'+gData.companies[i].id;

                //         $("#progressGraph").append('<div class="col-md-4"><canvas id="'+ProgressChartId+'"></canvas></div>');

                //         new Chart(document.getElementById(ProgressChartId).getContext("2d"), {
                //                 type:'pie',
                //                 data:gData.companies[i].graph,
                //                 options:{
                //                     legend: false,
                //                     title: {text:gData.companies[i].company, display:true, position:'bottom'},
                //                     tooltips: {
                //                         enabled: true,
                //                         mode: 'single',
                //                         callbacks: {
                //                             label: function(tooltip, data) {
                //                                 return data.labels[tooltip.index].replace('&nbsp;', ' ') + ' : ' + data.datasets[tooltip.datasetIndex].data[tooltip.index] + '%';
                //                             }
                //                         }
                //                     }
                //                 }
                //             }
                //         );
                //     }
                // });//progress graph

                // Add listener to country's checkbox in the country selector
                for (var i = 0; i < countries.length; i++) {
                    toggleChekbox(countries[i]);
                }

                // refresh the grades graph when a company checkbox is checked/unchecked
                $(".country_company input").change(function(){
                    refreshGradesGraph();
                });

                /**
                 * Ajax a new dataset according active country and filter
                */
                function refreshGradesGraph() {

                    // Freeze all checkbox and button while the graph is being refreshed
                    freezeControl(true);

                    // Get selected companies
                    var companies = [];
                    $(".country_company input").each(function(){
                        if (this.checked) {
                            companies.push($(this).attr('id'));
                        }
                    });

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
                        // Make the graph with newly received data
                        makeGradesGraph($.parseJSON(response));

                        // unfreeze controls
                        freezeControl(false);
                    })
                    .fail(function(response) {
                        console.log('fail');
                        console.log(response);
                    });
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

                /**
                 * Check/uncheck all company under a country
                 * param: country string - The country's 2 letters abbr (ie.: ID or MY or ...)
                */
                function toggleChekbox(country) {
                    // check/uncheck all checkbox of the country
                    $("#"+country).click(function() {
                        if ($("#"+country).prop('checked') === true) {
                            $('.country-'+country+' input').prop('checked', true);
                        } else {
                            $('.country-'+country+' input').prop('checked', false);
                        }
                        // refresh grades graph with new country selection
                        refreshGradesGraph();
                    });
                } // end toggleChekbox()

                /**
                 * Disable the country selector and filters button
                 * param: state bool - whether the input should be disable or not
                */
                function freezeControl(state) {
                    // freeze countries checkbox
                    $(".country_company input, .country input, #filters_tab button").each(function(){
                        $(this).attr('disabled', state);
                    });
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

            }); // end document.ready
        } // end: init
    };
});