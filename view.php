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

/******************************************/
/************** Set UI text ***************/
/******************************************/
$uiText = new \stdClass();
$uiText->systemoverview_block_title = get_string('systemoverview_block_title', 'report_iomadanalytics');
$report->setTplVars($uiText);


/******************************************/
/************** Output Page ***************/
/******************************************/
echo $output->header();
echo $output->render($report);
echo $output->footer();