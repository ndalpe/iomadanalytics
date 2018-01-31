<?php
namespace report_iomadanalytics\task;

require_once($CFG->dirroot . '/report/iomadanalytics/locallib.php');

class FilterTabs extends \core\task\scheduled_task
{
	// Contains all custom profile fields
	public $Fields;

	// Custom Profile Field to exclude from filters
	// 3  : nationality
	// 11 : company : different country has different companies
	public $FieldToExclude = '3,6,11';

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

		$this->Fields = $this->DB->get_records_sql(
			'SELECT id, shortname, name, datatype, param1 FROM mdl_user_info_field WHERE id NOT IN('.$this->FieldToExclude.') ORDER BY sortorder ASC;', array(), $limitfrom=0, $limitnum=0
		);

		$this->output = $PAGE->get_renderer('report_iomadanalytics');

		$this->report = new \report_iomadanalytics();

		$tabList = $this->renderNavTabList();
		$tabCont = $this->renderTabContent();

		$this->generateFile(
			'filters_tabs_rendered.mustache',
			$tabList."\n\n".$tabCont
		);
	}

	/**
	 * Render the necessary HTML to display the tab list
	 *
	*/
	public function renderNavTabList()
	{
		$activeFlag = true;
		foreach ($this->Fields as $tab) {
			if (!empty($tab->name)) {

				// add the active css class to the first .tab-pan
				if ($activeFlag) {
					$active = ' active';
					$activeFlag = false;
				}

				$tabsList[] = array(
					'id' => $tab->id,
					'shortname' => $tab->shortname,
					'name' => $this->parseBiName($tab->name),
					'active' => $active
				);

				$active = '';
			}
		}

		$tabsListBlock = new \stdClass();
		$tabsListBlock->name = 'tabslist';
		$tabsListBlock->data = $tabsList;
		$reportTab = new \report_iomadanalytics();
		$reportTab->setTplBlock($tabsListBlock);
		$tabsBlockRendered = $this->output->render_tabsList($reportTab);

		return $tabsBlockRendered;
	}

	/**
	 * Render the necessary HTML to display the tab content
	 *
	*/
	public function renderTabContent()
	{
		$activeFlag = true;
		foreach ($this->Fields as $tab) {
			if (!empty($tab->name) && !empty($tab->shortname)) {

				$tabFuncName = 'tabContent'.ucfirst($tab->shortname);

				if (method_exists($this, $tabFuncName)) {
					$options = $this->$tabFuncName($tab);
				} else {
					$options = $this->tabContentGeneric($tab);
				}

				foreach ($options as $option) {
					$checkboxList[] = array(
						'fieldid' => $tab->id,
						'value'   => $option['value'],
						'label'   => $option['label'],
					);
				}

				// add the active css class to the first .tab-pan
				if ($activeFlag) {
					$active = ' active';
					$activeFlag = false;
				}

				$chkboxListBlock = new \stdClass();
				$chkboxListBlock->name = 'filterslist';
				$chkboxListBlock->data = $checkboxList;
				$reportTab = new \report_iomadanalytics();
				$reportTab->setTplBlock($chkboxListBlock);
				$checkboxBlockRendered[] = array(
					'shortname' => $tab->shortname,
					'tabContent' => $this->output->render_checkboxList($reportTab),
					'active'  => $active
				);

				// reset the active css class bootstrap needs to show the .tab-pane
				$active = '';

				// reset field's checkbox
				unset($checkboxList);
			}
		}

		$tabPanListBlock = new \stdClass();
		$tabPanListBlock->name = 'indTablist';
		$tabPanListBlock->data = $checkboxBlockRendered;
		$reportTab = new \report_iomadanalytics();
		$reportTab->setTplBlock($tabPanListBlock);
		$tabsBlockRendered = $this->output->render_tabPanList($reportTab);

		return $tabsBlockRendered;
	}

	/**
	 * Return an array to available age group found in mdl_user_info_data
	 *
	 * @param object $tab The current custom profile field data
	 * @return array
	 *
	*/
	public function tabContentGeneric($tab)
	{
		if (!empty($tab->param1)) {
			$filterValues = explode("\n", $tab->param1);
			if (is_array($filterValues)) {
				foreach ($filterValues as $key => $value) {
					$exists = $this->DB->record_exists_sql("SELECT * FROM mdl_user_info_data WHERE data='$value' AND fieldid=".$tab->id);
					if ($exists) {
						$options[] = array(
							'value' => $value,
							'label' => $value
						);
					}
				}
			}
		}
		return $options;
	}

	public function tabContentJoindate($tab)
	{
		return array();
	}

	/**
	 * Return an array containing available age group found in mdl_user_info_data
	 *
	 * @param object $tab The current custom profile field data
	 * @return array
	 *
	*/
	public function tabContentDob($tab)
	{
		// $aSql = "
		// SELECT a.timefinish, u.data as dob, DATE_FORMAT(FROM_UNIXTIME(a.timefinish), '%Y') - DATE_FORMAT(FROM_UNIXTIME(u.data), '%Y') - (DATE_FORMAT(FROM_UNIXTIME(a.timefinish), '00-%m-%d') < DATE_FORMAT(FROM_UNIXTIME(u.data), '00-%m-%d')) AS age
		// FROM moodle.mdl_quiz_attempts AS a
		// INNER JOIN moodle.mdl_user_info_data AS u ON a.userid = u.userid
		// WHERE a.quiz = 12 AND a.state = 'finished' AND u.fieldid = 1
		// ORDER BY age ASC;";
		// $Age = $this->DB->get_records_sql($aSql, null, $limitfrom=0, $limitnum=0);
		$Age = $this->DB->get_records_sql('
			SELECT id, userid, fieldid, data AS dob, TIMESTAMPDIFF(YEAR, FROM_UNIXTIME(data), CURDATE()) AS age
			FROM mdl_user_info_data
			WHERE fieldid =1;
		', null, $limitfrom=0, $limitnum=0);

		// cleanup the null and the 0s
		// TODO : optimize the SQL query to exclude the NULL and Zeros from result set
		foreach ($Age as $key => $value) {
			if ($value->age == 'NULL' || $value->age < '15') {
				unset($Age[$key]);
			}
		}

		// num of student who received a grade for final test
		// $ageCount = count($Age);

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

		// Build the checkbox options value
		foreach ($ageGroups as $group) {
			if ($group['ageCnt'] > 0) {
				$options[] = array(
					'value' => $group['tsMin'].'-'.$group['tsMax'],
					'label' => $group['ageMin'].' - '.$group['ageMax']
				);
			}
		}

		return $options;
	}

	/**
	 * Retrieve the english part of a multi-lang string
	 * ie: <span class="multilang" lang="en">Join Date</span><span class="multilang" lang="id">bergabung</span>
	 *
	 * @param String $xmlstr The mlang XML string
	 * @return String English term
	 */
	public function parseBiName($xmlstr)
	{
		// if the string doesn't contain the multilang syntaxe, just return is as is
		if (strpos($xmlstr, '<span') === false) {
			return $xmlstr;
		}

		if (!empty($xmlstr)) {
			$this->domDoc = new \domDocument('1.0', 'utf-8');
			$this->domDoc->preserveWhiteSpace = false;
			$this->domDoc->loadHTML($xmlstr);
			$span = $this->domDoc->getElementsByTagName('span');
			$str = $span->item(0)->nodeValue;
		} else {
			$str = '';
		}

		// Garbage
		$span = '';

		return $str;
	}

	public function generateFile($file, $content) {
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/iomadanalytics/templates/{$file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $content);
		fclose($myfile);
	}
}