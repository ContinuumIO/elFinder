<?php

function chmodnum($chmod) {
    $trans = array('-' => '0', 'r' => '4', 'w' => '2', 'x' => '1');
    $chmod = substr(strtr($chmod, $trans), 1);
    $array = str_split($chmod, 3);
    return array_sum(str_split($array[0])) . array_sum(str_split($array[1])) . array_sum(str_split($array[2]));
}

/**
 * Simple elFinder driver for FTP
 *
 * @author Dmitry (dio) Levashov
 * @author Cem (discofever)
 **/
class elFinderVolumeFTP extends elFinderVolumeDriver {
	
	/**
	 * Driver id
	 * Must be started from letter and contains [a-z0-9]
	 * Used as part of volume id
	 *
	 * @var string
	 **/
	protected $driverId = 'f';
	
	/**
	 * FTP Connection Instance
	 *
	 * @var ftp
	 **/
	protected $connect = null;
	
	/**
	 * Directory for tmp files
	 * If not set driver will try to use tmbDir as tmpDir
	 *
	 * @var string
	 **/
	protected $tmpPath = '';
	
	/**
	 * Files info cache
	 *
	 * @var array
	 **/
	protected $cache = array();
		
	/**
	 * Last FTP error message
	 *
	 * @var string
	 **/
	protected $ftpError = '';
	
	/**
	 * undocumented class variable
	 *
	 * @var string
	 **/
	protected $separator = '';
	
	protected $ftpOsUnix;
	
	protected $tmp = '';
	
	/**
	 * undocumented class variable
	 *
	 * @var string
	 **/
	// protected $cache = array();
	
	/**
	 * Constructor
	 * Extend options with required fields
	 *
	 * @return void
	 * @author Dmitry (dio) Levashov
	 * @author Cem (DiscoFever)
	 **/
	public function __construct() {
		$opts = array(
			'host'          => 'localhost',
			'user'          => '',
			'pass'          => '',
			'port'          => 21,
			'mode'        	=> 'passive',
			'path'			=> '/',
			'timeout'		=> 10,
			'owner'         => true,
			'tmbPath'       => '',
			'tmpPath'       => ''
		);
		$this->options = array_merge($this->options, $opts); 
		$this->options['mimeDetect'] = 'internal';
	}
	
	/*********************************************************************/
	/*                        INIT AND CONFIGURE                         */
	/*********************************************************************/
	
	/**
	 * Prepare FTP connection
	 * Connect to remote server and check if credentials are correct, if so, store the connection id in $ftp_conn
	 *
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 * @author Cem (DiscoFever)
	 **/
	protected function init() {

		if (!$this->options['host'] 
		||  !$this->options['user'] 
		||  !$this->options['pass'] 
		||  !$this->options['port']
		||  !$this->options['path']) {
			return $this->setError('Required options undefined.');
		}
		
		if (!function_exists('ftp_connect')) {
			return $this->setError('FTP extension not loaded..');
		}
		// normalize root path
		$this->root = $this->options['path'] = $this->_normpath($this->options['path']);
		
		if (empty($this->options['alias'])) {
			$num = elFinder::$volumesCnt-1;
			$this->options['alias'] = $this->root == '/' || $this->root == '.' ? 'FTP folder '.$num : basename($this->root);
		}

		$this->rootName = $this->options['alias'];
		$this->options['separator'] = '/';
		return 
		$this->connect();
		
	}


	/**
	 * Configure after successfull mount.
	 *
	 * @return void
	 * @author Dmitry (dio) Levashov
	 **/
	protected function configure() {
		parent::configure();
		
		if (!empty($this->options['tmpPath'])) {
			if ((is_dir($this->options['tmpPath']) || @mkdir($this->options['tmpPath'])) && is_writable($this->options['tmpPath'])) {
				$this->tmp = $this->options['tmpPath'];
			}
		}
		
		if (!$this->tmp && $this->tmbPath) {
			$this->tmp = $this->tmbPath;
		}
		
		if (!$this->tmp) {
			$this->disabled[] = 'mkdir';
			$this->disabled[] = 'mkfile';
			$this->disabled[] = 'paste';
			$this->disabled[] = 'duplicate';
			$this->disabled[] = 'upload';
			$this->disabled[] = 'edit';
			$this->disabled[] = 'archive';
			$this->disabled[] = 'extract';
		}
		
		// echo $this->tmp;
		
	}
	
