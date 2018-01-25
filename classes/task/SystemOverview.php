<?php
namespace report_iomadanalytics\task;

require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->dirroot . '/report/iomadanalytics/locallib.php');

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

		$this->output = $PAGE->get_renderer('report_iomadanalytics');

		$this->report = new \report_iomadanalytics();

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

		// Average Time Completion Block
		$this->generateFile(
			'allCtryTimeCompBlock_rendered.mustache',
			$this->allCtryTimeCompBlock()
		);
	}

	/**************************************************/
	/********** Average Grade For Fianl Test **********/
	/**************************************************/
	public function allCtryAvgBlock()
	{
		foreach ($this->Countries as $key => $country) {
			$Companies = $this->reportUtils->getCompaniesInCountry($country->country);

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
				'grade' => $this->reportUtils->getAvgGrade($comp_id, 12),
				'country' => $country->country
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
		foreach ($this->Countries as $key => $country) {
			$notStarted = $this->reportUtils->getNotStarted($country->country);
			$started = $this->reportUtils->getStarted($country->country);
			$completed = $this->reportUtils->getCompleted($country->country);
			$all = $notStarted+$started+$completed;
			$allCtryProgressData[] = array(
				'name' => get_string($country->country, 'countries'),
				'notStarted' => $notStarted,
				'notStarted_percent' => $this->reportUtils->getPercent($notStarted, $all, $precision=false),
				'started'    => $started,
				'started_percent'    => $this->reportUtils->getPercent($started, $all, $precision=false),
				'completed'  => $completed,
				'completed_percent'  => $this->reportUtils->getPercent($completed, $all, $precision=false)
			);
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
			'notStarted_metric' => $this->reportUtils->getPercent($notStarted, $numberStudents, $precision=false),
			'started_label' => get_string('AllCtryProgressBlock_started', 'report_iomadanalytics'),
			'started_metric' => $this->reportUtils->getPercent($started, $numberStudents, $precision=false),
			'completed_label' => get_string('AllCtryProgressBlock_completed', 'report_iomadanalytics'),
			'completed_metric' => $this->reportUtils->getPercent($completed, $numberStudents, $precision=false)
		);

		// All country final test avg bock data
		$allCtryBlockData = array();
		$allCtryBlockData['header'] = get_string('AllCtryProgressBlock_title', 'report_iomadanalytics');
		$allCtryBlockData['keyMetric'] = $keyMetric;
		$allCtryBlockData['countries'] = $allCtryProgressData;

		// set AllCtryProgressBlock in template
		$allCtryProgBlockTlpData = new \stdClass();
		$allCtryProgBlockTlpData->name = 'AllCtryProgressBlock';
		$allCtryProgBlockTlpData->data = $allCtryBlockData;
		$this->report->setTplBlock($allCtryProgBlockTlpData);

		return $this->output->render_allCtryProgressBlock($this->report);
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
		$allCtryTimeBlockData['header'] = get_string('AllCtryTimeCompBlock_title', 'report_iomadanalytics');
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