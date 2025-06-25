<?php
namespace FreePBX\modules\Filestore\drivers;
class FlysystemBase extends DriverBase {
	protected $handler;

	//Filestore Actions
	/**
	 * Read files
	 * @param  string $path location of a file
	 * @return string file contents
	 */
	public function read($path) {
		return $this->getHandler()->read($path);
	}

	/**
	 * Read files as a stream
	 * @param  string $path location of a file
	 * @return resource file stream
	 */
	public function readStream($path) {
		return $this->getHandler()->readStream($path);
	}

	/**
	 * Write or Update Files
	 * @param string $path location of a file
	 * @param string $contents file contents
	 * @return bool success boolean
	 */
	public function put($path,$contents) {
		return $this->getHandler()->put($path, $contents);
	}

	/**
	 * Write or Update Files using a stream
	 * @param string $path location of a file
	 * @param resource $resource file stream
	 * @return bool success boolean
	 */
	public function putStream($path,$resource) {
		return $this->getHandler()->writeStream($path,$resource);
	}

	/**
	 * List contents of a directory.
	 *
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return array
	 */
	public function listContents($directory = '', $recursive = false) {
		return $this->getHandler()->listContents($directory, $recursive);
	}

	/**
	 * Delete Files
	 * @param  string  $path   location of a file
	 * @return boolean            success boolean
	 */
	public function delete($path) {
		try {
			if(!$this->getHandler()->has($path)) return false;
			$this->getHandler()->delete($path);
			return true;
		} catch (\League\Flysystem\FilesystemException $exception) {
			return false;
		}
	}

	/**
	 * Rename files
	 * @param  string $from location of a file
	 * @param  string $to new location
	 * @return bool  was the operation successful
	 */
	public function rename($from, $to) {
		return $this->getHandler()->rename($from, $to);
	}

	/**
	 * Copy files
	 * @param  string $from location of a file
	 * @param  string $to new location
	 * @return bool  was the operation successful
	 */
	public function copy($from, $to) {
		return $this->getHandler()->copy($from, $to);
	}

	/**
	 * Get Mimetypes
	 *
	 * @param string $path location of a file
	 * @return string mime-type
	 */
	public function getMimetype($path) {
		return $this->getHandler()->getMimetype($path);
	}

	/**
	 * Get Timestamps
	 *
	 * This function returns the last updated timestamp.
	 *
	 * @param string  $path location of a file
	 * @return integer timestamp of modification
	 */
	public function getTimestamps($path) {
		return $this->getHandler()->getTimestamps($path);
	}

	/**
	 * Get File Sizes
	 *
	 * @param string  $path location of a file
	 * @return integer size of a file
	 */
	public function getSize($path) {
		return $this->getHandler()->fileSize($path);
	}

	/**
	 * Create Directories
	 *
	 * Directories are also made implicitly when writing to a deeper path.
	 * In general creating a directory is not required in order to write to it.
	 *
	 * @param string $path location of a file
	 * @return bool  was the operation successful
	 */
	public function createDir($path) {
		return $this->getHandler()->createDirectory($path);
	}

	/**
	 * Delete Directories
	 *
	 * Deleting directories is always done recursively.
	 *
	 * @param string $path location of a file
	 * @return bool  was the operation successful
	 */
	public function deleteDir($path) {
		return $this->getHandler()->deleteDir($path);
	}

	/**
	 * Check if a file exists
	 *
	 * This only has consistent behaviour for files, not directories.
	 * Directories are less important in Flysystem, they’re created
	 * implicitly and often ignored because not every adapter (filesystem type)
	 * supports directories.
	 *
	 * @param string $path location of a file
	 * @return bool whether the file exists
	 */
	public function fileExists($path){
		return $this->getHandler()->fileExists($path);
	}

	public function getHandler(){
		throw new \Exception("Handler not declared!");
	}
}