	/**
	 * Connect to ftp server
	 *
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function connect() {
		if (!($this->connect = ftp_connect($this->options['host'], $this->options['port'], $this->options['timeout']))) {
			return $this->setError('Unable to connect to FTP server '.$this->options['host']);
		}
		if (!ftp_login($this->connect, $this->options['user'], $this->options['pass'])) {
			$this->umount();
			return $this->setError('Unable to login into '.$this->options['host']);
		}
		
		// switch off extended passive mode - may be usefull for some servers
		@ftp_exec($this->connect, 'epsv4 off' );
		// enter passive mode if required
		ftp_pasv($this->connect, $this->options['mode'] == 'passive');

		// enter root folder
		if (!ftp_chdir($this->connect, $this->root) 
		|| $this->root != ftp_pwd($this->connect)) {
			$this->umount();
			return $this->setError('Unable to open root folder.');
		}
		
		// check for MLST support
		$features = ftp_raw($this->connect, 'FEAT');
		if (!is_array($features)) {
			$this->umount();
			return $this->setError('Server does not support command FEAT. wtf? 0_o');
		}

		foreach ($features as $feat) {
			if (strpos(trim($feat), 'MLST') === 0) {
				return true;
			}
		}
		
		return $this->setError('Server does not support command MLST. wtf? 0_o');
	}
	
	/*********************************************************************/
	/*                               FS API                              */
	/*********************************************************************/

