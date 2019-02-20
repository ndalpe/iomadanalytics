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

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class
 *
 * @package   report_iomadanalytics
 * @copyright 2017 PT. Bridgeus Kizuna Asia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_iomadanalytics_utils {

    // Cache recordset
    public $Users = false;
    public $Attemps;
    public $quiz;

    public $coursesId = array(7,9,10,11,12,13,14,15,16,17,18);

    public $quizsId   = array(2,3,4,5,6,7,8,9,10,11,12);

    public $barGraphColors = array(
        "51,102,204",
        "220,57,18",
        "255,153,0",
        "16,150,24",
        "153,0,153",
        "59,62,172",
        "0,153,198",
        "221,68,119",
        "102,170,0",
        "184,46,46",
        "49,99,149",
        "153,68,15",
        "34,170,153",
        "170,170,17",
        "102,51,204",
        "230,115,0",
        "139,7,7",
        "50,146,98",
        "85,116,166",
        "59,62,172"
    );

    public function __construct(){
        if (!isset($DB)) {
            global $DB;
        }

        $this->DB = $DB;
    }

    /**
     * Return an rgba color string ie: "rgba(51,102,204,0.5)"
     *
     * int $index The color index in barGraphColors array
     * int $alpha The color alpha. Range from 1 to 9
    */
    public function getBarGraphColor($index, $alpha) {
        return 'rgba('.$this->barGraphColors[$index].',0.'.$alpha.')';
    }

    /**
     * Get a list of all countries where there are KP company
     *
     * bool $grouped whether the country list should be grouped by country or not
    */
    public function getCountries($grouped = false) {
        $Countries = $this->DB->get_records_sql(
            'SELECT count(id),country FROM mdl_company WHERE suspended = :suspended AND parentid != :parentid GROUP BY country ORDER BY country ASC;', array('parentid'=>'0','suspended'=>'0'), $limitfrom=0, $limitnum=0
        );
        return $Countries;
    }

    /**
     * Get a list of all countries where there are KP company
     *
     * bool $grouped whether the country list should be grouped by country or not
    */
    public function getCompanies() {
        $Companies = $this->DB->get_records_sql(
            'SELECT * FROM mdl_company WHERE suspended = :suspended AND parentid != :parentid ORDER BY country ASC;', array('parentid'=>'0','suspended'=>'0'), $limitfrom=0, $limitnum=0
        );
        return $Companies;
    }

    /**
     * Get a list of all courses specified in $this->courseId property
     *
    */
    public function getCourses()
    {
        $coursesId = implode(',', $this->coursesId);
        $courses = $this->DB->get_records_sql("SELECT id, fullname FROM mdl_course WHERE id IN(".$coursesId.") ORDER BY sortorder", null, $limitfrom=0, $limitnum=0);
        return $courses;
    }

    /**
     * Get a list of all quiz for a specified course
     *
     * int $courseid the course id to retrieve the quiz from
    */
    public function getQuizByCourse($courseid)
    {
        $quizs = $this->DB->get_records_sql("SELECT id, name FROM mdl_quiz WHERE course = :courseid", array('courseid'=>$courseid), $limitfrom=0, $limitnum=0);
        return $quizs;
    }

    /**
     * Get a list of all companies in a given country
     *
     * string $country The 2 letters country code (ID = Indonesia, MY = Malaysia, etc)
    */
    public function getCompaniesInCountry($country) {
        if (is_string($country)) {
            $Companies = $this->DB->get_records_sql(
                'SELECT * FROM mdl_company WHERE country=:country AND suspended=:suspended AND parentid != :parentid;', array('country'=>$country, 'suspended'=>'0', 'parentid'=>'0'), $limitfrom=0, $limitnum=0
            );
        } else {
            $Companies = '$country should be a 2 letters country code.';
        }
        return $Companies;
    }

    /**
     * Get a list of all students within a company
     *
     * string $company_id The company id
    */
    public function getStudentsInCompany($company_id)
    {
        // test if company exists
        if ($this->DB->record_exists('company', array('id'=>$company_id))) {
            $Students = $this->DB->get_records_sql(
                "SELECT userid as id
                FROM mdl_company_users as cu
                INNER JOIN mdl_user as u ON cu.userid = u.id
                WHERE companyid=:company_id AND cu.suspended=0 AND u.suspended=0 AND u.suspended=0 AND u.deleted=0",
                array('company_id' => $company_id)
            );
        } else {
            $Students = false;
        }

        return $Students;
    }

    /**
     * Get the average grade in % for the specified companies
     *
     * String|Array $company_ids The company ids to use
     * int $quiz_id The quiz id to use
    */
    public function getAvgGrade($company_ids, $quiz_id=12) {

        // if $company_ids is an array, convert it to a comma sep list of company id
        if (is_array($company_ids)) {
            $sql_company_ids = implode(',', $company_ids);
        } else {
            $sql_company_ids = $company_ids;
        }

        $avgGrades = $this->DB->get_records_sql(
            'SELECT AVG(qg.grade) as avgGrade
            FROM mdl_company_users as cu
            INNER JOIN mdl_quiz_grades as qg ON cu.userid = qg.userid
            INNER JOIN mdl_user as u ON cu.userid = u.id
            WHERE cu.companyid IN ('.$sql_company_ids.') AND cu.suspended=0 AND qg.quiz=:quizid AND u.suspended=0 AND u.deleted=0;',
            array('quizid'=>$quiz_id), $limitfrom=0, $limitnum=0
        );

        // Weirdly Moodle return the value of the first element in the select as array key
        // so we need to grabe the query result with this. I dont understand why Moodle is
        // doing it this way
        $avg = key($avgGrades);

        // get the quiz max grade value
        $numQuestion = $this->DB->get_record("quiz", array('id'=>$quiz_id), $fields='grade');

        // get grade percentage
        $percent = $this->getPercent($avg, $numQuestion->grade);

        return $percent;
    }


    /**
     * Get the number of student who have not answered a quiz yet in a specific country
     *
     * string $country The country abbr (ie.: ID or MY or ...)
    */
    public function getNotStarted($country) {
        return $this->DB->count_records_sql("
            SELECT COUNT(mdl_user.id) AS userid
            FROM mdl_user
            LEFT JOIN mdl_quiz_attempts ON mdl_user.id = mdl_quiz_attempts.userid
            WHERE country=:country AND suspended = 0 AND deleted = 0 AND mdl_quiz_attempts.quiz IS NULL;",
            array('country'=>$country)
        );
    }

    /**
     * Get the number of student who have not answered a quiz yet in a specific company/companies
     *
     * array $companies_id The array of company id
    */
    public function getNotStartedCompany($companies_id) {

        // stringify the companies id
        $companyid = implode(',', $companies_id);

        return $this->DB->count_records_sql("
            SELECT COUNT(cu.userid) AS userid
            FROM mdl_company_users AS cu
                LEFT JOIN mdl_quiz_attempts AS qa ON cu.userid = qa.userid
                INNER JOIN mdl_user AS u ON cu.userid = u.id
            WHERE companyid IN({$companyid}) AND u.suspended = 0 AND u.deleted = 0 AND qa.quiz IS NULL;",
            array()
        );
    }

    /**
     * Get the number of student who started the course but not finish the final test in a specific country
     *
     * string $country The country abbr (ie.: ID or MY or ...)
    */
    public function getStarted($country) {
        $c = 0;
        $Users = $this->DB->get_recordset_sql("
            SELECT
                mdl_user.id AS userid,
                MAX(mdl_quiz_attempts.quiz) AS quizid,
                (SELECT state FROM mdl_quiz_attempts WHERE mdl_quiz_attempts.userid = mdl_user.id AND quiz = 12) AS state
            FROM mdl_user
            INNER JOIN mdl_quiz_attempts ON mdl_user.id = mdl_quiz_attempts.userid
            WHERE country=:country AND suspended = 0 AND deleted = 0
            GROUP BY mdl_user.id;",
            array('country'=>$country)
        );
        foreach ($Users as $user) {
            // student didn't get to final test
            if ((int)$user->quizid < 12) {
                $c += 1;
            }

            // student got to final test but didn't finished it
            if ((int)$user->quizid == 12 && $user->state == 'inprogress') {
                $c += 1;
            }
        }
        $Users->close();
        return $c;
    }

    /**
     * Get the number of student who started the course but not finish the final test in a specific company
     *
     * array $companies_id The array of company id
    */
    public function getStartedComapany($companies_id) {
        // number of student who started the course
        $c = 0;

        // stringify the companies id
        $companyid = implode(',', $companies_id);

        $Users = $this->DB->get_recordset_sql("
            SELECT
                cu.userid AS userid,
                MAX(qa.quiz) AS quizid,
                (SELECT state FROM mdl_quiz_attempts WHERE mdl_quiz_attempts.userid = cu.userid AND quiz = 12) AS state
            FROM mdl_company_users AS cu
            INNER JOIN mdl_user AS u ON cu.userid = u.id
            INNER JOIN mdl_quiz_attempts AS qa ON cu.userid = qa.userid
            WHERE companyid IN ({$companyid}) AND cu.suspended=0 AND u.suspended=0 AND u.deleted=0
            GROUP BY cu.userid;",
            array()
        );

        foreach ($Users as $user) {
            // student didn't get to final test
            if ((int)$user->quizid < 12) {
                $c += 1;
            }

            // student got to final test but didn't finished it
            if ((int)$user->quizid == 12 && $user->state == 'inprogress') {
                $c += 1;
            }
        }

        $Users->close();
        return $c;
    }

    /**
     * Get the number of student who started the course but not finish the final test in a specific company
     *
     * array $companies_id The array of company id
    */
    public function getStartedFiltered($companies_id, $fieldid, $fieldValue) {
        // stringify the companies ids
        $companyid = implode(',', $companies_id);

        if (is_array($fieldValue)) {
            if ($fieldValue['type'] == 'MinMax') {
                $whereData = "AND (uid.data>'".$fieldValue['max']."' AND uid.data<'".$fieldValue['min']."')";
            }
        } else {
            $whereData = "AND uid.data='{$fieldValue}'";
        }

        $Users = $this->DB->get_recordset_sql("
            SELECT
                cu.userid AS userid,
                MAX(qa.quiz) AS quizid,
                (SELECT state FROM mdl_quiz_attempts WHERE mdl_quiz_attempts.userid = cu.userid AND quiz = 12) AS state,
                uid.fieldid AS field,
                uid.data AS data
            FROM mdl_company_users AS cu
            INNER JOIN mdl_user AS u ON cu.userid = u.id
            INNER JOIN mdl_quiz_attempts AS qa ON cu.userid = qa.userid
            INNER JOIN mdl_user_info_data AS uid ON cu.userid = uid.userid
            WHERE cu.companyid IN ({$companyid})
                AND cu.suspended=0 AND u.suspended=0 AND u.deleted=0
                AND uid.fieldid=:fieldid
                {$whereData}
            GROUP BY cu.userid;",
            array(
                'fieldid'=>$fieldid
            )
        );

        // number of student who started the course
        $c = 0;

        foreach ($Users as $user) {
            // student didn't get to final test
            if ((int)$user->quizid < 12) {
                $c += 1;
            }

            // student got to final test but didn't finished it
            if ((int)$user->quizid == 12 && $user->state == 'inprogress') {
                $c += 1;
            }
        }

        $Users->close();
        return $c;
    }

    /**
     * Get the number of student who completed the final test in a specific country
     *
     * string $company_id The country abbr (ie.: ID or MY or ...)
    */
    public function getCompleted($country) {
        $Users = $this->DB->count_records_sql("
            SELECT count(u.id) AS total FROM mdl_user AS u
            INNER JOIN mdl_quiz_attempts AS a ON u.id = a.userid
            WHERE u.country=:country AND a.quiz=12 AND a.state='finished' AND u.suspended=0 AND u.deleted=0;",
            array('country'=>$country), $limitfrom=0, $limitnum=0
        );
        return $Users;
    }

    /**
     * Get the number of student who completed the final test in a specific company
     *
     * array $companies_id The array of company id
    */
    public function getCompletedCompany($companies_id) {
        $companyid = implode(',', $companies_id);
        $Users = $this->DB->count_records_sql("
            SELECT count(u.id) AS total
            FROM mdl_company_users AS cu
            INNER JOIN mdl_quiz_attempts AS a ON cu.userid = a.userid
            INNER JOIN mdl_user AS u ON cu.userid = u.id
            WHERE cu.companyid IN({$companyid}) AND a.quiz=12 AND a.state='finished' AND u.suspended=0 AND u.deleted=0;",
            array('companyid'=>$companyid)
        );
        return $Users;
    }

    /**
     * Get the number of student who completed the final test in a specific company
     *
     * array $companies_id The array of company id
    */
    public function getCompletedFiltered($companies_id, $fieldid, $fieldValue)
    {
        $companyid = implode(',', $companies_id);

        if (is_array($fieldValue)) {
            if ($fieldValue['type'] == 'MinMax') {
                $whereData = "AND (uid.data>'".$fieldValue['max']."' AND uid.data<'".$fieldValue['min']."')";
            }
        } else {
            $whereData = "AND uid.data='{$fieldValue}'";
        }

        $Users = $this->DB->count_records_sql("
            SELECT count(u.id) AS total
            FROM mdl_company_users AS cu
            INNER JOIN mdl_quiz_attempts AS a ON cu.userid = a.userid
            INNER JOIN mdl_user AS u ON cu.userid = u.id
            INNER JOIN mdl_user_info_data as uid ON cu.userid = uid.userid
            WHERE
                cu.companyid IN({$companyid}) AND
                a.quiz=12 AND a.state='finished' AND
                u.suspended=0 AND u.deleted=0 AND
                uid.fieldid=:fieldid
                {$whereData};",
            array('companyid'=>$companyid, 'fieldid'=>$fieldid)
        );
        return $Users;
    }

    public function getTotalCompletionTime($userid)
    {
        $strCroursesId = implode(',', $this->coursesId);
        $CompTime = $this->DB->get_records_sql("
            SELECT
                l.id,
                l.userid as userid,
                DATE_FORMAT(FROM_UNIXTIME(l.timecreated),'%Y-%m-%d') AS dTime,
                @prevtime := (SELECT MAX(timecreated) FROM mdl_logstore_standard_log WHERE userid = $userid AND id < l.id ORDER BY id ASC LIMIT 1) AS prev_time,
                IF (l.timecreated - @prevtime < 600, @delta := @delta + (l.timecreated-@prevtime),0) AS sumtime,
                l.timecreated-@prevtime AS delta
            FROM mdl_logstore_standard_log AS l, (SELECT @delta := 0) AS s_init
            WHERE l.userid = {$userid} AND l.courseid IN ({$strCroursesId}) AND  component != 'core' AND component != 'gradereport_overview' AND component != 'report_completion' AND component != 'gradereport_grader' AND component != 'mod_forum'
            ORDER BY sumtime DESC
            LIMIT 1;", array(), $limitfrom=0, $limitnum=0
        );
        return $CompTime;
    }

    /**
     * Return the count of all user in the database who are not deleted or suspended
     *
    */
    public function getCountStudents() {
        return $this->DB->count_records('user', array('deleted'=>0, 'suspended'=>0));
    }

    /**
     * Return user id of students who completed the Final test
     *
     * string $country The 2 letter country code as found in mdl_user.country
     *
    */
    public function getAllStudentsCompleted($country) {
        $Students = $this->DB->get_records_sql("
            SELECT u.id
            FROM mdl_user as u
            INNER JOIN mdl_quiz_attempts as a ON u.id = a.userid
            WHERE u.country=:country AND a.quiz = 12 AND a.state='finished' AND u.suspended=0 AND u.deleted=0;",
            array('country'=>$country)
        );
        return $Students;
    }

    public function getAvgPercentGrades($Grades)
    {
        $gradeCount = $i = 0;

        foreach ($Grades->items[0]->grades as $key => $grade) {

            if (!is_null($grade->grade)) {

                $gradeNumber = (int)$grade->grade;

                if ($gradeNumber == 0) {
                    $pGrade = 0;
                } else {
                    $pGrade = ($grade->grade / $Grades->items[0]->grademax) * 100;
                }

                $gradeCount += $pGrade;

                $i++;
            }
        }

        if ($i > 0) {
            $avg = round(($gradeCount / $i));
        } else {
            $avg = 0;
        }

        return $avg;
    }

    public function getTimeFromSec($sec) {
        if ($sec > 86400) {
            return 0;
        }

        if ($sec < 3600) {
            $time = gmdate("i:s", $sec);
        } else {
            $time = gmdate("H:i:s", $sec);
        }

        return $time;
    }

    /**
     * Return a % from given number
     *
     * int|float $number The number to be divided
     * int $divider The number to divide $number with
     * bool $precision False to round() the % or int to specifiy the precision
     *
    */
    public function getPercent($number, $divider, $precision=false) {

        // make sure $number is numeric
        // and convert it into a int or a float
        if (is_numeric($number)) {
            $number += 0;
        } else if ($number === 0) {
            return $number;
        }

        // make sure $divider is numeric
        // and convert it into a int or a float
        if (is_numeric($divider)) {
            $divider += 0;
        } else if ($divider === 0) {
            return $divider;
        }

        // Make the % happen
        $percent = ($number/$divider) * 100;

        // round() the number according to $precision
        if (!$precision) {
            $percent = round($percent);
        } else {
            $percent = round($percent, $precision);
        }

        return $percent;
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
}


/*
$this->DB->set_debug(true);
$this->DB->set_debug(false);
*/