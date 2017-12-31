<?php

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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    localwstemplate
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$services = array(
	'report_iomadanalytics_external' => array( //the name of the web service
		'functions' => array ('report_iomadanalytics_filters'),
		'restrictedusers' => 0,
		'enabled'=>1,
	)
);

// We defined the web service functions to install.
$functions = array(
	'report_iomadanalytics_filters' => array(
		'classname'   => 'report_iomadanalytics_external',
		'methodname'  => 'filter_grades',
		'classpath'   => 'report/iomadanalytics/externallib.php',
		'description' => 'Add filter to grade graph',
		'type'        => 'read',
		'ajax'  => true
	)
);
