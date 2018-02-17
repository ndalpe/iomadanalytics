<?php
require_once("$CFG->libdir/externallib.php");

require_once($CFG->dirroot . '/report/iomadanalytics/locallib.php');
require_once($CFG->dirroot . '/report/iomadanalytics/classes/GradesFilters.php');
require_once($CFG->dirroot . '/report/iomadanalytics/classes/ProgressFilters.php');
require_once($CFG->dirroot . '/report/iomadanalytics/classes/FlatFile.php');

class report_iomadanalytics_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function filter_grades_parameters() {
        return new external_function_parameters(
                array('filters' => new external_value(PARAM_TEXT, 'The list of filters and companies', VALUE_DEFAULT, ''))
        );
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function filter_grades($filters = 'Hello world, ') {
        global $USER;

        //Parameter validation
        $params = self::validate_parameters(self::filter_grades_parameters(), array('filters'=>$filters));

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

        //Capability checking
        //OPTIONAL but in most web service it should be present
        // if (!has_capability('moodle/user:viewdetails', $context)) {
        //     throw new moodle_exception('cannotviewprofile');
        // }

        $param = json_decode($filters);

        // Contains the grades and profress data
        $graphs = new stdClass();

        // Get grades data
        $g = new GradesFilters();
        $g->setFilter($param->filters[0]);
        $g->setCompanies($param->companies);
        $graphs->grades = $g->getFiltersData();

        // Get progress data
        $p = new ProgressFilters();
        $p->setFilter($param->filters[0]);
        $p->setCompanies($param->companies);
        $graphs->progress = $p->getFiltersData();

        return json_encode($graphs);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function filter_grades_returns() {
        return new external_value(PARAM_TEXT, 'The filtered grades');
    }
}


// ob_start();
// var_dump(json_encode($return));
// $content = ob_get_contents();
// ob_end_clean();
// $f = new FlatFile();
// $f->setFileName('ajax.txt');
// $f->setFileContent($content);
// $f->writeToFile();exit();

// $f = new FlatFile();
// $f->setFileName('ajax.txt');
// $f->setObContent($datasets);
// $f->writeToFile();exit();