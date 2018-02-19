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

	static function writeObContent($content)
	{
		ob_start();
		var_dump($content);
		$c = ob_get_contents();
		ob_end_clean();

		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/iomadanalytics/templates/debug.log", "w+") or die("Unable to open file!");
		fwrite($myfile, $c);
		fclose($myfile);
	}

	public function setObContent($ob)
	{
		ob_start();
		var_dump($ob);
		$this->content = ob_get_contents();
		ob_end_clean();
	}

	public function writeToFile()
	{
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/iomadanalytics/templates/{$this->file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $this->content);
		fclose($myfile);
	}
}