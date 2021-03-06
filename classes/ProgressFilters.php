<?php
class ProgressFilters
{
	// contain utilities function to process the report (contained in the locallib)
	public $reportUtils;

	// Contains all the countries in mdl_company
	public $Countries;

	// Contains all the companies in mdl_company
	public $Companies;

	// Simple Class to write data in Flat file
	public $FlatFile;

	// Moodle public database abstraction layer
	public $DB;

	// Custom Profile Field to exclude from filters
	// 3  : nationality
	// 11 : company : different country has different companies
	public $FieldToExclude = '3,11';

	// The current company id
	public $currentComapnyId;

	// Contains all primary courses
	public $Courses;

	public function get_name()
	{
		// Shown in admin screens
		return get_string('pluginname', 'report_iomadanalytics');
	}

	public function __construct()
	{
		if (!isset($DB)) {global $DB;}

		$this->DB = $DB;
		$this->reportUtils = new \report_iomadanalytics_utils();
		$this->Countries = $this->reportUtils->getCountries(false);
		$this->Companies = $this->reportUtils->getCompanies();
		$this->Courses = $this->reportUtils->getCourses();
	}

	/**
	 * Set the filter's id (costom profile field) (mdl_user_info_field)
	 *
	 * @param String $filter - The custom profile field ID
	*/
	public function setFilter($filter)
	{
		if (!empty($filter)) {
			$this->filter = $filter;
		}
	}

	/**
	 * Set the company/companies id
	 *
	 * @param Array $companies - The company ids
	*/
	public function setCompanies($companies)
	{
		if (is_array($companies)) {
			$this->companies = $companies;
		}
	}

	/**
	 * Get the costom profile field's data (mdl_user_info_field)
	*/
	public function getField()
	{
		return $this->DB->get_record('user_info_field', array('id'=>$this->filter));
	}

	/**
	 * Return the company ids either as a csv string of ID or as an array
	 *
	 * @param String $format - Wheter the company ids should be returned as an Array or a csv
	*/
	public function getCompanies($format = 'string')
	{
		if ($format == 'string') {
			$c = implode(',', $this->companies);
		} else {
			$c = $this->companies;
		}

		return $c;
	}

	/**
	 * Return an array containing all quiz name
	*/
	public function getQuizLabel()
	{
		// contains the quiz name as graph's labels
		$labels = array();

		// get the grades of $Students
		foreach ($this->Courses as $course) {

			// get all quiz for this course
			$Quiz = $this->reportUtils->getQuizByCourse($course->id);
			foreach ($Quiz as $quiz) {
				$labels[] = $quiz->name;
			}
		}

		return $labels;
	}

	/**
	 * Return the list of all countries letter code
	 *
	 * str $companies a comma separated string of company id uptained from $this->getCompanies()
	 */
	public function getCountryList($companies = '') {
		if (empty($companies)) {
			$Countries = $this->DB->get_records_sql("SELECT DISTINCT country FROM mdl_company");
		} else {
			$Countries = $this->DB->get_records_sql("SELECT DISTINCT country FROM mdl_company WHERE id IN ({$companies})");
		}

		return $Countries;
	}

	/**
	 * Separate the field values by country
	 * Countries are separated by "=== Country name" in the param1 field's values
	 *
	 * object $Field The profile field to filter
	 * object $countries The list of country to keep
	 *
	 * return object str the values possessed by all country
	 */
	public function getFiltersByCountry($Field, $countries) {

		// Contains the values to be returned
		$filteredValues = '';

		foreach ($countries as $key => $country) {

			// convert the country code into the country name ie. ID -> Indonesia
			$countryName = get_string($country->country, 'countries');

			// Separate the list by country (country value starts with '=== ')
			$values = explode("=== ", $Field->param1);

			foreach ($values as $key => $value) {
				if (strpos($values[$key], $countryName) !== false) {
					$filteredValues .= trim(str_replace($countryName, '', $values[$key]));
				}
			}
		}

		return $filteredValues;
	}

