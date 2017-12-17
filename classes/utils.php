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
    public $Users;
    public $Attemps;
    public $quiz;

    public function __construct(){
        if (!isset($DB)) {
            global $DB;
        }

        $this->DB = $DB;
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
     * Get a list of all companies in a given country
     *
     * string $country The 2 letters country code (ID = Indonesia, MY = Malaysia, etc)
    */
    public function getCompaniesInCountry($country) {
        if (is_string($country)) {
            $Companies = $this->DB->get_records_sql(
                'SELECT id,country FROM mdl_company WHERE country=:country AND suspended=:suspended AND parentid != :parentid;', array('country'=>$country, 'suspended'=>'0', 'parentid'=>'0'), $limitfrom=0, $limitnum=0
            );
        } else {
            $Companies = '$country should be a 2 letters country code.';
        }
        return $Companies;
    }

    public function getAvgGrade($company_ids, $quiz_id=12) {
        $avgGrades = $this->DB->get_records_sql(
            'SELECT AVG(qg.grade) as avgGrade
            FROM mdl_company_users as cu
            INNER JOIN mdl_quiz_grades as qg ON cu.userid = qg.userid
            INNER JOIN mdl_user as u ON cu.userid = u.id
            WHERE cu.companyid IN ('.$company_ids.') AND cu.suspended=0 AND qg.quiz=:quizid AND u.suspended=0 AND u.deleted=0
            GROUP BY cu.companyid;', array('quizid'=>$quiz_id), $limitfrom=0, $limitnum=0
        );
        $numGrades = count($avgGrades);
        $avgs = 0;
        foreach ($avgGrades as $value) {
            $avgs += round($value->avggrade);
        }
        $a = $avgs / $numGrades;

        // get number of question in quiz
        $numQuestion = $this->DB->count_records('quiz_slots', array('quizid' => $quiz_id));

        // get grade percentage
        $percent = round(($a/$numQuestion)*100);
        return $percent;
    }

    public function getNotStarted($country) {
        $c = 0;
        $Attemps = $this->getAttemps();
        $Users = $this->getUsersFromCountry($country);
        foreach ($Users as $user) {
            if (array_search($user->id, array_column($Attemps, 'userid')) === false) {
                $c = $c + 1;
            }
        }
        return $c;
    }

    public function getStarted($country) {
        $c = 0;
        $Users = $this->DB->get_recordset('user', array('country'=>$country,'suspended'=>'0', 'deleted'=>'0'), $sort='', $fields='*', $limitfrom=0, $limitnum=0);
        foreach ($Users as $user) {
            $Attemps = $this->DB->get_records_sql(
                'SELECT id,quiz FROM mdl_quiz_attempts WHERE userid=:userid;',
                array('userid'=>$user->id)
            );
            if (count($Attemps) !== 0) {
                $started = true;
                foreach ($Attemps as $attempt) {
                    // reject the record if the user has an attemp for final test (quiz id 12)
                    if ($attempt->quiz == 12) {
                        $started = false;
                    }
                }
                if ($started) {
                    $c = $c + 1;
                }
            }
        }
        $Users->close();
        return $c;
    }

    public function getCompleted($country) {
        $Users = $this->DB->count_records_sql(
            'SELECT count(u.id) AS total FROM mdl_user AS u
            INNER JOIN mdl_quiz_attempts AS a ON u.id = a.userid
            WHERE u.country=:country AND a.quiz=12 AND u.suspended=0 AND u.deleted=0;',
            array('country'=>$country), $limitfrom=0, $limitnum=0
        );
        return $Users;
    }

    public function getUsersFromCountry($country) {
        $Users = $this->DB->get_records_sql(
            'SELECT * FROM mdl_user WHERE country=:country AND suspended=0 AND deleted=0;',
            array('country'=>$country), $limitfrom=0, $limitnum=0
        );
        return $Users;
    }

    public function getAttemps() {
        $Quizs = $this->getQuiz();
        foreach ($Quizs as $key => $quiz) {
            $q[] = $quiz->id;
        }
        $whereQuiz = implode(',', $q);
        unset($Quizs, $q);

        $Attemps = $this->DB->get_records_sql(
            'SELECT id,quiz,userid FROM mdl_quiz_attempts WHERE quiz IN ('.$whereQuiz.');', array(), $limitfrom=0, $limitnum=0
        );
        return $Attemps;
    }

    public function getQuiz() {
        $Quiz = $this->DB->get_records_sql(
            'SELECT * FROM mdl_quiz WHERE id IN (2,3,4,5,6,7,8,9,10,11,12);',
            array(), $limitfrom=0, $limitnum=0
        );
        return $Quiz;
    }

    public function getQuizCompletionTime($courseid, $quizid, $students){
        //$grading_info = grade_get_grades($courseid, 'mod', 'quiz', $quizid, array_keys($students));

            //FROM_UNIXTIME(timestart, '%Y-%m-%d %H:%i:%s') as ts,
            //FROM_UNIXTIME(timefinish, '%Y-%m-%d %H:%i:%s') as tf,
        $grading_info = $this->DB->get_records_sql(
            "SELECT id, quiz, userid, MAX(attempt) as m, sumgrades,
            SEC_TO_TIME(TIMESTAMPDIFF(second, FROM_UNIXTIME(timestart, '%Y-%m-%d %H:%i:%s'),FROM_UNIXTIME(timefinish, '%Y-%m-%d %H:%i:%s'))) as td
            FROM (SELECT * FROM mdl_quiz_attempts order by userid ASC, attempt DESC) AS t
            where quiz = {$quizid} AND state='finished'
            group by userid
            order by userid;",
            array(), $limitfrom=0, $limitnum=0
        );

        return $grading_info;
    }

    public function getPercent($number, $divider, $precision=false) {
        $number = intval($number);
        if ($number === 0) {
            $number = 1;
        }

        $divider = intval($divider);
        if ($divider === 0) {
            $divider = 1;
        }

        $p = ($number/$divider) * 100;
        if (!$precision) {
            return round($p);
        } else {
            return round($p, $precision);
        }
    }
}


/*
$this->DB->set_debug(true);
$this->DB->set_debug(false);
*/