<?php

$tasks = array(
	array(
		'classname' => 'report_iomadanalytics\task\SystemOverview',
		'blocking' => 0,
		'minute' => '0',
		'hour' => '*',
		'day' => '*',
		'dayofweek' => '*',
		'month' => '*'
	),
	array(
		'classname' => 'report_iomadanalytics\task\GradesGraph',
		'blocking' => 0,
		'minute' => '0',
		'hour' => '*',
		'day' => '*',
		'dayofweek' => '*',
		'month' => '*'
	),
	array(
		'classname' => 'report_iomadanalytics\task\FilterTabs',
		'blocking' => 0,
		'minute' => '0',
		'hour' => '3',
		'day' => '*',
		'dayofweek' => '1-5',
		'month' => '*'
	)
);