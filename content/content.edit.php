<?php 
/**
 * @package content
 * @author thomas appel <mail@thomas-appel.com>

 * Displays <a href="http://opensource.org/licenses/gpl-3.0.html">GNU Public License</a>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 */ 

require_once(EXTENSIONS . '/filemanager/content/content.settings.php');

Class contentExtensionFilemanagerEdit extends contentExtensionFilemanagerSettings {

	public function __construct(&$parent) {
		parent::__construct($parent);

		if (isset($_POST['type'])) {
			$this->task($_POST['type']);
		} else {
			$this->handleGeneralError(array('error' => 'can\'t proceed'));
		}
	}

	public function task($type) {
		switch ($type) {
		case 'move':
			$this->moveItem();
			break;
		case 'delete':
			$this->deleteItem();
			break;
		case 'create':
			$this->createDir();
			break;
		default:
			break;
		}
	}
	
	/**
	 * delete a file or a  from the server
	 */
	public function deleteItem() {
		$file = General::sanitize(FILEMANAGER_WORKSPACE . $_POST['file']);
		if (!file_exists($file)) {
			$this->handleGeneralError(array(
				'error' => 'can\'t delete unknown ' . ($_POST['dataType'] == 'file' ? 'file' : 'directory') . ' ' . $_POST['file']
			));

			return false;
		}
		if ($this->deleteFile($file)) {
			$this->_Result = array(
				'success' => array(
					'message' => 'successfully deleted {$file}', 
					'context' => array(
						'file' => basename(General::sanitize($_POST['file']))
					)
				)
			);
		}
			
	}

	/**
	 *
	 * move a file to a new location
	 */
	public function moveItem() {
		$fn = $_POST['from'];		
		$dstfn = General::sanitize(basename(FILEMANAGER_WORKSPACE . $fn));

		$destDir =  WORKSPACE . $_POST['to'] . '/';

		$newDest = $destDir . $dstfn;

		/*
		if (file_exists($newDest) || is_dir($newDest)) {
			$this->handleGeneralError(array(
				'error' => $_POST['dataType'] == 'file' ? 'file exists' : 'directory exists'
			));
			return;
		}
		*/
		$new_file = FILEMANAGER_WORKSPACE . $fn;	
		if ($this->moveFile($destDir, $dstfn, $new_file)) {
			$this->_Result = array(
				'success' => array(
					'message' => $_POST['dataType'] == 'file' ? 'file {$item} successfully moved to {$to}' : 'directory {$item} successfully moved {$to}',
					'context' => array(
						'to' => basename($destDir),
						'item' => basename($new_file)
					)
				)
			);
		} 	
	}

	public function process() {
		//$this->setSettings(false);
		parent::process();
		//print_r($this->_settings);
		//print_r($this->get('allow_dir_upload_files'));
	}

	/**
	 * tries to remove a file or directory from the server
	 *
	 * @param string $file file or directory to be removed
	 * @return boolean
	 */
	public function deleteFile($file) {
		$bn = basename($file);
		if (!is_writable($file)) {
			$this->handleAccessError(basename($file));
			return false;
		}
		if (is_dir($file)) {

			$parent = dirname($file);

			if ($this->isRoot($file)) {
				return $this->handleRootDirError($file);
			}

			// check if we can operate on the parent directory
			if (!is_writable($parent)) {
				$this->handleAccessError(basename($parent));
				return false;
			}

			if ($this->rrmdir($file) !== false) {
				return true;
			} else {
				$this->handleGeneralError(array(
					'error' => array(
						'message' => 'can\'t delete directory {$file}',
						'context' => array(
							'file' => basename($file)
						)
					)
				));
			}
		}

		if (is_file($file)) {
			if (unlink($file)) {
				return true;
			} else {

				$this->handleGeneralError(array(
					'error' => array(
						'message' => 'can\'t delete file {$file}',
						'context' => array(
							'file' => basename($file)
						)
					)
				));
			}
		}
		return false;
	}

	private function moveFile($dest_path, $dest_file, $source_file) {
		$new_file = $dest_path . $dest_file;

		$bn = basename($dest_path);

		if (is_dir($source_file)) {
			$has_root_dir = $this->scanForRootDirs($source_file);
			if(is_array($has_root_dir) && sizeof($has_root_dir) > 0) {
				return $this->handleRootDirError($has_root_dir[0]);
			}
		}
		
		if (!is_readable($dest_path) || !is_writeable($dest_path)) {
			$this->handleGeneralError();
			return false;
		}

		if (!is_dir($dest_path)) {
			$this->handleGeneralError(array(
				'error' => array(
					'message' => 'Directory {$dir} doesn\'t exist',
					'context' => array(
						'dir' => $bn
					)
				)
			));
			return false;
		}

		if (file_exists($new_file)) {
			$this->handleGeneralError(array(
				'error' => array(
					'message' => $_POST['dataType'] == 'file' ? 'file {$file} already exists' : 'directory {$file} already exists',
					'context' => array(
						'file' => basename($new_file),
						'path' => $dest_path,
					)
				)
			));
			return false;
		}

		try {
			rename($source_file, $new_file);
			return true;

		} catch (Exception $e) {
			$this->handleGeneralError(array(
				'error' => array(
					'message' => 'can\'t move {$file} to {$location}',
					'context' => array(
						'file' => $source_file,
						'location' => $dest_file,
					)
				)
			));
		}
	}

	/**
	 * attempts to create a new directory
	 * @return boolean
	 */ 
	public function createDir() {

		$name = $_POST['mkdir'];
		$dest_path = FILEMANAGER_WORKSPACE . $this->sanitizePathFragment($_POST['within']) . DIRECTORY_SEPARATOR;

		if (!is_readable($dest_path) || !is_writeable($dest_path)) {
			$this->handleAccessError();
			return false;
		}

		if (is_dir($dest_path . $name) || file_exists($dest_path . $name)) {
			$this->handleGeneralError(array('error' => array('message' => 'directory {$file} already exists', 'context' => array('file' => $name, 'path' => $dest_path))));
			return false;
		}

		try {
			mkdir($dest_path . $name);
			$this->_Result = array('success' => array(
				'message' => 'Directory {$dir} successfully created in {$path}',
				'context' => array(
					'dir' => $name,
					'path' => substr($dest_path, strlen(FILEMANAGER_WORKSPACE . DIRECTORY_SEPARATOR)),
					'destination' => $dest_path . $name,
					'destination_path' => $dest_path,
					'oo_within' => $_POST['within'],
					'within' => $this->sanitizePathFragment($_POST['within'])
				)
			));
		} catch (Exception $e) {
			$this->handleGeneralError(array('error' => array(
				'message' => 'Failed creating Directory {$dir} in {$path}',
				'context' => array(
					'dir' => $name,
					'path' => substr($dest_path, strlen(FILEMANAGER_WORKSPACE . DIRECTORY_SEPARATOR)),
					'destination' => $dest_path . $name,
					'destination_path' => $dest_path
				)
			)));
			//$this->catchedExceptionHanlder($e);
		}
		return false; 
	}

	private function handleRootDirError($dir) {
		$bn = basename($dir);
		$this->handleGeneralError(array(
			'error' => array(
				'message' => 'can\'t delete or move {$dir}. {$dir2} is used by another field',
				'context' => array(
					'dir' => $bn,
					'dir2' => $bn
				)
			)
		));
		return false;
	}

	private function handleAccessError($dir) {
		$this->handleGeneralError(array(
			'error' => array(
				'message' => 'Cannot access {$file}', 
				'context' => array(
					'file' => basename($dir)
				)
			)
		));
	}

	/**
	 * converts a Exception into a message object
	 * @deprecated
	 */ 
	private function catchedExceptionHanlder(&$exception) {
		$this->handleGeneralError(array('error' => array(
				'message' => $exception->getMessage(), 
				'context' => array(
					'trace' => $exception->getTrace() 
				)
			)
		));
	}

	
	public static function listDirs($dir, $dirs = array()) {
		if (is_dir($dir)) {
			$dirs[] = $dir;
			$objects = scandir($dir);
			foreach($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (is_dir($dir."/".$object)) {
						contentExtensionFilemanagerEdit::listDirs($dir."/".$object, &$dirs);
					}
				}
			}
			reset($objects);
		}
		return $dirs;
	}

	public function scanForRootDirs($dir) {
		if (is_dir($dir)) {
			$dirs = contentExtensionFilemanagerEdit::listDirs($dir);
			$failed = array();
			foreach ($dirs as $d) {
				if ($this->isRoot($d)) {
					$failed[] = $d;	
					break;
				}
			}
			return $failed;
		}
	}	
	/**
	 * recursiv directory deletion
	 * see : http://www.php.net/manual/de/function.rmdir.php#107233
	 */
	public function rrmdir($dir) {
		if (is_dir($dir)) {
			//print_r($this->isRoot($dir) ? 'is root' : 'no root');
			//print_r($dir);
			if ($this->isRoot($dir)) {
				return false;
			}
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") {
						if (!$this->rrmdir($dir."/".$object)) {
							return false;
						}
					} else {
						unlink($dir."/".$object);
					}
				}
			}
			reset($objects);
			return rmdir($dir);
		}
	}

}
