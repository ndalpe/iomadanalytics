<?php
require_once("$CFG->libdir/externallib.php");

class report_iomadanalytics_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function filter_grades_parameters() {
        return new external_function_parameters(
                array('filters' => new external_value(PARAM_TEXT, 'The list of filters', VALUE_DEFAULT, ''))
        );
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function filter_grades($filters = 'Hello world, ') {
        global $USER;

        //Parameter validation
        $params = self::validate_parameters(
            self::filter_grades_parameters(),
            array('filters' => $filters)
        );

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

        //Capability checking
        //OPTIONAL but in most web service it should present
        // if (!has_capability('moodle/user:viewdetails', $context)) {
        //     throw new moodle_exception('cannotviewprofile');
        // }

        return 'hello from WS';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function filter_grades_returns() {
        return new external_value(PARAM_TEXT, 'The filtered grades');
    }
}