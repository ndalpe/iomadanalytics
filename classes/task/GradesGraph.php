<?php
namespace report_iomadanalytics\task;

require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->dirroot . '/report/iomadanalytics/locallib.php');

class GradesGraph extends \core\task\scheduled_task
{

	// contain utilities function to process the report (contained in the locallib)
	public $reportUtils;

	// Contains all the countries in mdl_company
	public $Countries;

	// Contains all the companies in mdl_company
	public $Companies;

	// Get plugin renderer
	public $output;

	// Moodle public database abstraction layer
	public $DB;

	public function get_name()
	{
		// Shown in admin screens
		return get_string('pluginname', 'report_iomadanalytics');
	}

	public function execute()
	{
		if (!isset($DB))   {global $DB;}
		if (!isset($PAGE)) {global $PAGE;}

		$this->DB = $DB;

		$this->reportUtils = new \report_iomadanalytics_utils();

		$this->Countries = $this->reportUtils->getCountries(false);

		$this->Companies = $this->reportUtils->getCompanies();

		$this->output = $PAGE->get_renderer('report_iomadanalytics');

		$this->report = new \report_iomadanalytics();

		// Average Fianl Test Result Block
		$this->graphFinalGradesAllCompanies();

		// Course Progress All companies
		$this->graphProgressAllCompanies();
	}

	public function graphProgressAllCompanies()
	{
		$graphs = array();

		foreach ($this->Companies as $key => $company) {
			$notStarted = $this->reportUtils->getNotStartedCompany($company->id);
			$started = $this->reportUtils->getStartedComapany(array($company->id));
			$completed = $this->reportUtils->getCompletedCompany($company->id);
			$all = $notStarted+$started+$completed;

			$data = new \stdClass();
			$data->data = [
				$this->reportUtils->getPercent($notStarted, $all, $precision=false),
				$this->reportUtils->getPercent($started, $all, $precision=false),
				$this->reportUtils->getPercent($completed, $all, $precision=false)
			];
			$data->backgroundColor = array('#cc0000', '#ffcc00', '#33cc00');

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

		// Generate the graph data file for the current cohort
		$this->generateFile(
			'graph_progress_all_companies.json',
			json_encode($allGraph)
		);
	}

	public function graphFinalGradesAllCompanies()
	{
		// Simple counter to pick a color in barGraphColors
		$colorIndex = 0;

		$Companies = $this->reportUtils->getCompanies();
		foreach ($Companies as $company) {

			// get all courses
			$Courses = $this->reportUtils->getCourses();

			foreach ($Courses as $course) {

				// get all quiz for this course
				$Quiz = $this->reportUtils->getQuizByCourse($course->id);
				foreach ($Quiz as $quiz) {
					// $grading_info = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, array_keys($Students));
					// $labels[] = $grading_info->items[0]->name;
					$labels[] = $quiz->name;
					$d[] = $this->reportUtils->getAvgGrade($company->id, $quiz->id);
				}
			}
			$data[] = (object) ['data' => $d];

			// keep a copy of all grades for the combined graph
			$allGrades['labels'] = $labels;
			$dataAll[] = (object) [
				'data' => $d,
				'backgroundColor' => $this->reportUtils->getBarGraphColor($colorIndex, 3),
				'borderColor' => $this->reportUtils->getBarGraphColor($colorIndex, 5),
				'borderWidth' => 1,
				'label' => $company->shortname,
				'stack' => 'stak'.$company->shortname
			];

			// reset the graph per cohort data
			unset($labels, $data, $d);

			$colorIndex = $colorIndex + 1;
		}

		// Generate the graph data file for the current cohort
		$this->generateFile(
			'graph_grades_all_companies.json',
			$this->makeJSON(array('labels'=>$allGrades['labels'],'datasets'=>$dataAll))
		);
	}

/*
	public function graphAllCountries()
	{
		$Companies = $this->reportUtils->getCompanies();
		foreach ($Companies as $company) {
			$Students = $this->reportUtils->getStudentsInCompany($company->id);


			// get all courses
			$Courses = $this->reportUtils->getCourses();

			foreach ($Courses as $course) {

				// get all quiz for this course
				$Quiz = $this->reportUtils->getQuizByCourse($course->id);
				foreach ($Quiz as $quiz) {
					$grading_info = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, array_keys($Students));

					$labels[] = $grading_info->items[0]->name;
					$d[] = $this->reportUtils->getAvgPercentGrades($grading_info);
				}
			}
			$data[] = (object) ['data' => $d];

			// keep a copy of all grades for the combined graph
			$allGrades['labels'] = $labels;
			$dataAll[] = (object) [
				'data' => $d,
				'label' => $company->shortname,
				'stack' => 'stak'.$company->shortname
			];

			// reset the graph per cohort data
			unset($labels, $data, $d);
		}

		// Generate the graph data file for the current cohort
		$this->generateFile(
			'graph_all_companies.json',
			$this->makeJSON(array('labels'=>$allGrades['labels'], 'datasets'=>$dataAll))
		);
	}
*/
	private function makeJSON($vars)
	{
		$data = new \stdClass();
		$data->labels = $vars['labels'];
		$data->datasets = $vars['datasets'];

		if (isset($vars['options'])) {
			$data->options = $vars['options'];
		}

		return json_encode($data);
	}

	/**
	 * Retrieve the english part of a multi-lang string
	 * ie: <span class="multilang" lang="en">Join Date</span><span class="multilang" lang="id">bergabung</span>
	 *
	 * @param String $xmlstr The mlang XML string
	 * @return String English term
	 */
	// public function parseBiName($xmlstr)
	// {
	// 	if (!empty($xmlstr)) {
	// 		if (!is_object($this->domDoc)) {
	// 			$this->domDoc = new \domDocument('1.0', 'utf-8');
	// 			$this->domDoc->preserveWhiteSpace = false;
	// 		}
	// 		$this->domDoc->loadHTML($xmlstr);
	// 		$span = $this->domDoc->getElementsByTagName('span');
	// 		$str = $span->item(0)->nodeValue;
	// 	} else {
	// 		$str = '';
	// 	}

	// 	// Garbage
	// 	$span = '';

	// 	return $str;
	// }

	/**
	 * Return the plural form of $str
	 *
	 * @param int $num Number
	 * @param str $str Word to pluralize
	 * @return String English term
	 */
	public function pluralizer($num, $str)
	{
		if (is_numeric($num) || !empty($str)) {
			if ($num > 1) {
				$str .= 's';
			}
		} else {
			$str = '';
		}

		return $str;
	}

	public function generateFile($file, $content) {
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/iomadanalytics/templates/{$file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $content);
		fclose($myfile);
	}
}