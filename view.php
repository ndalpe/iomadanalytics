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
require_once('locallib.php');

require_login(0, false);

$PAGE->set_url('/report/iomadanalytics/view.php');

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// Set the HTML <title> tag
$PAGE->set_title(get_string('report_page_title', 'report_iomadanalytics'));

// Set the page heading (big title before content)
$PAGE->set_heading(get_string('report_page_title', 'report_iomadanalytics'));

$PAGE->requires->js_call_amd('report_iomadanalytics/iomadanalytics', 'init');


// Get custom renderer
$output = $PAGE->get_renderer('report_iomadanalytics');

$report = new report_iomadanalytics();
$reportUtils = new report_iomadanalytics_utils();

/*****************************************************/
/************** Country Selector Block ***************/
/*****************************************************/
$Countries = $reportUtils->getCountries();
foreach ($Countries as $country) {

	// get companies per country and make the checkbox list
	// the companies in the country are rendered here cuz mustache doesn't take nested blocks
	$Companies = $reportUtils->getCompaniesInCountry($country->country);
	foreach ($Companies as $companie) {
		$companiesList[] = array(
			'name' => $companie->name,
			'shortname' => $companie->shortname,
			'id' => $companie->id,
			'country' => $companie->country
		);
	}
	$companiesListBlock = new \stdClass();
	$companiesListBlock->name = 'CompanyList';
	$companiesListBlock->data = $companiesList;
	$reportCie = new report_iomadanalytics();
	$reportCie->setTplBlock($companiesListBlock);
	$companieBlockRendered = $output->render_companyList($reportCie);

	$countiesList[] = array(
		'country_abbr' => $country->country,
		'country_name' => get_string($country->country, 'countries'),
		'companies' => $companieBlockRendered
	);
	unset($reportCie, $companieBlockRendered, $companiesList);
}
// set CntryList in template
$cntrySelBlockData = new \stdClass();
$cntrySelBlockData->name = 'CntryList';
$cntrySelBlockData->data = $countiesList;
$report->setTplBlock($cntrySelBlockData);


/*****************************************************/
/************** Filters Selector Block ***************/
/*****************************************************/
// Custom Profile Field to exclude from filters
// 3  : nationality : abandoned since the data was mono-nationality
// 11 : company : different country has different companies
$Filters = $DB->get_records_sql(
	'SELECT id, shortname, name, datatype, param1 FROM mdl_user_info_field WHERE id NOT IN(3,11) ORDER BY sortorder ASC;', array(), $limitfrom=0, $limitnum=0
);

foreach ($Filters as $filter) {
	$filtersList[] = array(
		'id' => $filter->id,
		'shortname' => $filter->shortname,
		'name' => $reportUtils->parseBiName($filter->name),
		'datatype' => $filter->datatype,
		'param1' => $filter->param1
	);
}
// set Filters List in template
$filtersListBlock = new \stdClass();
$filtersListBlock->name = 'FilterList';
$filtersListBlock->data = $filtersList;
$report->setTplBlock($filtersListBlock);

/******************************************/
/************** Output Page ***************/
/******************************************/
echo $output->header();
echo $output->render($report);
echo $output->footer();