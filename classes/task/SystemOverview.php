<?php
namespace report_iomadanalytics\task;

require_once($CFG->dirroot . '/report/iomadanalytics/locallib.php');
require_once($CFG->dirroot . '/report/iomadanalytics/classes/FlatFile.php');

class SystemOverview extends \core\task\scheduled_task
{

	// contain utilities function to process the report (contained in the locallib)
	public $reportUtils;

	// Contains all the conutries in mdl_company
	public $Countries;

	// Get plugin renderer
	public $output;

	// Moodle public database abstraction layer
	public $DB;

	// Contains the object structure for Final test avg and progress
	public $chartData;

	public function get_name()
	{
		// Shown in admin screens
		return get_string('pluginname', 'report_iomadanalytics');
	}

	public function execute()
	{
		if (!isset($DB))   {global $DB;}
		if (!isset($PAGE)) {global $PAGE;}

		$PAGE->set_context(\context_system::instance());

		$this->DB = $DB;

		$this->reportUtils = new \report_iomadanalytics_utils();

		$this->Countries = $this->reportUtils->getCountries(false);

		$this->output = $PAGE->get_renderer('report_iomadanalytics');

		$this->report = new \report_iomadanalytics();

		// Contains the PHP data structure for the
		// AllCtryAvgBlock and AllCtryProgressBlock block
		$this->chartData = new \stdClass();

		// Average Fianl Test Result Block
		$this->generateFile(
			'allCtryAvgBlock_rendered.mustache',
			$this->allCtryAvgBlock()
		);

		// Average Progress Block
		$this->generateFile(
			'allCtryProgressBlock_rendered.mustache',
			$this->allCtryProgressBlock()
		);

		// Write the JSON file
		$this->generateFile(
			'systemoverview_rendered.json',
			json_encode($this->chartData)
		);

		// Average Time Completion Block
		// $this->generateFile(
		// 	'allCtryTimeCompBlock_rendered.mustache',
		// 	$this->allCtryTimeCompBlock()
		// );

		// Progress of the past 12 months
		$this->generateFile(
			'allCtryProgressYearBlock.json',
			$this->allCtryProgressYearBlock()
		);
	}

	public function allCtryProgressYearBlock()
	{
		$notStartedData = $completedData = $months = array();
		for ($i = 0; $i <= 11; $i++) {

			// Get year and month of $i month in the past
			$Ym = date("Y-m", strtotime( date( 'Y-m-01' )." -$i months"));

			// Make a new date object with Ym
			$month = new \DateTime($Ym.'-01');

			// Get the last day of the month
			$lastDay = $month->format('Y-m-t');

			// Get the first day of the month
			$firstDay = $Ym.'-01';

			// Make the x axis labels
			$labels[] = $month->format('M Y');

			// Build the not started array
			$notStartedData[] = $this->DB->count_records_sql("
				SELECT COUNT(mdl_user.id) AS userid
				FROM mdl_user
				LEFT JOIN mdl_quiz_attempts ON mdl_user.id = mdl_quiz_attempts.userid
				WHERE
					timecreated < UNIX_TIMESTAMP('{$lastDay} 23:59:59') AND
					#timecreated > UNIX_TIMESTAMP('{$firstDay} 00:00:00') AND
					suspended = 0 AND deleted = 0 AND quiz IS NULL;",
				array()
			);

			// Build the completed array
			$completedData[] = $this->DB->count_records_sql("
				SELECT count(id) AS num
				FROM mdl_quiz_attempts AS qa
				WHERE
					qa.quiz=12 AND
					qa.state='finished' AND
					qa.timefinish < UNIX_TIMESTAMP('{$lastDay} 23:59:59')
					#AND qa.timefinish > UNIX_TIMESTAMP('{$firstDay} 00:00:00');",
				array()
			);
		}

		// Create the not started data object
		$notStarted = new \stdClass();
		$notStarted->data = array_reverse($notStartedData);
		$notStarted->label = str_replace('&nbsp;', ' ', get_string('AllCtryProgressBlock_notStarted', 'report_iomadanalytics'));
		$notStarted->backgroundColor = $notStarted->borderColor = $this->reportUtils->stackGraphColors['red'];
		$notStarted->fill = false;

		// Create the completed data object
		$completed = new \stdClass();
		$completed->data = array_reverse($completedData);
		$completed->label = get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics');
		$completed->backgroundColor = $completed->borderColor = $this->reportUtils->stackGraphColors['green'];
		$completed->fill = false;

		// Set the graph option - remove the graph's title
		$options = new \stdClass();
		$options->title = (object) array('display' => false);

		$data = new \stdClass();
		$data->labels = array_reverse($labels);
		$data->datasets = array($notStarted, $completed);

		$graph = new \stdClass();
		$graph->type = 'line';
		$graph->data = $data;
		$graph->options = $options;

		return json_encode($graph);

	}

