<?php
/* NP_Mediatocu 2.0.0 */

class MEDIADIRS extends MEDIA
{

	function getCollectionList($exceptReadOnly = false,$listmode = 'normal')
	{
		if (!in_array($listmode,array('normal','common','all'))){return;}

		global $DIR_MEDIA;
		
		$collections = array();

		// add global collections
		if (!is_dir($DIR_MEDIA)||!in_array($listmode,array('normal','common','all'))) {
			return $collections;
		}

		$searchDir = '/';

		if ($listmode == 'normal'){
				global $member;
			$prefix = $member->getID();
			if (!MEDIADIRS::checkHiddenDir($prefix)) {
				$collections = MEDIADIRS::traceCorrectionDir($searchDir, $prefix, _MEDIA_PHP_32, $exceptReadOnly);
				// add private directory for member
				if (@is_writable($DIR_MEDIA . $prefix)) {
					$collections[$prefix] = _MEDIA_PHP_32;
				} else if (!$exceptReadOnly) {
					$collections[$prefix] = _MEDIA_PHP_32 . ' ' . _MEDIA_PHP_49;
				}
			}
		}

		$dirhandle = opendir($DIR_MEDIA);
		while ($dirname = readdir($dirhandle)) {
			// only add non-numeric (numeric=private) dirs
			if (($listmode == 'all')||(!is_numeric($dirname))){
				if (@is_dir($DIR_MEDIA . $dirname) && (!MEDIADIRS::checkHiddenDir($dirname))) {
					$prefix = $dirname;
					$collections += (array)MEDIADIRS::traceCorrectionDir($searchDir, $prefix, $dirname, $exceptReadOnly);
					if (@is_writable($DIR_MEDIA . $dirname)) {
						$collections[$dirname] = $dirname;
					} else if (!$exceptReadOnly) {
						$collections[$dirname] = $dirname . ' ' . _MEDIA_PHP_49;
					}
				}
			}
		}
		closedir($dirhandle);

		ksort($collections, SORT_STRING);
		return $collections;
	}

	function traceCorrectionDir($searchDir, $prefix ='', $preName, $exceptReadOnly = false)
	{
		global $DIR_MEDIA;
		$collections = array();		//http://japan.nucleuscms.org/bb/viewtopic.php?p=21230#21230
		$dirhandle   = @opendir($DIR_MEDIA . $prefix . $searchDir);
		if (!$dirhandle) {
			return;
		}
		while ($dirname = readdir($dirhandle)) {
			if (@is_dir($DIR_MEDIA . $prefix . $searchDir . $dirname) && (!MEDIADIRS::checkHiddenDir($dirname))) {
				if (@is_writable($DIR_MEDIA . $prefix . $searchDir . $dirname)) {
					$collections[$prefix . $searchDir . $dirname] = $preName . $searchDir . $dirname;
				} else if (!$exceptReadOnly) {
					$collections[$prefix . $searchDir . $dirname] = $preName . $searchDir . $dirname . ' ' . _MEDIA_PHP_49;
				}
				$collections += (array)MEDIADIRS::traceCorrectionDir($searchDir . $dirname . '/', $prefix, $preName, $exceptReadOnly);
			}
		}
		closedir($dirhandle);

		return $collections;
	}

	function checkHiddenDir($dirname)
	{
		global $manager;
		$mediatocu = $manager->getPlugin('NP_Mediatocu');
		return in_array($dirname,$mediatocu->hiddendir);
	}

	function getMediaListByCollection($collection, $filter = '')
	{
		global $DIR_MEDIA;

		$filelist = array();

		// 1. go through all objects and add them to the filelist

		$mediadir = $DIR_MEDIA . $collection . '/';

		// return if dir does not exist
		if (!is_dir($mediadir)) {
			return $filelist;
		}

		$dirhandle = opendir($mediadir);
		while ($filename = readdir($dirhandle)) {
			// only add files that match the filter
			if (!@is_dir($mediadir . $filename) && MEDIA::checkFilter($filename, $filter)) {
				array_push($filelist, new MEDIAOBJECT($collection, $filename, filemtime($mediadir . $filename)));
			}
		}
		closedir($dirhandle);

		// sort array so newer files are shown first
		usort($filelist, 'sort_media');

		return $filelist;
	}

	/**
	  * checks if a collection exists with the given name, and if it's
	  * allowed for the currently logged in member to upload files to it
	  */
	function isValidCollection($collectionName, $exceptReadOnly = false) {
		global $member, $DIR_MEDIA;

		// allow creating new private directory
		if ($collectionName === (string)$member->getID())
			return true;

		$collections = MEDIADIRS::getCollectionList($exceptReadOnly,'normal');
		$dirname = $collections[$collectionName];
		if ($dirname == NULL || $dirname === _MEDIA_PHP_32)
			return false;  

		// other collections should exist and be writable
		$collectionDir = $DIR_MEDIA . $collectionName;
		if ($exceptReadOnly)
			return (@is_dir($collectionDir) && @is_writable($collectionDir));

		// other collections should exist
		return @is_dir($collectionDir);
	}

	/**
	  * Adds an uploaded file to the media archive
	  *
	  * @param collection
	  *		collection
	  * @param uploadfile
	  *		the postFileInfo(..) array
	  * @param filename
	  *		the filename that should be used to save the file as
	  *		(date prefix should be already added here)
	  */
	function addMediaObject($collection, $uploadfile, $filename) {
		global $DIR_MEDIA, $manager;

		$param = array('collection' => &$collection, 'uploadfile' => $uploadfile, 'filename' => &$filename);
		$manager->notify('PreMediaUpload',$param);

		// don't allow uploads to unknown or forbidden collections
		$exceptReadOnly = true;
		if (!MEDIADIRS::isValidCollection($collection,$exceptReadOnly))
			return _ERROR_DISALLOWED;
		// check dir permissions (try to create dir if it does not exist)
		$mediadir = $DIR_MEDIA . $collection;

		// try to create new private media directories if needed
		if (!@is_dir($mediadir) && is_numeric($collection)) {
			$oldumask = umask(0000);
			if (!@mkdir($mediadir, 0777))
				return _ERROR_BADPERMISSIONS;
			umask($oldumask);
		}

		// if dir still not exists, the action is disallowed
		if (!@is_dir($mediadir))
			return _ERROR_DISALLOWED;

		if (!is_writeable($mediadir))
			return _ERROR_BADPERMISSIONS;

		// add trailing slash (don't add it earlier since it causes mkdir to fail on some systems)
		$mediadir .= '/';

		if (file_exists($mediadir . $filename))
			return _ERROR_UPLOADDUPLICATE;

		// move file to directory
		if (is_uploaded_file($uploadfile)) {
			if (!@move_uploaded_file($uploadfile, $mediadir . $filename))
				return _ERROR_UPLOADMOVEP;
		} else {
			if (!copy($uploadfile, $mediadir . $filename))
				return _ERROR_UPLOADCOPY ;
		}

		// chmod uploaded file
		$oldumask = umask(0000);
		@chmod($mediadir . $filename, 0644);
		umask($oldumask);

		$param = array('collection' => $collection, 'mediadir' => $mediadir, 'filename' => $filename);
		$manager->notify('PostMediaUpload',$param);

		return '';

	}
}

?>