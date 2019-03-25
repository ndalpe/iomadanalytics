<?php
namespace report_iomadanalytics\task;

require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->dirroot . '/report/iomadanalytics/locallib.php');
require_once($CFG->dirroot . '/report/iomadanalytics/classes/FlatFile.php');

class GradesFilters extends \core\task\scheduled_task
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

	public function execute()
	{
		if (!isset($DB))   {global $DB;}

		$this->DB = $DB;
		$this->reportUtils = new \report_iomadanalytics_utils();
		$this->Countries = $this->reportUtils->getCountries(false);
		$this->Companies = $this->reportUtils->getCompanies();
		$this->Courses = $this->reportUtils->getCourses();
		// $this->report = new \report_iomadanalytics();
		$this->FlatFile = new \FlatFile();

		$content = $this->gradesPerCompanies();
		$this->FlatFile->setFileName('graphGradesAllCompany.json');
		$this->FlatFile->setFileContent($content);
		$this->FlatFile->writeToFile();
	}

	public function gradesPerCompanies()
	{
		$comapnies = array();
		foreach ($this->Companies as $company) {

			// set the company id avalable to all method
			$this->currentComapnyId = $company->id;

			$comapnies[$company->shortname] = (object) array(
				'id' => $company->id,
				'shortname' => $company->shortname,
				'name' => $company->name,
				'filters' => $this->getFiltersData()
			);
		}

		return json_encode($comapnies);
	}

	public function getFiltersData()
	{
		$Fields = $this->DB->get_records_sql(
			'SELECT id, shortname, name, datatype, param1 FROM mdl_user_info_field WHERE id NOT IN('.$this->FieldToExclude.') ORDER BY sortorder ASC;', array(), $limitfrom=0, $limitnum=0
		);

		foreach ($Fields as $field) {
			if (is_object($field)) {

				// Create method name to fetch field data
				$filterFuncName = 'fieldContent'.ucfirst($field->shortname);

				if (method_exists($this, $filterFuncName)) {
					$datasets = $this->$filterFuncName($field);
				} else {
					$datasets = $this->fieldContentGeneric($field);
				}

				$fieldDataset[$field->id] = array(
					'datasets' => $datasets
				);
			}
		}

		return $fieldDataset;
	}

	/**
	 * Get grades from filter date of birth profile field
	 *
	 * @param object $field The DOB custom profile field object
	 * @return array Array containing available age group found in mdl_user_info_data
	 *
	*/
	public function fieldContentDob($field)
	{
		$Age = $this->DB->get_records_sql('
			SELECT
			mdl_user_info_data.id, mdl_user_info_data.userid, mdl_user_info_data.fieldid, mdl_user_info_data.data AS dob, TIMESTAMPDIFF(YEAR, FROM_UNIXTIME(data), CURDATE()) AS age,
			mdl_company_users.companyid, mdl_company_users.userid
			FROM mdl_user_info_data
			INNER JOIN mdl_company_users ON mdl_user_info_data.userid = mdl_company_users.userid
			WHERE mdl_user_info_data.fieldid=1 AND mdl_company_users.companyid=:companyid;
		', array('companyid' => $this->currentComapnyId), $limitfrom=0, $limitnum=0);

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

		foreach ($Age as $key => $age) {
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

		// get the average grade per age groups for all tests
		foreach ($ageGroups as $key => $group) {

			// get the grades of $Students
			foreach ($this->Courses as $course) {

				// get all quiz for this course
				$Quiz = $this->reportUtils->getQuizByCourse($course->id);
				foreach ($Quiz as $quiz) {
					if ($group['ageCnt'] == 1) {
						$Students = $this->DB->get_records_sql("
							SELECT AVG(mdl_quiz_grades.grade) AS grades
							FROM mdl_user_info_data
							INNER JOIN mdl_company_users ON mdl_user_info_data.userid = mdl_company_users.userid
							INNER JOIN mdl_quiz_grades ON mdl_user_info_data.userid = mdl_quiz_grades.userid
							WHERE mdl_user_info_data.fieldid=1 AND mdl_company_users.companyid=:companyid AND mdl_quiz_grades.quiz=:quizid AND data='".$group['tsMin']."'
							GROUP BY mdl_quiz_grades.quiz",
							array('companyid'=>$this->currentComapnyId, 'quizid'=>$quiz->id)
						);
					} else {
						$Students = $this->DB->get_records_sql("
							SELECT AVG(mdl_quiz_grades.grade) AS grades
							FROM mdl_user_info_data
							INNER JOIN mdl_company_users ON mdl_user_info_data.userid = mdl_company_users.userid
							INNER JOIN mdl_quiz_grades ON mdl_user_info_data.userid = mdl_quiz_grades.userid
							WHERE mdl_user_info_data.fieldid=1 AND mdl_company_users.companyid=:companyid AND mdl_quiz_grades.quiz=:quizid AND (data>'".$group['tsMax']."' AND data<'".$group['tsMin']."')
							GROUP BY mdl_quiz_grades.quiz",
							array('companyid'=>$this->currentComapnyId, 'quizid'=>$quiz->id)
						);
					}

					// Do not add the field value if no student has passed the test yet
					// If no student has passed the test for his age group or department
					if (count($Students) != 0) {
						$sumGrades = $this->DB->get_record('quiz', array('id'=>$quiz->id), $fields='sumgrades');
						$avgGrades = reset($Students);
						$avgGrade  = ($avgGrades->grades / $sumGrades->sumgrades) * 100;
						$ageGroups[$key]['avgGrade'][] = array(
							'avgGrade' => $avgGrade,
							'quiz' => $quiz->name
						);
					}
				}
			}
		}

		foreach ($ageGroups as $key => $group) {
			$a = new \stdClass();
			$a->label = $group['ageMin'].'-'.$group['ageMax'];
			$a->stack = 'stack'.$group['ageMin'].$group['ageMax'];
			if (isset($group['avgGrade'])) {
				foreach ($group['avgGrade'] as $key => $grade) {
					$a->data[] = round($grade['avgGrade']);
				}
			} else {
				// zero fill the array if no grades are found
				// array array_fill ( int $start_index , int $num , mixed $value )
				$a->data = array_fill(0, count($this->Courses), 0);
			}
			$datasets[] = $a;
			unset($a);
		}

		// if company doesn't have any age group yet (no registered student)
		if (!isset($datasets)) {
			$datasets[] = false;
		}

		return $datasets;
	} //fieldContentDob()
}
// $this->DB->set_debug(true);
// $this->DB->set_debug(false);