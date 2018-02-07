define(
    [
        'jquery',
        '/report/iomadanalytics/amd/src/Chart.min.js',
        'core/ajax'
    ],
    function($, Chart, ajax) {
    return {
        init: function() {
            $(document).ready(function() {

                // Holds the country abbr
                // TODO: Ajax this
                var countries = ['ID', 'MY'],
                pluginPath = '/report/iomadanalytics/templates/',
                ProgressChartId = '';

                // Make the default final grades graph of all companies
                $.getJSON(pluginPath+"graph_grades_all_companies.json", function(gData){
                    new Chart(document.getElementById("chart-grades").getContext("2d"),
                        {type:'bar', data:gData, options: {
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
                });

                // Make the default progress graph of all companies
                $.getJSON(pluginPath+"graph_progress_all_companies.json", function(gData){
                    for (i in gData.companies) {
                        ProgressChartId = 'chart-progress-'+gData.companies[i].id;

                        $("#progressGraph").append('<div class="col-md-4"><canvas id="'+ProgressChartId+'"></canvas></div>');

                        new Chart(document.getElementById(ProgressChartId).getContext("2d"), {
                                type:'pie',
                                data:gData.companies[i].graph,
                                options:{
                                    legend: false,
                                    title: {text:gData.companies[i].company, display:true, position:'bottom'},
                                    tooltips: {
                                        enabled: true,
                                        mode: 'single',
                                        callbacks: {
                                            label: function(tooltip, data) {
                                                return data.labels[tooltip.index].replace('&nbsp;', ' ') + ' : ' + data.datasets[tooltip.datasetIndex].data[tooltip.index] + '%';
                                            }
                                        }
                                    }
                                }
                            }
                        );
                    }
                });//progress graph

                // Add listener to country's checkbox in the country selector
                for (var i = 0; i < countries.length; i++) {
                    toggleChekbox(countries[i]);
                }

                // refresh the grades graph when a company checkbox is checked/unchecked
                $(".country_company input").change(function(){
                    refreshGradesGraph();
                });

                function refreshGradesGraph() {
                    console.log('refreshGradesGraph');
                    $.getJSON(pluginPath+'graphGradesAllCompany.json', function(data){
                        $(".country_company input").each(function(){
                            if (this.checked) {
                                var selectedFilter = $("#selectedFilter").val();
                                console.log(selectedFilter);
                                if (selectedFilter != 'all') {
                                    console.log(data[$(this).val()]['filters'][selectedFilter]);
                                }
                            }
                        });

                        return data;
                    });

                }

                // check/uncheck all company under a country when the country checkbox is clicked
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

                $("#btn").click(function(){
                    testajax();
                });
                function testajax(){
                    alert('me');
                    var promises = ajax.call([
                        {methodname: 'report_iomadanalytics_filters', args: {filters: 'pluginname'}}
                    ]);

                    promises[0].done(function(response) {
                        alert('mod_wiki/pluginname is' + response);
                    }).fail(function(response) {
                        console.log(response);
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

                // set the selected filter in hidden element
                $('#filters_tab button').click(function(){
                    $("#selectedFilter").val($(this).data('filterid'));
                    resetFilterBtnStyle();
                    $(this).html('<i class="fa fa-check"></i> '+$(this).html()).addClass('bkgBtn');

                    // refresh the grades graph with the new filter
                    refreshGradesGraph();
                });

                // remove the check mark and bkg color on the selected filter button
                function resetFilterBtnStyle() {
                    $('#filters_tab button').each(function(){
                        $(this).html(
                            $("<div>").html($(this).html()).text()
                        )
                        .removeClass('bkgBtn');
                    });
                }

            }); // end document.ready
        } // end: init
    };
});