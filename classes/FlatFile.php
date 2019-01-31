<?php

/**
* Write data to a flat file, mainly for caching purpose

include('/var/www/html/report/iomadanalytics/classes/FlatFile.php');
$f = new FlatFile();
$f->setFilePath($CFG->dirroot.'/theme/kpdesktop/');
$f->setFileName('theme.txt');
$f->setObContent($PAGE);
$f->writeToFile();

*/
class FlatFile
{
	// The path that the file should be created in
	public $path;

	// The file name to write in
	public $file;

	// The content to write in $this->file
	public $content = '';

	public function setFilePath($path)
	{
		$this->path = $path;
	}

	public function getFilePath()
	{
		if (!isset($CFG)) {
			global $CFG;
		}

		if (empty($this->path)) {
			return $CFG->dirroot.'/report/iomadanalytics/templates/';
		} else {
			return $this->path;
		}
	}

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
		$this->content .= ob_get_contents();
		ob_end_clean();
	}

	public function writeToFile()
	{
		global $CFG;
		$path = $this->getFilePath();
		$myfile = fopen($path.$this->file, "r+");
		fwrite($myfile, $this->content);
		fclose($myfile);
	}
}