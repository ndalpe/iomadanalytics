<?php
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event documentation.
 *
 * @package   report_eventlist
 * @copyright 2014 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login(0, false);

$PAGE->set_url('/report/iomadanalytics/view.php');

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// Get custom renderer
$output = $PAGE->get_renderer('report_iomadanalytics');

$report = new report_iomadanalytics();

// Utility class to get the report's data
$reportUtils = new report_iomadanalytics_utils();

// Get all countries
$Countries = $reportUtils->getCountries(false);

/**************************************************/
/********** Average Grade For Fianl Test **********/
/**************************************************/


foreach ($Countries as $key => $country) {
	$Companies = $reportUtils->getCompaniesInCountry($country->country);

	// company if withing the current country
	$company_ids = array();

	// compile all company id from the current country and implode it to put in an IN() statement
	foreach ($Companies as $company) {
		$company_ids[] = $company->id;
	}
	$comp_id = implode(',', $company_ids);

	// store country average
	$countryFinalTestAvg[] = array(
		'name' => get_string($country->country, 'countries'),
		'grade' => $reportUtils->getAvgGrade($comp_id, 12)
	);
}
// avg of all country
$allCtry = 0;
foreach ($countryFinalTestAvg as $value) {
	$allCtry += $value['grade'];
}
$allCtryAvg = $allCtry / count($countryFinalTestAvg);

// All country final test avg bock data
$allCtryBlockData = array();
$allCtryBlockData['header'] = get_string('AllCtryAvgBlock_title', 'report_iomadanalytics');
$allCtryBlockData['keyMetric'] = $allCtryAvg.'%';
$allCtryBlockData['countries'] = $countryFinalTestAvg;

// set AllCtryAvgBlock in template
$allCtryBlockTlpData = new stdClass();
$allCtryBlockTlpData->name = 'AllCtryAvgBlock';
$allCtryBlockTlpData->data = $allCtryBlockData;
$report->setTplBlock($allCtryBlockTlpData);
/**************************************************/
/********** Average Grade For Fianl Test **********/
/**************************************************/


/**************************************************/
/********* All Countries Course Progress **********/
/**************************************************/

foreach ($Countries as $key => $country) {
	$allCtryProgressData[] = array(
		'name' => get_string($country->country, 'countries'),
		'notStarted' => $reportUtils->getNotStarted($country->country),
		'started'    => $reportUtils->getStarted($country->country),
		'completed'  => $reportUtils->getCompleted($country->country)
	);
}

// All country final test avg bock data
$allCtryBlockData = array();
$allCtryBlockData['header'] = get_string('AllCtryProgressBlock_title', 'report_iomadanalytics');
$allCtryBlockData['keyMetric'] = '';
$allCtryBlockData['countries'] = $allCtryProgressData;

// set AllCtryProgressBlock in template
$allCtryProgBlockTlpData = new stdClass();
$allCtryProgBlockTlpData->name = 'AllCtryProgressBlock';
$allCtryProgBlockTlpData->data = $allCtryBlockData;
$report->setTplBlock($allCtryProgBlockTlpData);
/**************************************************/
/********* All Countries Course Progress **********/
/**************************************************/


echo $output->header();
echo $output->render($report);
echo $output->footer();