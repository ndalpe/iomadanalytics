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

/**
 * Renderer for report.
 *
 * @package    report_iomadanalytics
 * @copyright  2017 Bridgeus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// namespace report_iomadanalytics\output;

defined('MOODLE_INTERNAL') || die();


class report_iomadanalytics implements renderable, templatable {

    public function __construct() {}

    public function setTplBlock($tplBlock) {
        $this->data[$tplBlock->name] = $tplBlock->data;
    }

    public function setTplVars($tplVars) {
        foreach ($tplVars as $key => $value) {
    	   $this->data[$key] = $value;
        }
    }

    public function export_for_template(renderer_base $output) {
    	return $this->data;
    }

    public function allCtryAvgBlock(renderer_base $output){
        return $this->data['AllCtryAvgBlock'];
    }

    public function allCtryProgressBlock(renderer_base $output){
        return $this->data['AllCtryProgressBlock'];
    }

    public function allCtryTimeCompBlock(renderer_base $output){
        return $this->data['AllCtryTimeCompBlock'];
    }
}

//https://v4-alpha.getbootstrap.com/components/card/

class report_iomadanalytics_renderer extends \plugin_renderer_base {

    protected function render_report_iomadanalytics(report_iomadanalytics $widget) {
        $data = $widget->export_for_template($this);
        return parent::render_from_template('report_iomadanalytics/system_overview', $data);
    }

    public function render_allCtryAvgBlock(report_iomadanalytics $widget) {
        $data['AllCtryAvgBlock'] = $widget->allCtryAvgBlock($this);
        return parent::render_from_template('report_iomadanalytics/AllCtryAvgBlock', $data);
    }

    public function render_allCtryProgressBlock(report_iomadanalytics $widget) {
        $data['AllCtryProgressBlock'] = $widget->allCtryProgressBlock($this);
        return parent::render_from_template('report_iomadanalytics/AllCtryProgressBlock', $data);
    }

    public function render_allCtryTimeCompBlock(report_iomadanalytics $widget) {
        $data['AllCtryTimeCompBlock'] = $widget->allCtryTimeCompBlock($this);
        return parent::render_from_template('report_iomadanalytics/AllCtryTimeCompBlock', $data);
    }

    // protected function render_report_iomadanalytics(report_iomadanalytics $widget) {
    //     $data = $widget->export_for_template($this);
    //     return parent::render_from_template('report_iomadanalytics/all_ctry_avg_block', $data);
    // }
}