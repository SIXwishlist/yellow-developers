<?php
// Release plugin, https://github.com/datenstrom/yellow-developers
// Copyright (c) 2013-2017 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowRelease
{
	const VERSION = "0.7.1";

	// Handle plugin initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("releasePluginsDir", "/Users/Shared/Github/yellow-plugins/");
		$this->yellow->config->setDefault("releaseThemesDir", "/Users/Shared/Github/yellow-themes/");
		$this->yellow->config->setDefault("releaseZipArchiveDir", "zip/");
		$this->yellow->config->setDefault("releaseDocumentationFile", "README.md");
	}

	// Handle command help
	function onCommandHelp()
	{
		return "release [DIRECTORY]\n";
	}
	
	// Handle command
	function onCommand($args)
	{
		list($command) = $args;
		switch($command)
		{
			case "release":	$statusCode = $this->releaseCommand($args); break;
			default:		$statusCode = 0;
		}
		return $statusCode;
	}

	// Update release files
	function releaseCommand($args)
	{
		$statusCode = 0;
		list($command, $path) = $args;
		if(empty($path))
		{
			$path = rtrim($this->yellow->config->get("releasePluginsDir"), '/').'/';
			$statusCode = max($statusCode, $this->updateSoftwareFiles($path));
			$statusCode = max($statusCode, $this->updateSoftwareArchives($path));
			$path = rtrim($this->yellow->config->get("releaseThemesDir"), '/').'/';
			$statusCode = max($statusCode, $this->updateSoftwareFiles($path));
			$statusCode = max($statusCode, $this->updateSoftwareArchives($path));
		} else {
			$path = rtrim($path, '/').'/';
			$statusCode = max($statusCode, $this->updateSoftwareFiles($path));
			$statusCode = max($statusCode, $this->updateSoftwareArchives($path));
		}
		echo "Yellow $command: Release files ".($statusCode!=200 ? "not " : "")."updated\n";
		return $statusCode;
	}
	
	// Update software files
	function updateSoftwareFiles($path)
	{
		$statusCode = 200;
		if(is_dir($path))
		{
			$data = $this->getSoftwareVersionFromRepository($path);
			$statusCode = max($statusCode, $this->updateSoftwareVersion($path, $data));
			$statusCode = max($statusCode, $this->updateSoftwareResource($path, $data));
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				list($software, $version) = $this->getSoftwareVersionFromDirectory("$path$entry/");
				$statusCode = max($statusCode, $this->updateSoftwareInformation("$path$entry/", $version));
				$statusCode = max($statusCode, $this->updateSoftwareDocumentation("$path$entry/", $version));
			}
		} else {
			$statusCode = 500;
			echo "ERROR updating files: Can't find directory '$path'!\n";
		}
		return $statusCode;
	}
	
	// Update software version file
	function updateSoftwareVersion($path, $data)
	{
		$statusCode = 200;
		$fileName = $path.$this->yellow->config->get("updateVersionFile");
		if(is_file($fileName))
		{
			$fileData = $this->yellow->toolbox->readFile($fileName);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !is_null($data[$matches[1]]))
				{
					list($version, $url) = explode(',', $matches[2]);
					$version = $data[$matches[1]];
					$fileDataNew .= "$matches[1]: $version,$url\n";
				} else {
					$fileDataNew .= $line;
				}
			}
			if($fileData!=$fileDataNew)
			{
				if(!$this->yellow->toolbox->createFile($fileName, $fileDataNew))
				{
					$statusCode = 500;
					echo "ERROR updating files: Can't write file '$fileName'!\n";
				}
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowRelease::updateSoftwareVersion file:$fileName<br/>\n";
		}
		return $statusCode;
	}

	// Update software resource file
	function updateSoftwareResource($path, $data)
	{
		$statusCode = 200;
		$fileName = $path.$this->yellow->config->get("updateResourceFile");
		if(is_file($fileName))
		{
			$resourceData = $this->getSoftwareResourceFromRepository($path);
			$fileData = $this->yellow->toolbox->readFile($fileName);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]))
				{
					list($softwareNew) = explode('/', $matches[1]);
					if(!is_null($data[$softwareNew]) && !is_null($resourceData[$softwareNew]))
					{
						if($software!=$softwareNew)
						{
							$software = $softwareNew;
							$fileDataNew .= $resourceData[$softwareNew];
						}
					} else {
						$fileDataNew .= $line;
					}
				} else {
					$fileDataNew .= $line;
				}
			}
			if($fileData!=$fileDataNew)
			{
				if(!$this->yellow->toolbox->createFile($fileName, $fileDataNew))
				{
					$statusCode = 500;
					echo "ERROR updating files: Can't write file '$fileName'!\n";
				}
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowRelease::updateSoftwareResource file:$fileName<br/>\n";
		}
		return $statusCode;
	}
	
	// Update software information file
	function updateSoftwareInformation($path, $version)
	{
		$statusCode = 200;
		$fileName = $path.$this->yellow->config->get("updateInformationFile");
		if(is_file($fileName) && !empty($version))
		{
			$fileData = $this->yellow->toolbox->readFile($fileName);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], '/'))
				{
					list($dummy, $entry) = explode('/', $matches[1], 2);
					if($dummy[0]!='Y') list($entry, $flags) = explode(',', $matches[2], 2); //TODO: remove later, converts old file format
					if(is_file($path.$entry)) { $published = filemtime($path.$entry); break; }
				}
			}
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(lcfirst($matches[1])=="version") $line = "Version: $version\n";
				if(lcfirst($matches[1])=="published") $line = "Published: ".date("Y-m-d H:i:s", $published)."\n";
				$fileDataNew .= $line;
			}
			if($fileData!=$fileDataNew)
			{
				if(!$this->yellow->toolbox->createFile($fileName, $fileDataNew))
				{
					$statusCode = 500;
					echo "ERROR updating files: Can't write file '$fileName'!\n";
				}
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowRelease::updateSoftwareInformation file:$fileName<br/>\n";
		}
		return $statusCode;
	}

	// Update software documentation file
	function updateSoftwareDocumentation($path, $version)
	{
		$statusCode = 200;
		$fileName = $path.$this->yellow->config->get("releaseDocumentationFile");
		if(is_file($fileName) && !empty($version))
		{
			$fileData = $this->yellow->toolbox->readFile($fileName);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^(.*?)([0-9\.]+)$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !$found)
				{
					$fileDataNew .= "$matches[1]$version\n";
					$found = true;
				} else {
					$fileDataNew .= $line;
				}
			}
			if($fileData!=$fileDataNew)
			{
				if(!$this->yellow->toolbox->createFile($fileName, $fileDataNew))
				{
					$statusCode = 500;
					echo "ERROR updating files: Can't write file '$fileName'!\n";
				}
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowRelease::updateSoftwareDocumentation file:$fileName<br/>\n";
		}
		return $statusCode;
	}
	
	// Update software archives
	function updateSoftwareArchives($path)
	{
		$statusCode = 200;
		if(is_dir($path))
		{
			$pathZipArchive = $path.$this->yellow->config->get("releaseZipArchiveDir");
			$fileNameInformation = $this->yellow->config->get("updateInformationFile");
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				if("$path$entry/"==$pathZipArchive) continue;
				if(!is_file("$path$entry/$fileNameInformation")) continue;
				$zip = new ZipArchive();
				$fileNameZipArchive = "$pathZipArchive$entry.zip";
				if($zip->open($fileNameZipArchive, ZIPARCHIVE::CREATE|ZIPARCHIVE::OVERWRITE)===true)
				{
					$modified = 0;
					$fileNamesRequired = $this->getSoftwareArchiveEntries("$path$entry/");
					$fileNamesFound = $this->yellow->toolbox->getDirectoryEntries($path.$entry, "/.*/", true, false);
					foreach($fileNamesFound as $fileName)
					{
						if(!in_array($fileName, $fileNamesRequired)) continue;
						$zip->addFile($fileName, substru($fileName, strlenu($path)));
						$modified = max($modified, $this->yellow->toolbox->getFileModified($fileName));
						unset($fileNamesRequired[array_search($fileName, $fileNamesRequired)]);
					}
					if(count($fileNamesRequired))
					{
						$statusCode = 500;
						foreach($fileNamesRequired as $fileName)
						{
							echo "ERROR updating files: Can't find file '$fileName'!\n";
						}
					}
					if(!$zip->close() || !$this->yellow->toolbox->modifyFile($fileNameZipArchive, $modified))
					{
						$statusCode = 500;
						echo "ERROR updating files: Can't write file '$fileNameZipArchive'!\n";
					}
				} else {
					$statusCode = 500;
					echo "ERROR updating files: Can't write file '$fileNameZipArchive'!\n";					
				}
				if(defined("DEBUG") && DEBUG>=2) echo "YellowRelease::updateSoftwareArchives file:$fileNameZipArchive<br/>\n";
			}
		}
		return $statusCode;
	}
	
	// Return software files for archive
	function getSoftwareArchiveEntries($path)
	{
		$entries = array();
		$fileName = $path.$this->yellow->config->get("updateInformationFile");
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], '/'))
				{
					list($dummy, $entry) = explode('/', $matches[1], 2);
					if($dummy[0]!='Y') list($entry, $flags) = explode(',', $matches[2], 2); //TODO: remove later, converts old file format
					if(!preg_match("/delete/i", $flags)) array_push($entries, "$path$entry");
				}
		}
		array_push($entries, $fileName);
		return $entries;
	}
	
	// Return software version from repository
	function getSoftwareVersionFromRepository($path)
	{
		$data = array();
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true) as $entry)
		{
			list($software, $version) = $this->getSoftwareVersionFromDirectory($entry);
			if(!empty($software) && !empty($version)) $data[$software] = $version;
		}
		return $data;
	}
	
	// Return software version from directory
	function getSoftwareVersionFromDirectory($path)
	{
		$software = $version = "";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.php$/", false, false) as $entry)
		{
			$fileData = $this->yellow->toolbox->readFile($entry, 4096);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s+(.*?)$/", $line, $matches);
				if($matches[1]=="class" && !strempty($matches[2])) $software = $matches[2];
				if($matches[1]=="const" && preg_match("/\"([0-9\.]+)\"/", $line, $matches)) $version = $matches[1];
				if($matches[1]=="function") break;
			}
		}
		return array($software, $version);
	}

	// Return software resource from repository
	function getSoftwareResourceFromRepository($path)
	{
		$data = array();
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true) as $entry)
		{
			list($software, $resource) = $this->getSoftwareResourceFromDirectory("$entry/");
			if(!empty($software) && !empty($resource)) $data[$software] = $resource;
		}
		return $data;
	}
	
	// Return software resource from directory
	function getSoftwareResourceFromDirectory($path)
	{
		$software = $resource = "";
		$fileName = $path.$this->yellow->config->get("updateInformationFile");
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
			if(lcfirst($matches[1])=="plugin" || lcfirst($matches[1])=="theme") $software = $matches[2];
			if(!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], '/'))
			{
				list($dummy, $entry) = explode('/', $matches[1], 2);
				list($fileName, $flags) = explode(',', $matches[2], 2);
				if($dummy[0]!='Y') //TODO: remove later, converts old file format
				{
					$matches[1] = "$software/$fileName";
					$matches[2] = "$dummy/$entry,$flags";
				}
				$resource .= "$matches[1]: $matches[2]\n";
			}
		}
		return array($software, $resource);
	}
}

$yellow->plugins->register("release", "YellowRelease", YellowRelease::VERSION);
?>
