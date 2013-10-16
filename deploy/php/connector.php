<?php

error_reporting(0); // Set E_ALL for debuging

include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderConnector.class.php';
include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinder.class.php';
include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeDriver.class.php';
include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeLocalFileSystem.class.php';
// Required for MySQL storage connector
// include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeMySQL.class.php';
// Required for FTP connector support
// include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeFTP.class.php';


/**
 * Simple function to demonstrate how to control file access using "accessControl" callback.
 * This method will disable accessing files/folders starting from '.' (dot)
 *
 * @param  string  $attr  attribute name (read|write|locked|hidden)
 * @param  string  $path  file path relative to volume root directory started with directory separator
 * @return bool|null
 **/
function access($attr, $path, $data, $volume) {
	return strpos(basename($path), '.') === 0       // if file/folder begins with '.' (dot)
		? !($attr == 'read' || $attr == 'write')    // set read+write to false, other (locked+hidden) set to true
		:  null;                                    // else elFinder decide it itself
}


// Documentation for connector options:
// https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options
$opts = array(
    'roots' => array(
        array(
            'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
            'path'          =>  getenv("HOME"),  // path to files (REQUIRED)
            'alias'         => 'Home', // The name to replace your actual path name. (OPTIONAL)
            'accessControl' => 'access', // disable and hide dot starting files (OPTIONAL)
            'tmbURL'        => 'https://d3uybqv7a64u59.cloudfront.net/latest/apps/elFinder/img' 
        ),
        array(
            'driver'        => 'LocalFileSystem',
            'path'          => getenv("PROJECT_HOME"),
            'alias'         => getenv("PROJECT_NAME"),
            'accessControl' => 'access',      // disable and hide dot starting files (OPTIONAL)
            'tmbURL'        => 'https://d3uybqv7a64u59.cloudfront.net/latest/apps/elFinder/img' 
        )
    )
);

// run elFinder
$connector = new elFinderConnector(new elFinder($opts));
$connector->run();