	/**
	 * Get a list country from the given company list
	 *
	 * return object The country list
	 */
	public function getCountriesForConpanies() {
		// Get a comma separated string of selected company id
		$selectedCompanies = $this->getCompanies();
		$Countries = $this->getCountryList($selectedCompanies);
		return $Countries;
	}

	public function getFiltersData()
	{
		if ($this->filter == 'all') {
			$Field = new stdClass();
			$Field->shortname = $this->filter;
		} else {
			$Field = $this->getField();
		}

		// Get the country of the selected company
		$selectedCountry = $this->getCountriesForConpanies();

		// Keep only the values from selected countries
		$filteredValues = $this->getFiltersByCountry($Field, $selectedCountry);
		$Field->param1 = $filteredValues;

		// Create method name to fetch field data
		$filterFuncName = 'fieldContent'.ucfirst(strtolower($Field->shortname));

		if (method_exists($this, $filterFuncName)) {
			$datasets = $this->$filterFuncName($Field);
		} else {
			$datasets = $this->fieldContentGeneric($Field);
		}

		$fieldDataset = new \stdClass();
		$fieldDataset->labels = $this->getQuizLabel();
		$fieldDataset->datasets = $datasets;

		return $fieldDataset;
	}

	/**
	 * Render grades graph when no filter are selected (ie.: on page load)
	*/
	public function fieldContentAll($field)
	{
		$graphs = array();

		$Companies = $this->DB->get_records_sql(
		    'SELECT * FROM mdl_company WHERE id IN('.$this->getCompanies().') ORDER BY country ASC;', array()
		);

		foreach ($Companies as $key => $company) {
			$notStarted = $this->reportUtils->getNotStartedCompany(array($company->id));
			$started = $this->reportUtils->getStartedComapany(array($company->id));
			$completed = $this->reportUtils->getCompletedCompany(array($company->id));
			$all = $notStarted+$started+$completed;

			$data = new \stdClass();
			$data->data = [
				$this->reportUtils->getPercent($notStarted, $all, $type='round', $precision=false),
				$this->reportUtils->getPercent($started, $all, $type='round', $precision=false),
				$this->reportUtils->getPercent($completed, $all, $type='round', $precision=false)
			];
			$data->backgroundColor = array(
				$this->reportUtils->stackGraphColors['red'],
				$this->reportUtils->stackGraphColors['yellow'],
				$this->reportUtils->stackGraphColors['green']
			);

			$datasets = array();
			$datasets['datasets'][] = $data;
			$datasets['labels'] = array(
				get_string('AllCtryProgressBlock_notStarted', 'report_iomadanalytics'),
				get_string('AllCtryProgressBlock_started', 'report_iomadanalytics'),
				get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics'),
			);

			$graph = new \stdClass();
			$graph->graph = $datasets;
			$graph->company = $company->shortname;
			$graph->id = $company->id;

			$graphs[] = $graph;
		}

		$allGraph = new \stdClass();
		$allGraph->companies = $graphs;

		return $allGraph;
	}

	/**
	 * Get progress from filter who doesn't need specific rendering (such as age group)
	 *
	 * @param object $field The current custom profile field object
	 * @return
	 *
	*/
	public function fieldContentGeneric($field)
	{
		// order the field values in the same order as the frontend
		$param1 = explode("\n", $field->param1);
		foreach ($param1 as $key => $param) {
			if (!empty($param) && $param != '=== Indonesia' && $param != '=== Malaysia') {
				$notStarted = $this->reportUtils->getNotStartedCompany($this->getCompanies('Array'));
				$started    = $this->reportUtils->getStartedFiltered($this->getCompanies('Array'), $field->id, $param);
				$completed  = $this->reportUtils->getCompletedFiltered($this->getCompanies('Array'), $field->id, $param);
				$all = $notStarted+$started+$completed;

				$data = new \stdClass();
				$data->data = [
					$this->reportUtils->getPercent($notStarted, $all, $type='round', $precision=false),
					$this->reportUtils->getPercent($started, $all, $type='round', $precision=false),
					$this->reportUtils->getPercent($completed, $all, $type='round', $precision=false)
				];
				$data->backgroundColor = array(
					$this->reportUtils->stackGraphColors['red'],
					$this->reportUtils->stackGraphColors['yellow'],
					$this->reportUtils->stackGraphColors['green']
				);

				$datasets = array();
				$datasets['datasets'][] = $data;
				$datasets['labels'] = array(
					get_string('AllCtryProgressBlock_notStarted', 'report_iomadanalytics'),
					get_string('AllCtryProgressBlock_started', 'report_iomadanalytics'),
					get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics'),
				);

				$graph = new \stdClass();
				$graph->graph = $datasets;
				$graph->company = $param;
				$graph->id = $key;

				$graphs[] = $graph;
			}
		}

		$allGraph = new \stdClass();
		$allGraph->companies = $graphs;

		return $allGraph;
	}

