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

// Get custom renderer
$output = $PAGE->get_renderer('report_iomadanalytics');

$report = new report_iomadanalytics();
$reportUtils = new report_iomadanalytics_utils();

// CntryList

/*****************************************************/
/************** Country Selector Block ***************/
/*****************************************************/
$Countries = $reportUtils->getCountries();
foreach ($Countries as $country) {

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


/******************************************/
/************** Set UI text ***************/
/******************************************/
$uiText = new \stdClass();
$uiText->systemoverview_block_title = get_string('systemoverview_block_title', 'report_iomadanalytics');
$uiText->countryselector_block_title = get_string('countryselector_block_title', 'report_iomadanalytics');
$report->setTplVars($uiText);


/******************************************/
/************** Output Page ***************/
/******************************************/
echo $output->header();
echo $output->render($report);
echo $output->footer();