<?php

/**
* Write data to a flat file, mainly for caching purpose
*/
class FlatFile
{

	// The file name to write in
	public $file;

	// The content to write in $this->file
	public $content;

	public function setFileName($file)
	{
		$this->file = $file;
	}

	public function setFileContent($content)
	{
		$this->content = $content;
	}

	public function writeToFile()
	{
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/iomadanalytics/templates/{$this->file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $this->content);
		fclose($myfile);
	}
}