	/**
	 * Get profile from filter date of birth profile field
	 *
	 * @param object $field The DOB custom profile field object
	 * @return
	 *
	*/
	public function fieldContentJoindate($field)
	{
		$companyids = $this->getCompanies('string');

		// convert joindate timestamp into year
		// id	userid	fieldid	joindate	joinyear	companyid	userid
		// 168	22	    6	    870973200	1997	    3	        22
		// 320	47	    6	    951325200	2000	    3	        47
		// 9024	1129	6	    963853200	2000	    3	        1129
		$Joindate = $this->DB->get_records_sql("
			SELECT
			mdl_user_info_data.id, mdl_user_info_data.userid, mdl_user_info_data.fieldid, mdl_user_info_data.data AS joindate, DATE_FORMAT(FROM_UNIXTIME(data), '%Y') AS joinyear,
			mdl_company_users.companyid, mdl_company_users.userid
			FROM mdl_user_info_data
			INNER JOIN mdl_company_users ON mdl_user_info_data.userid = mdl_company_users.userid
			WHERE mdl_user_info_data.fieldid=6 AND mdl_company_users.companyid IN ({$companyids})
			ORDER BY CONVERT(mdl_user_info_data.data, UNSIGNED INTEGER);"
		, array(), $limitfrom=0, $limitnum=0);

		// cleanup the null
		// TODO : optimize the SQL query to exclude the NULL and Zeros from result set
		foreach ($Joindate as $key => $jd) {
			if (is_null($jd->joinyear)) {
				unset($Joindate[$key]);
			}
		}

		// get the min joindate
		$min = reset($Joindate);

		// get the max joindate
		$max = end($Joindate);
		reset($Joindate);

		// Create join year group (1980, 1990, 2000, 2010, etc)
		//  array range ( mixed $start , mixed $end [, number $step = 1 ] )
		$joinYearRange = range($min->joinyear, $max->joinyear, 10);

		// build age group array
		foreach ($joinYearRange as $key => $range) {

			// Get the next range key
			$nextRange = $key + 1;

			// if last range is reach, create a last range with the last range and $max->joinyear
			// 2000-2009, 2010-today
			if (isset($joinYearRange[$nextRange])) {
				$ageMax = $joinYearRange[$nextRange] - 1;
				$ageMin = $range;
				$ageGroups[$key]['ageMin'] = $ageMin;
				$ageGroups[$key]['ageMax'] = $ageMax;
				$ageGroups[$key]['tsMin'] = 0;
				$ageGroups[$key]['tsMax'] = 0;
				$ageGroups[$key]['ageCnt'] = 0;
			} else {
				$ageMax = (int)$max->joinyear;
				$ageMin = $range;
				$ageGroups[$key]['ageMin'] = $ageMin;
				$ageGroups[$key]['ageMax'] = $ageMax;
				$ageGroups[$key]['tsMin'] = 0;
				$ageGroups[$key]['tsMax'] = 0;
				$ageGroups[$key]['ageCnt'] = 0;
			}
		}

		// build the join date min/max values
		foreach ($Joindate as $age) {
			foreach ($ageGroups as $groupKey => $group) {

				$intAge = intval($age->joinyear);
				if ($intAge >= $group['ageMin'] && $intAge <= $group['ageMax']) {
					// Increment the age counter when group matches
					$ageGroups[$groupKey]['ageCnt']++;

					// set the min timestamp for age group
					if ($ageGroups[$groupKey]['tsMin'] == 0) {
						$ageGroups[$groupKey]['tsMin'] = $age->joindate;
					}
					if ($ageGroups[$groupKey]['tsMin'] < $age->joindate) {
						$ageGroups[$groupKey]['tsMin'] = $age->joindate;
					}

					// set the max timestamp for age group
					if ($ageGroups[$groupKey]['tsMax'] == 0) {
						$ageGroups[$groupKey]['tsMax'] = $age->joindate;
					}

					if ($ageGroups[$groupKey]['tsMax'] > $age->joindate) {
						$ageGroups[$groupKey]['tsMax'] = $age->joindate;
					}
				}
			}
		}

		// Remove age group with 0 student in it
		foreach ($ageGroups as $key => $group) {
			if ($group['ageCnt'] === 0) {
				unset($ageGroups[$key]);
			}
		}

		foreach ($ageGroups as $key => $group) {
			$where = array(
				'type' => 'MinMax',
				'min'  => $group['tsMin'],
				'max'  => $group['tsMax']
			);
			$notStarted = $this->reportUtils->getNotStartedCompany($this->getCompanies('Array'));
			$started    = $this->reportUtils->getStartedFiltered($this->getCompanies('Array'), $field->id, $where);
			$completed  = $this->reportUtils->getCompletedFiltered($this->getCompanies('Array'), $field->id, $where);
			$all = $notStarted+$started+$completed;

			$data = new \stdClass();
			$data->data = [
				$this->reportUtils->getPercent($notStarted, $all, $type='round', $precision=false),
				$this->reportUtils->getPercent($started,    $all, $type='round', $precision=false),
				$this->reportUtils->getPercent($completed,  $all, $type='round', $precision=false)
			];
			$data->backgroundColor = array(
				$this->reportUtils->stackGraphColors['red'],
				$this->reportUtils->stackGraphColors['yellow'],
				$this->reportUtils->stackGraphColors['green']
			);

			$datasets = array();
			$datasets['datasets'][] = $data;
			$datasets['labels'] = array(
				get_string('AllCtryProgressBlock_notStarted', 'report_iomadanalytics'),
				get_string('AllCtryProgressBlock_started', 'report_iomadanalytics'),
				get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics'),
			);

			$graph = new \stdClass();
			$graph->graph = $datasets;
			$graph->company = $group['ageMin'].'-'.$group['ageMax'];
			$graph->id = $key;

			$graphs[] = $graph;
		}

		$allGraph = new \stdClass();
		$allGraph->companies = $graphs;

		return $allGraph;

	}