	/**************************************************/
	/********** Average Grade For Fianl Test **********/
	/**************************************************/
	public function allCtryAvgBlock()
	{
		foreach ($this->Countries as $key => $country) {

			// Get the companies in the specified country
			$Companies = $this->reportUtils->getCompaniesInCountry($country->country);

			// Full country name
			$countryName = get_string($country->country, 'countries');

			// Calculate the avg grade
			$avgGrade = $this->reportUtils->getAvgGrade(array_keys($Companies), 12);

			// store country average to render mustache tpl
			$countryFinalTestAvg[] = array(
				'name' => $countryName,
				'grade' => $avgGrade,
				'country' => $country->country
			);

			// Generate the country's JSON object
			$datasets = array(
				(object)[
					'label'=>$countryName,
					'backgroundColor'=>$this->reportUtils->stackGraphColors['green'],
					'data'=>array($avgGrade)
				],
				(object)[
					'label'=>$countryName,
					'backgroundColor'=>$this->reportUtils->stackGraphColors['grey'],
					'data'=>array(100 - $avgGrade)
				]
			);
			$this->addChartData($country->country, 'AllCtryAvgBlock', $datasets);
		}

		// avg of all country
		$allCtry = 0;
		foreach ($countryFinalTestAvg as $value) {
			$allCtry += $value['grade'];
		}
		$allCtryAvg = $allCtry / count($countryFinalTestAvg);

		// All country final test avg bock data
		$allCtryBlockData = array();
		$allCtryBlockData['keyMetric'] = $allCtryAvg;
		$allCtryBlockData['countries'] = $countryFinalTestAvg;

		// set AllCtryAvgBlock in template
		$allCtryBlockTlpData = new \stdClass();
		$allCtryBlockTlpData->name = 'AllCtryAvgBlock';
		$allCtryBlockTlpData->data = $allCtryBlockData;
		$this->report->setTplBlock($allCtryBlockTlpData);

		return $this->output->render_allCtryAvgBlock($this->report);
	}

	/**************************************************/
	/********* All Countries Course Progress **********/
	/**************************************************/
	public function allCtryProgressBlock()
	{
		// Get stats for each country
		foreach ($this->Countries as $key => $country) {
			$notStarted = $this->reportUtils->getNotStarted($country->country);
			$started = $this->reportUtils->getStarted($country->country);
			$completed = $this->reportUtils->getCompleted($country->country);
			$all = $notStarted+$started+$completed;
			$allCtryProgressData[] = array(
				'name' => get_string($country->country, 'countries'),
				'countryid'  => $country->country,
				'notStarted' => $notStarted,
				'started'    => $started,
				'completed'  => $completed,
			);

			// Generate the country's JSON object
			$datasets = array(
				(object)[
					'label'=>'not started',
					'backgroundColor'=>$this->reportUtils->stackGraphColors['red'],
					'data'=>array($this->reportUtils->getPercent($notStarted, $all, $type='floor'))
				],
				(object)[
					'label'=>'started',
					'backgroundColor'=>$this->reportUtils->stackGraphColors['yellow'],
					'data'=>array($this->reportUtils->getPercent($started, $all, $type='floor'))
				],
				(object)[
					'label'=>'completed',
					'backgroundColor'=>$this->reportUtils->stackGraphColors['green'],
					'data'=>array($this->reportUtils->getPercent($completed, $all, $type='floor'))
				]
			);
			$this->addChartData($country->country, 'allCtryProgressBlock', $datasets);
		}

		// Process keyMetric
		$notStarted = $started = $completed = 0;
		$numberStudents = $this->reportUtils->getCountStudents();
		foreach ($allCtryProgressData as $data) {
			$notStarted += $data['notStarted'];
			$started += $data['started'];
			$completed += $data['completed'];
		}

		$keyMetric = array(
			'notStarted_label' => get_string('AllCtryProgressBlock_notStarted', 'report_iomadanalytics'),
			'notStarted_metric' => $this->reportUtils->getPercent($notStarted, $numberStudents, $type='floor'),
			'started_label' => get_string('AllCtryProgressBlock_started', 'report_iomadanalytics'),
			'started_metric' => $this->reportUtils->getPercent($started, $numberStudents, $type='floor'),
			'completed_label' => get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics'),
			'completed_metric' => $this->reportUtils->getPercent($completed, $numberStudents, $type='floor')
		);

		// All country final test avg bock data
		$allCtryBlockData = array();
		$allCtryBlockData['keyMetric'] = $keyMetric;
		$allCtryBlockData['countries'] = $allCtryProgressData;

		// set AllCtryProgressBlock in template
		$allCtryProgBlockTlpData = new \stdClass();
		$allCtryProgBlockTlpData->name = 'AllCtryProgressBlock';
		$allCtryProgBlockTlpData->data = $allCtryBlockData;
		$this->report->setTplBlock($allCtryProgBlockTlpData);

		return $this->output->render_allCtryProgressBlock($this->report);
	}