	/**
	 * Close opened connection
	 *
	 * @return void
	 * @author Dmitry (dio) Levashov
	 **/
	public function umount() {
		$this->connect && @ftp_close($this->connect);
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Dmitry Levashov
	 **/
	protected function parseRaw($raw) {
		$info = preg_split("/\s+/", $raw, 9);
		$stat = array();

		if (count($info) < 9 || $info[8] == '.' || $info[8] == '..') {
			return false;
		}

		if (!isset($this->ftpOsUnix)) {
			$this->ftpOsUnix = !preg_match('/\d/', substr($info[0], 0, 1));
		}
		
		if ($this->ftpOsUnix) {
			
			$stat['ts'] = strtotime($info[5].' '.$info[6].' '.$info[7]);
			if (empty($stat['ts'])) {
				$stat['ts'] = strtotime($info[6].' '.$info[5].' '.$info[7]);
			}
			
			$name = $info[8];
			
			if (preg_match('|(.+)\-\>(.+)|', $name, $m)) {
				$name   = trim($m[1]);
				$target = trim($m[2]);
				if (substr($target, 0, 1) != '/') {
					$target = $this->root.'/'.$target;
				}
				$target = $this->_normpath($target);
				$stat['name']  = $name;
				if ($this->_inpath($target, $this->root) 
				&& ($tstat = $this->stat($target))) {
					$stat['size']  = $tstat['mime'] == 'directory' ? 0 : $info[4];
					$stat['alias'] = $this->_relpath($target);
					$stat['thash'] = $tstat['hash'];
					$stat['mime']  = $tstat['mime'];
					$stat['read']  = $tstat['read'];
					$stat['write']  = $tstat['write'];
				} else {
					
					$stat['mime']  = 'symlink-broken';
					$stat['read']  = false;
					$stat['write'] = false;
					$stat['size']  = 0;
					
				}
				return $stat;
			}
			
			$perm = $this->parsePermissions($info[0]);
			$stat['name']  = $name;
			$stat['mime']  = substr(strtolower($info[0]), 0, 1) == 'd' ? 'directory' : $this->mimetype($stat['name']);
			$stat['size']  = $stat['mime'] == 'directory' ? 0 : $info[4];
			$stat['read']  = $perm['read'];
			$stat['write'] = $perm['write'];
			$stat['perm']  = substr($info[0], 1);
		} else {
			
		}

		return $stat;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Dmitry Levashov
	 **/
	protected function parsePermissions($perm) {
		$res   = array();
		$parts = array();
		$owner = $this->options['owner'];
		for ($i = 0, $l = strlen($perm); $i < $l; $i++) {
			$parts[] = substr($perm, $i, 1);
		}

		$read = ($owner && $parts[0] == 'r') || $parts[4] == 'r' || $parts[7] == 'r';
		
		return array(
			'read'  => $parts[0] == 'd' ? $read && (($owner && $parts[3] == 'x') || $parts[6] == 'x' || $parts[9] == 'x') : $read,
			'write' => ($owner && $parts[2] == 'w') || $parts[5] == 'w' || $parts[8] == 'w'
		);
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Dmitry Levashov
	 **/
	protected function cacheDir($path) {
		$this->dirsCache[$path] = array();

		foreach (ftp_rawlist($this->connect, $path) as $raw) {
			if (($stat = $this->parseRaw($raw))) {
				$p    = $path.'/'.$stat['name'];
				$stat = $this->updateCache($p, $stat);
				if (empty($stat['hidden'])) {
					$files[] = $stat;
					$this->dirsCache[$path][] = $p;
				}
			}
		}
	}

	/*********************** paths/urls *************************/
	
	/**
	 * Return parent directory path
	 *
	 * @param  string  $path  file path
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _dirname($path) {
		return dirname($path);
	}

	/**
	 * Return file name
	 *
	 * @param  string  $path  file path
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _basename($path) {
		return basename($path);
	}

	/**
	 * Join dir name and file name and retur full path
	 *
	 * @param  string  $dir
	 * @param  string  $name
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _joinPath($dir, $name) {
		return $dir.DIRECTORY_SEPARATOR.$name;
	}
	
	/**
	 * Return normalized path, this works the same as os.path.normpath() in Python
	 *
	 * @param  string  $path  path
	 * @return string
	 * @author Troex Nevelin
	 **/
	protected function _normpath($path) {
		if (empty($path)) {
			$path = '.';
		}
		// path must be start with /
		$path = preg_replace('|^\.\/?|', '/', $path);
		$path = preg_replace('/^([^\/])/', "/$1", $path);

		if (strpos($path, '/') === 0) {
			$initial_slashes = true;
		} else {
			$initial_slashes = false;
		}
			
		if (($initial_slashes) 
		&& (strpos($path, '//') === 0) 
		&& (strpos($path, '///') === false)) {
			$initial_slashes = 2;
		}
			
		$initial_slashes = (int) $initial_slashes;

		$comps = explode('/', $path);
		$new_comps = array();
		foreach ($comps as $comp) {
			if (in_array($comp, array('', '.'))) {
				continue;
			}
				
			if (($comp != '..') 
			|| (!$initial_slashes && !$new_comps) 
			|| ($new_comps && (end($new_comps) == '..'))) {
				array_push($new_comps, $comp);
			} elseif ($new_comps) {
				array_pop($new_comps);
			}
		}
		$comps = $new_comps;
		$path = implode('/', $comps);
		if ($initial_slashes) {
			$path = str_repeat('/', $initial_slashes) . $path;
		}
		
		return $path ? $path : '.';
	}
	
	/**
	 * Return file path related to root dir
	 *
	 * @param  string  $path  file path
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _relpath($path) {
		return $path == $this->root ? '' : substr($path, strlen($this->root)+1);
	}
	
	/**
	 * Convert path related to root dir into real path
	 *
	 * @param  string  $path  file path
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _abspath($path) {
		return $path == $this->separator ? $this->root : $this->root.$this->separator.$path;
	}
	
	/**
	 * Return fake path started from root dir
	 *
	 * @param  string  $path  file path
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _path($path) {
		return $this->rootName.($path == $this->root ? '' : $this->separator.$this->_relpath($path));
	}
	
	/**
	 * Return true if $path is children of $parent
	 *
	 * @param  string  $path    path to check
	 * @param  string  $parent  parent path
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _inpath($path, $parent) {
		return $path == $parent || strpos($path, $parent.'/') === 0;
	}
	
	/***************** file stat ********************/
	/**
	 * Return stat for given path.
	 * Stat contains following fields:
	 * - (int)    size    file size in b. required
	 * - (int)    ts      file modification time in unix time. required
	 * - (string) mime    mimetype. required for folders, others - optionally
	 * - (bool)   read    read permissions. required
	 * - (bool)   write   write permissions. required
	 * - (bool)   locked  is object locked. optionally
	 * - (bool)   hidden  is object hidden. optionally
	 * - (string) alias   for symlinks - link target path relative to root path. optionally
	 * - (string) target  for symlinks - link target path. optionally
	 *
	 * If file does not exists - returns empty array or false.
	 *
	 * @param  string  $path    file path 
	 * @return array|false
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _stat($path) {
		$raw = ftp_raw($this->connect, 'MLST '.$path);

		if (is_array($raw) && count($raw) > 1 && substr(trim($raw[0]), 0, 1) == 2) {
			$parts = explode(';', trim($raw[1]));
			array_pop($parts);
			$parts = array_map('strtolower', $parts);
			$stat  = array();
			// debug($parts);
			foreach ($parts as $part) {

				list($key, $val) = explode('=', $part);

				switch ($key) {
					case 'type':
						$stat['mime'] = strpos($val, 'dir') !== false ? 'directory' : $this->mimetype($path);
						break;

					case 'size':
						$stat['size'] = $val;
						break;

					case 'modify':
						$ts = mktime(intval(substr($val, 8, 2)), intval(substr($val, 10, 2)), intval(substr($val, 12, 2)), intval(substr($val, 4, 2)), intval(substr($val, 6, 2)), substr($val, 0, 4));
						$stat['ts'] = $ts;
						$stat['date'] = $this->formatDate($ts);
						break;

					case 'unix.mode':
						$stat['chmod'] = $val;
						break;

					case 'perm':
						$val = strtolower($val);
						$stat['read']  = (int)preg_match('/e|l|r/', $val);
						$stat['write'] = (int)preg_match('/w|m|c/', $val);
						if (!preg_match('/f|d/', $val)) {
							$stat['locked'] = 1;
						}
						break;
				}
			}
			if (empty($stat['mime'])) {
				return array();
			}
			if ($stat['mime'] == 'directory') {
				$stat['size'] = 0;
			}
			
			if (isset($stat['chmod'])) {
				$stat['perm'] = '';
				if ($stat['chmod'][0] == 0) {
					$stat['chmod'] = substr($stat['chmod'], 1);
				}

				for ($i = 0; $i <= 2; $i++) {
					$perm[$i] = array(false, false, false);
					$n = $stat['chmod'][$i];
					
					if ($n - 4 >= 0) {
						$perm[$i][0] = true;
						$n = $n - 4;
						$stat['perm'] .= 'r';
					} else {
						$stat['perm'] .= '-';
					}
					
					if ($n - 2 >= 0) {
						$perm[$i][1] = true;
						$n = $n - 2;
						$stat['perm'] .= 'w';
					} else {
						$stat['perm'] .= '-';
					}

					if ($n - 1 == 0) {
						$perm[$i][2] = true;
						$stat['perm'] .= 'x';
					} else {
						$stat['perm'] .= '-';
					}
					
					$stat['perm'] .= ' ';
				}
				
				$stat['perm'] = trim($stat['perm']);

				$owner = $this->options['owner'];
				$read = ($owner && $perm[0][0]) || $perm[1][0] || $perm[2][0];

				$stat['read']  = $stat['mime'] == 'directory' ? $read && (($owner && $perm[0][2]) || $perm[1][2] || $perm[2][2]) : $read;
				$stat['write'] = ($owner && $perm[0][1]) || $perm[1][1] || $perm[2][1];
				unset($stat['chmod']);

			}
			
			return $stat;
			
		}
		
		return array();
	}
	
	/**
	 * Return true if path is dir and has at least one childs directory
	 *
	 * @param  string  $path  dir path
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _subdirs($path) {
		foreach (ftp_rawlist($this->connect, $path) as $str) {
			if (($stat = $this->parseRaw($str)) && $stat['mime'] == 'directory') {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Return object width and height
	 * Ususaly used for images, but can be realize for video etc...
	 *
	 * @param  string  $path  file path
	 * @param  string  $mime  file mime type
	 * @return string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _dimensions($path, $mime) {
		return false;
	}
	
	/******************** file/dir content *********************/
		
	/**
	 * Return files list in directory.
	 *
	 * @param  string  $path  dir path
	 * @return array
	 * @author Dmitry (dio) Levashov
	 * @author Cem (DiscoFever)
	 **/
	protected function _scandir($path) {
		$files = array();
		
		foreach (ftp_rawlist($this->connect, $path) as $str) {
			if (($stat = $this->parseRaw($str))) {
				$files[] = $path.DIRECTORY_SEPARATOR.$stat['name'];
			}
		}

		return $files;
	}
		
	/**
	 * Open file and return file pointer
	 *
	 * @param  string  $path  file path
	 * @param  bool    $write open file for writing
	 * @return resource|false
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _fopen($path, $mode='rb') {
		
		if ($this->tmp) {
			$local = $this->tmp.DIRECTORY_SEPARATOR.md5($path);
			// $local = $this->tmp.DIRECTORY_SEPARATOR.basename($path);
			// $mime = $this->mimetype(basename($path));
			if (ftp_get($this->connect, $local, $path, FTP_BINARY)) {
				return @fopen($local, 'r');
			}
		}
		
		return false;

		return @fopen($path, $mode);
	}
	
	/**
	 * Close opened file
	 *
	 * @param  resource  $fp  file pointer
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _fclose($fp, $path='') {
		@fclose($fp);
		if ($path) {
			@unlink($this->tmp.DIRECTORY_SEPARATOR.md5($path));
		}
	}
	
	/********************  file/dir manipulations *************************/
	
	/**
	 * Create dir
	 *
	 * @param  string  $path  parent dir path
	 * @param string  $name  new directory name
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _mkdir($path, $name) {
		$path = $path.DIRECTORY_SEPARATOR.$name;
		
		if ($path == '' || (!($this->ftp_conn)))
		{
			return false;
		}

		$result = @ftp_mkdir($this->ftp_conn, $path);

		if ($result === false)
		{
			$this->setError('Unable to create remote directory.');
			return false;
		}

		/* TODO : implement for ftp */
		/*
		if (@mkdir($path)) {
			@chmod($path, $this->options['dirMode']);
			return true;
		}
		*/
		return true;
	}
	
	/**
	 * Create file
	 *
	 * @param  string  $path  parent dir path
	 * @param string  $name  new file name
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _mkfile($path, $name) {
		die('Not yet implemented. (_mkfile)');
		$path = $path.DIRECTORY_SEPARATOR.$name;
		
		if (($fp = @fopen($path, 'w'))) {
			@fclose($fp);
			@chmod($path, $this->options['fileMode']);
			return true;
		}
		return false;
	}
	
	/**
	 * Create symlink
	 *
	 * @param  string  $target  link target
	 * @param  string  $path    symlink path
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _symlink($target, $path, $name='') {
		die('Not yet implemented. (_symlink)');
		if (!$name) {
			$name = basename($path);
		}
		return @symlink('.'.DIRECTORY_SEPARATOR.$this->_relpath($target), $path.DIRECTORY_SEPARATOR.$name);
	}
	
	/**
	 * Copy file into another file
	 *
	 * @param  string  $source     source file path
	 * @param  string  $targetDir  target directory path
	 * @param  string  $name       new file name
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _copy($source, $targetDir, $name='') {
		die('Not yet implemented. (_copy)');
		$target = $targetDir.DIRECTORY_SEPARATOR.($name ? $name : basename($source));
		return copy($source, $target);
	}
	
	/**
	 * Move file into another parent dir
	 *
	 * @param  string  $source  source file path
	 * @param  string  $target  target dir path
	 * @param  string  $name    file name
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _move($source, $targetDir, $name='') {
		die('Not yet implemented. (_move)');
		$target = $targetDir.DIRECTORY_SEPARATOR.($name ? $name : basename($source));
		return @rename($source, $target);
	}
		
	/**
	 * Remove file
	 *
	 * @param  string  $path  file path
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _unlink($path) {
		if ($path == '' || (!($this->ftp_conn)))
		{
			return false;
		}
		
		$result = @ftp_delete($this->ftp_conn, $path);

		if ($result === false)
		{
			return $this->setError('Unable to delete remote file');
			return false;
		}
		return true;
	}

	/**
	 * Remove dir
	 *
	 * @param  string  $path  dir path
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _rmdir($path) {
		die('Not yet implemented. (_rmdir)');
		return @rmdir($path);
	}
	
	/**
	 * Create new file and write into it from file pointer.
	 * Return new file path or false on error.
	 *
	 * @param  resource  $fp   file pointer
	 * @param  string    $dir  target dir path
	 * @param  string    $name file name
	 * @return bool|string
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _save($fp, $dir, $name, $mime, $w, $h) {
		die('Not yet implemented. (_save)');
		$path = $dir.DIRECTORY_SEPARATOR.$name;

		if (!($target = @fopen($path, 'wb'))) {
			return false;
		}

		while (!feof($fp)) {
			fwrite($target, fread($fp, 8192));
		}
		fclose($target);
		@chmod($path, $this->options['fileMode']);
		clearstatcache();
		return $path;
	}
	
	/**
	 * Get file contents
	 *
	 * @param  string  $path  file path
	 * @return string|false
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _getContents($path) {
		ie('Not yet implemented. (_getContents)');
		return file_get_contents($path);
	}
	
	/**
	 * Write a string to a file
	 *
	 * @param  string  $path     file path
	 * @param  string  $content  new file content
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _filePutContents($path, $content) {
		die('Not yet implemented. (_filePutContents)');
		if (@file_put_contents($path, $content, LOCK_EX) !== false) {
			clearstatcache();
			return true;
		}
		return false;
	}

	/**
	 * Detect available archivers
	 *
	 * @return void
	 **/
	protected function _checkArchivers() {
		// die('Not yet implemented. (_checkArchivers)');
		return array();
	}

	/**
	 * Unpack archive
	 *
	 * @param  string  $path  archive path
	 * @param  array   $arc   archiver command and arguments (same as in $this->archivers)
	 * @return true
	 * @return void
	 * @author Dmitry (dio) Levashov
	 * @author Alexey Sukhotin
	 **/
	protected function _unpack($path, $arc) {
		die('Not yet implemented. (_unpack)');
		return false;
	}

	/**
	 * Recursive symlinks search
	 *
	 * @param  string  $path  file/dir path
	 * @return bool
	 * @author Dmitry (dio) Levashov
	 **/
	protected function _findSymlinks($path) {
		die('Not yet implemented. (_findSymlinks)');
		if (is_link($path)) {
			return true;
		}
		if (is_dir($path)) {
			foreach (scandir($path) as $name) {
				if ($name != '.' && $name != '..') {
					$p = $path.DIRECTORY_SEPARATOR.$name;
					if (is_link($p)) {
						return true;
					}
					if (is_dir($p) && $this->_findSymlinks($p)) {
						return true;
					} elseif (is_file($p)) {
						$this->archiveSize += filesize($p);
					}
				}
			}
		} else {
			$this->archiveSize += filesize($path);
		}
		
		return false;
	}

	/**
	 * Extract files from archive
	 *
	 * @param  string  $path  archive path
	 * @param  array   $arc   archiver command and arguments (same as in $this->archivers)
	 * @return true
	 * @author Dmitry (dio) Levashov, 
	 * @author Alexey Sukhotin
	 **/
	protected function _extract($path, $arc) {
		die('Not yet implemented. (_extract)');
		
	}
	
	/**
	 * Create archive and return its path
	 *
	 * @param  string  $dir    target dir
	 * @param  array   $files  files names list
	 * @param  string  $name   archive name
	 * @param  array   $arc    archiver options
	 * @return string|bool
	 * @author Dmitry (dio) Levashov, 
	 * @author Alexey Sukhotin
	 **/
	protected function _archive($dir, $files, $name, $arc) {
		die('Not yet implemented. (_archive)');
		return false;
	}
	
} // END class 


?>
		