	/**
	 * Return an array containing available age group found in mdl_user_info_data
	 *
	 * @param object $field The DOB custom profile field object
	 * @return array
	 *
	*/
	public function fieldContentDob($field)
	{
		$companyids = $this->getCompanies('string');

		$Age = $this->DB->get_records_sql('
			SELECT
			mdl_user_info_data.id, mdl_user_info_data.userid, mdl_user_info_data.fieldid, mdl_user_info_data.data AS dob, TIMESTAMPDIFF(YEAR, FROM_UNIXTIME(data), CURDATE()) AS age,
			mdl_company_users.companyid, mdl_company_users.userid
			FROM mdl_user_info_data
			INNER JOIN mdl_company_users ON mdl_user_info_data.userid = mdl_company_users.userid
			WHERE mdl_user_info_data.fieldid=1 AND mdl_company_users.companyid IN ('.$companyids.');
		', array(), $limitfrom=0, $limitnum=0);

		// cleanup the null and the 0s
		// TODO : optimize the SQL query to exclude the NULL and Zeros from result set
		foreach ($Age as $key => $value) {
			if ($value->age == 'NULL' || $value->age < '15') {
				unset($Age[$key]);
			}
		}

		// Create the age group (20, 25, 30, 35, etc)
		//  array range ( mixed $start , mixed $end [, number $step = 1 ] )
		$ageRange = range(15, 75, 10);

		// build age group array
		foreach ($ageRange as $key => $range) {

			// Get the next range key
			$nextRange = $key + 1;

			// if last range is reach, do not create a next ageGroup enrty
			if (isset($ageRange[$nextRange])) {
				$ageMax = $ageRange[$nextRange] - 1;
				$ageMin = $range;
				$ageGroups[$key]['ageMin'] = $ageMin;
				$ageGroups[$key]['ageMax'] = $ageMax;
				$ageGroups[$key]['tsMin'] = 0;
				$ageGroups[$key]['tsMax'] = 0;
				$ageGroups[$key]['ageCnt'] = 0;
			}
		}

		foreach ($Age as $age) {
			foreach ($ageGroups as $groupKey => $group) {

				$intAge = intval($age->age);
				if ($intAge >= $group['ageMin'] && $intAge <= $group['ageMax']) {
					// Increment the age counter when group matches
					$ageGroups[$groupKey]['ageCnt']++;

					// set the min timestamp for age group
					if ($ageGroups[$groupKey]['tsMin'] == 0) {
						$ageGroups[$groupKey]['tsMin'] = $age->dob;
					}
					if ($ageGroups[$groupKey]['tsMin'] < $age->dob) {
						$ageGroups[$groupKey]['tsMin'] = $age->dob;
					}

					// set the max timestamp for age group
					if ($ageGroups[$groupKey]['tsMax'] == 0) {
						$ageGroups[$groupKey]['tsMax'] = $age->dob;
					}

					if ($ageGroups[$groupKey]['tsMax'] > $age->dob) {
						$ageGroups[$groupKey]['tsMax'] = $age->dob;
					}
				}
			}
		}

		// Remove age group with 0 student in it
		foreach ($ageGroups as $key => $group) {
			if ($group['ageCnt'] === 0) {
				unset($ageGroups[$key]);
			}
		}

		foreach ($ageGroups as $key => $group) {
			$where = array(
				'type' => 'MinMax',
				'min'  => $group['tsMin'],
				'max'  => $group['tsMax']
			);
			$notStarted = $this->reportUtils->getNotStartedCompany($this->getCompanies('Array'));
			$started    = $this->reportUtils->getStartedFiltered($this->getCompanies('Array'), $field->id, $where);
			$completed  = $this->reportUtils->getCompletedFiltered($this->getCompanies('Array'), $field->id, $where);
			$all = $notStarted+$started+$completed;

			$data = new \stdClass();
			$data->data = [
				$this->reportUtils->getPercent($notStarted, $all, $type='round', $precision=false),
				$this->reportUtils->getPercent($started,    $all, $type='round', $precision=false),
				$this->reportUtils->getPercent($completed,  $all, $type='round', $precision=false)
			];
			$data->backgroundColor = array(
				$this->reportUtils->stackGraphColors['red'],
				$this->reportUtils->stackGraphColors['yellow'],
				$this->reportUtils->stackGraphColors['green']
			);

			$datasets = array();
			$datasets['datasets'][] = $data;
			$datasets['labels'] = array(
				get_string('AllCtryProgressBlock_notStarted', 'report_iomadanalytics'),
				get_string('AllCtryProgressBlock_started', 'report_iomadanalytics'),
				get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics'),
			);

			$graph = new \stdClass();
			$graph->graph = $datasets;
			$graph->company = $group['ageMin'].'-'.$group['ageMax'];
			$graph->id = $key;

			$graphs[] = $graph;
		}

		$allGraph = new \stdClass();
		$allGraph->companies = $graphs;

		return $allGraph;

	} //fieldContentDob()
}
// $this->DB->set_debug(true);
// $this->DB->set_debug(false);