	/*
	 * Add a block of data into a country
	 * str $country The 2 letters country code, uppercased
	 * str Block The template block name
	 * array $data The PHP Chart structure to be converted to JSON and used by Chart.js
	**/
	public function addChartData($country, $block, $data) {

		// Make sure we have a country, a block and $data is an array
		if (empty($country) || empty($block) || !is_array($data)) {
			return false;
		}

		// Make sure the country code is uppercase
		$country = strtoupper($country);

		// Create the country block if it doesn't exists
		if (!isset($this->chartData->{$country})) {
			$this->chartData->{$country} = new \stdClass();
		}

		// Create the template block if it doesn't exists
		if (!isset($this->chartData->{$country}->{$block})) {
			$this->chartData->{$country}->{$block} = new \stdClass();
		}

		// Add the chart data to the datasets property
		$this->chartData->{$country}->{$block}->datasets = $data;
	}

	/**************************************************/
	/********* All Countries Time Completion **********/
	/**************************************************/
	public function allCtryTimeCompBlock()
	{
		// $allTime course completion time in second of all student
		// $allStudent all student who completed the course in the required range
		$allTime = $allStudents = 0;

		foreach ($this->Countries as $key => $country) {
			$b = $selStudent = 0;
			$Students = $this->reportUtils->getAllStudentsCompleted($country->country);
			$numberStudents = count($Students);
			foreach ($Students as $student) {
				$as = $this->reportUtils->getTotalCompletionTime($student->id);
				foreach ($as as $a) {
					if ($a->sumtime > 1200 AND $a->sumtime < 10600) {
						$b += $a->sumtime;
						$selStudent++;
					}
				}
			}

			// Compile average per country
			$allCtryTimeData[] = array(
				'name' => get_string($country->country, 'countries'),
				'timeSpent' => $this->reportUtils->getTimeFromSec(($b/$selStudent))
			);

			// Get the data for the global (all country average)
			$allTime += $b;
			$allStudents += $selStudent;
		}

		// All country final test avg bock data
		$allCtryTimeBlockData = array();
		$allCtryTimeBlockData['keyMetric'] = $this->reportUtils->getTimeFromSec(($allTime/$allStudents));
		$allCtryTimeBlockData['countries'] = $allCtryTimeData;

		// set AllCtryProgressBlock in template
		$allCtryTimeCompBlockTlpData = new \stdClass();
		$allCtryTimeCompBlockTlpData->name = 'AllCtryTimeCompBlock';
		$allCtryTimeCompBlockTlpData->data = $allCtryTimeBlockData;
		$this->report->setTplBlock($allCtryTimeCompBlockTlpData);

		return $this->output->render_allCtryTimeCompBlock($this->report);
	}

	public function generateFile($file, $content) {
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/iomadanalytics/templates/{$file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $content);
		fclose($myfile);
	}
}