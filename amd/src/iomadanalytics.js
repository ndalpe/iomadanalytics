define(
    [
        'jquery',
        '/report/iomadanalytics/amd/src/Chart.min.js'
    ],
    function($, Chart) {
    return {
        init: function() {
            $(document).ready(function() {

                // Holds the country abbr
                // TODO: Ajax this
                var countries = ['ID', 'MY'],
                pluginPath = '/report/iomadanalytics/templates/';

                // Add listener to country's checkbox in the country selector
                for (var i = 0; i < countries.length; i++) {
                    toggleChekbox(countries[i]);
                }

                $.getJSON(pluginPath+"graph_all_companies.json", function(gData){
                    new Chart(document.getElementById("chart-grades").getContext("2d"),
                        {type:'bar', data:gData, options: {
                            scales: {yAxes: [{ticks: {beginAtZero:true}}]}
                        }}
                    );
                });

                function toggleChekbox(country) {
                    $("#"+country).click(function() {
                        if ($("#"+country).prop('checked') === true) {
                            $('.country-'+country+' input').prop('checked', true);
                        } else {
                            $('.country-'+country+' input').prop('checked', false);
                        }
                    });
                } // end toggleChekbox

            }); // end document.ready
        } // end: init
    };
});