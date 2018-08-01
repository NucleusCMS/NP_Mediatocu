<?php
/* NP_Mediatocu 2.0.0 */
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2002-2009 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 */
/**
 * Media popup window for Nucleus
 *
 * Purpose:
 *   - can be openen from an add-item form or bookmarklet popup
 *   - shows a list of recent files, allowing browsing, search and
 *     upload of new files
 *   - close the popup by selecting a file in the list. The file gets
 *     passed through to the add-item form (linkto, popupimg or inline img)
 *
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) 2002-2009 The Nucleus Group
 * @version $Id: media.php 956 2009-02-26 15:32:28Z shizuki $
 * $NucleusJP: media.php,v 1.8.2.1 2007/09/07 07:36:44 kimitake Exp $
 *
 */

// include all classes and config data
$p = '../../../';
$p = (is_file($p.'config.php') ? $p : $p.'../');
require($p.'config.php');
include($DIR_LIBS . 'MEDIA.php');	// media classes
include('mediadirs.php');	// MEDIADIRS classes

sendContentType('application/xhtml+xml', 'media');

// user needs to be logged in to use this
if (!$member->isLoggedIn()) {
	media_loginAndPassThrough();
	exit;
}

// check if member is on at least one teamlist
$query = 'SELECT * FROM ' . sql_table('team'). ' WHERE tmember=' . $member->getID();
$teams = sql_query($query);
if (media_sql_num_rows($teams) == 0 && !$member->isAdmin()) {
	media_doError(_ERROR_DISALLOWEDUPLOAD);
}
$mediatocu = $manager->getPlugin('NP_Mediatocu');
$Prefix_thumb = $mediatocu->Prefix_thumb;

$targetthumb = postVar('targetthumb');
if ($targetthumb) {
	// Needs a valid ticket
	if (!$manager->checkTicket()) {
		media_doError(_ERROR_BADTICKET);
	}
	// Check if the collection is valid.
	$currentCollection = media_postVar('currentCollection');
	if(!array_key_exists($currentCollection,MEDIADIRS::getCollectionList(false,'normal'))) media_doError(_ERROR_DISALLOWED);
	$mediapath = $DIR_MEDIA . $currentCollection . "/";
	$targetfile = postVar('targetfile');
	switch (postVar('myaction')) {
		case _MEDIA_PHP_1:
			$exist = file_exists($mediapath . $targetfile);
			if ($exist) {
				$msg1 = @media_unlink($mediapath, $targetfile);
				if (!$msg1) {
					print hsc($targetfile . _MEDIA_PHP_2);
				}
			}
			$exist = file_exists($mediapath . $targetthumb);
			if ($exist) {
				$msg2 = @media_unlink($mediapath, $targetthumb);
				if (!$msg2) {
					print hsc($targetthumb . _MEDIA_PHP_2);
				}
			}
			break;
		case _MEDIA_PHP_3:
			// check file type against allowed types
			$newfilename = postVar('newname');
			if (stristr($newfilename, '%00')) {
				media_doError(_MEDIA_PHP_38);
			}
			if (strpos($newfilename,"\0") !== false) {
				media_doError(_MEDIA_PHP_38);
			}
			if (strpos($newfilename,$Prefix_thumb) === 0) {
				media_doError(_MEDIA_PHP_44.$Prefix_thumb._MEDIA_PHP_45);
			}
			$ok = 0;
			$allowedtypes = explode (',', $CONF['AllowedTypes']);
			foreach ($allowedtypes as $type) {
				if (preg_match('/\.' . $type . '$/i', $newfilename)) {
					$ok = 1;
				}
			}
			if (preg_match('/\.php$/i', $newfilename)) {
				$ok = 0;
			}
			if (!$ok) {
				media_doError(_ERROR_BADFILETYPE);
			}
			if (file_exists($mediapath . $newfilename)) {
				media_doError(_MEDIA_PHP_46);
			}
			$exist = file_exists($mediapath . $targetfile);
			if ($exist) {
				$msg1 = @media_rename($mediapath, $targetfile, hsc($newfilename) );
				if (!$msg1) {
					print hsc($targetfile . _MEDIA_PHP_10);
				}
			}
			$exist = file_exists($mediapath . $targetthumb);
			if ($exist) {
				$msg2 = @media_rename($mediapath,$targetthumb, $Prefix_thumb . $newfilename);
				if (!$msg2) {
					print hsc($targetthumb . _MEDIA_PHP_10);
				}
			}
			break;
	}
}

// get action
$action = requestVar('action');
if ($action == '') {
	$action = 'selectmedia';
}

// check ticket
$aActionsNotToCheck = array('selectmedia', _MEDIA_FILTER_APPLY, _MEDIA_COLLECTION_SELECT);
if (!in_array($action, $aActionsNotToCheck)) {
	if (!$manager->checkTicket()) {
		media_doError(_ERROR_BADTICKET);
	}
}

switch($action) {
	case _UPLOAD_BUTTON:
	case _MEDIA_PHP_PASTENOW_BUTTON:
		if (!$member->isAdmin() and $CONF['AllowUpload'] != true) {
			media_doError(_ERROR_DISALLOWED);
		} else {
			media_upload($action);
		}
		break;
	case _MEDIA_FILTER_APPLY:
	case 'selectmedia':
	case _MEDIA_COLLECTION_SELECT:
		media_select();
		break;
	case _MEDIA_PHP_ACTION_DIR:
	case _MEDIA_PHP_ACTION_MKDIR:
	case _MEDIA_PHP_ACTION_RMDIR:
	case 'rmdir':
	case 'mkdir':
		media_mkdir($action);
		break;
	default:
		media_doError(_ERROR_DISALLOWED);
		break;
}

// select a file
//function media_select($currentCollection = '')
function media_select()
{
	global $member, $CONF, $DIR_MEDIA, $manager,$mediatocu;
	$Prefix_thumb = $mediatocu->Prefix_thumb;
	$MediaPerPage = $mediatocu->media_per_page;
	$usetinymce = $mediatocu->usetinymce;

	// get collection list
	$collections = MEDIADIRS::getCollectionList(false,'normal');

	// currently selected collection
	$currentCollection = media_requestVar('collection','acceptnull');
	if (postVar('currentCollection')) {
		$currentCollection = media_postVar('currentCollection');
	}
	if (!$currentCollection || !@is_dir($DIR_MEDIA . $currentCollection)) {
		$defdir = $mediatocu->def_dir;
		if ($defdir != _MEDIA_PHP_32 && is_dir($DIR_MEDIA . $defdir)) {
			$currentCollection = $defdir;
		} else {
			$currentCollection = key($collections);
			if ($currentCollection == $member->getID()) $currentCollection = $member->getID();
		}
	}

	// avoid directory travarsal and accessing invalid directory
	if (!MEDIADIRS::isValidCollection($currentCollection)) media_doError(_ERROR_DISALLOWED);
	if(!array_key_exists($currentCollection, $collections)) media_doError(_ERROR_DISALLOWED);

	$filter = requestVar('filter');
	$offset = intRequestVar('offset');
	$arr = MEDIADIRS::getMediaListByCollection($currentCollection, $filter);

	$typeradio = ($usetinymce) ? '0' : requestVar('typeradio');//not int
	if (is_null($typeradio)){
		$typeradio = (int)($mediatocu->paste_mode_checked);
	}
	switch($typeradio){
		case '1':
			$paste_mode_popup_checked = 'checked="checked"';
			$paste_mode_normal_checked = '';
			break;
		case '0':
			$paste_mode_normal_checked = 'checked="checked"';
			$paste_mode_popup_checked = '';
			break;
		default:
			media_doError(_ERROR_DISALLOWED);
			break;
	}

	media_head($typeradio);
?>
		<form method="post" enctype="multipart/form-data" action="media.php" name="mainform" id="mainform">
		<table summary="mainform">
			<tr>
				<th><label for="media_collection"><?php echo hsc(_MEDIA_COLLECTION_LABEL)?></label></th>
				<td><select name="collection" id="media_collection" onchange="return form.submit()">
<?php
	foreach ($collections as $dirname => $description) {
		echo "\t\t\t\t\t<option value=\"",hsc($dirname),'"';
		if ((string)$dirname === $currentCollection) {
			echo ' selected="selected"';
		}
		echo '>',hsc($description),"</option>\n";
	}
?>
				</select></td>
				<td><input type="submit" name="action" value="<?php echo hsc(_MEDIA_PHP_ACTION_DIR) ?>" title="<?php echo hsc(_MEDIA_PHP_ACTION_DIR_TT) ?>" /></td>
			</tr>
			<tr>
				<th><label for="uploadfile"><?php echo hsc(_MEDIA_PHP_50)?></label></th>
				<td><input name="uploadfile" id="uploadfile" type="file" /></td>
				<td><input type="submit" name="action" value="<?php echo hsc(_UPLOAD_BUTTON); ?>" /></td>
			</tr>
			<tr>
				<th><?php echo hsc(_MEDIA_PHP_11) ?></th>
				<td>
<?php
		if ($usetinymce){
?>
					<input id="typeradio0" type="radio" class="radio" name="typeradio" value="0" checked="checked" />
					<label for="typeradio0"><?php echo hsc(_MEDIA_INLINE);?></label>
					<input id="typeradio1" type="radio" class="radio" name="typeradio" value="1" disabled="disabled" />
					<label for="typeradio1"><?php echo hsc(_MEDIA_POPUP); ?></label>
<?php
		}else{
?>
					<input id="typeradio0" type="radio" class="radio" name="typeradio" onclick="setType(0);" onkeypress="setType(0);" value="0" <?php echo $paste_mode_normal_checked; ?> />
					<label for="typeradio0"><?php echo hsc(_MEDIA_INLINE);?></label>
					<input id="typeradio1" type="radio" class="radio" name="typeradio" onclick="setType(1);" onkeypress="setType(1);" value="1" <?php echo $paste_mode_popup_checked; ?> />
					<label for="typeradio1"><?php echo hsc(_MEDIA_POPUP); ?></label>
<?php
		}
?>
				</td>
				<td><input type="submit" name="action" value="<?php echo hsc(_MEDIA_PHP_PASTENOW_BUTTON); ?>" /></td>
			</tr>
			<tr>
				<th><label for="media_filter"><?php echo hsc(_MEDIA_FILTER_LABEL)?></label></th>
				<td><input id="media_filter" type="text" name="filter" value="<?php echo hsc($filter)?>" /></td>
				<td>
					<input type="submit" name="action" value="<?php echo hsc(_MEDIA_FILTER_APPLY) ?>" />
					<?php $manager->addTicketHidden() ?> 
					<input type="hidden" name="offset" id="offset" value="" />
				</td>
			</tr>
		</table>
		</form>
		<p class="navi">
		<span class='left'>

<?php
		$contents = array();
		/*The numbers of contents except the thumbnail image are requested. */
		for ($i=0;$i<sizeof($arr);$i++) {
			$obj = $arr[$i];
			if (strpos($obj->filename,$Prefix_thumb) !== 0) {
				$contents[] = $obj;
			}
		}
		$conts_count = sizeof($contents);
	if ($conts_count>0) {
		if ($conts_count < $MediaPerPage) {
			$maxpage = 1;
		} else {
			$maxpage = ceil($conts_count/$MediaPerPage);
		}

		if ($offset==0) {
			$offset=1;
		}
		$idxStart = $offset;
		$idxEnd   = $idxStart * $MediaPerPage;
		if ($idxEnd > $conts_count) {
			$idxEnd = $conts_count;
		}
		if ($idxEnd < 1) {
			$idxEnd = $MediaPerPage;
		}
		$idxNext = ($idxStart-1) * $MediaPerPage;
		if ($idxNext < 0) {
			$idxNext = 0;
		}
	} else {
		$idxStart = $idxEnd = $idxNext = $page = $maxpage = 0;
	}

	if ($idxStart > 0 && $idxNext > 0) {
		$page = ($idxStart-1);
		$pageswitch = "\t\t\t<a href=\"media.php?offset=$page&amp;typeradio=$typeradio&amp;collection=". urlencode($currentCollection)."&amp;filter=" . urlencode($filter)."\" onclick=\"return pageset($page)\" onkeypress=\"return pageset($page)\">" . hsc(_MEDIA_PHP_29) . "</a>\n";
	} else {
		$pageswitch = "";
	}
	if ($idxStart < $maxpage) {
		$page = ($idxStart+1);
		$pageswitch .= "\t\t\t<a href=\"media.php?offset=$page&amp;typeradio=$typeradio&amp;collection=". urlencode($currentCollection)."&amp;filter=" . urlencode($filter)."\" onclick=\"return pageset($page)\" onkeypress=\"return pageset($page)\">" . hsc(_MEDIA_PHP_28) . "</a>\n";
	}
	echo $pageswitch;
	echo "\t\t</span>\n\t\t<span class='right'>".hsc(_MEDIA_PHP_6 . $conts_count)  . "</span><span>". intVal($idxNext+1) . " - " . hsc($idxEnd . _MEDIA_PHP_7)."</span></p>\n\t\t<!--/form-->\n";
	if ($conts_count>0) {
		// Get ticket
		$ticket=$manager->addTicketToUrl('');
		$hscTicket=hsc(preg_replace('/^.*=/','',$ticket));
		for ($i=$idxNext;$i<$idxEnd;$i++) {
			$filename = $DIR_MEDIA . $currentCollection . '/' . $contents[$i]->filename;
			$targetfile = $contents[$i]->filename;
			$size       = @GetImageSize($filename);
			$intWidth   = intval($size[0]);
			$intHeight  = intval($size[1]);
			$filetype   = $size[2];
			$encCurrentCollection = preg_replace('/%2f/i','/',rawurlencode($currentCollection));
			$encFileName = rawurlencode($contents[$i]->filename);
			// strings for javascript
			$jsCurrentCollection = str_replace("'","\\'",$encCurrentCollection);
			$jsFileName = str_replace("'","\\'",$encFileName);
			$targetfile = $contents[$i]->filename;
			$mediapath = $DIR_MEDIA . $currentCollection."/";
			$thumb_file = $Prefix_thumb . $targetfile;
			$thumb_targetfile = $thumb_file;
			$encthumb_file = rawurlencode($thumb_file);
			$thumb_exist = file_exists($mediapath . $thumb_file);
			/*Thumbnail*/
			$hscJsCC = hsc($jsCurrentCollection);
			$hscTGTF = hsc($targetfile);
			$hscTTGT = hsc($thumb_targetfile);
			$hscJsFN = hsc($jsFileName);
			$hscencFN = hsc($encFileName);
			$hscCCol = hsc($currentCollection);
			$hscencCCol = hsc($encCurrentCollection);
			$hscThFN = hsc($thumb_file);
			$hscencThFN = hsc($encthumb_file);
			$hscMEDA = hsc($CONF['MediaURL']);
			$hscMVEW = hsc(_MEDIA_VIEW);
			$hscMVTT = hsc(_MEDIA_VIEW_TT);
			$hscMedia26 = hsc(_MEDIA_PHP_26);
			echo "\t\t<div class='box'>\n";
			if ($filetype != 0 && !$thumb_exist) {
				// image (gif/jpg/png/swf)
				make_thumbnail($DIR_MEDIA, $currentCollection, $filename, $targetfile,$size);
				$thumb_exist = file_exists($mediapath . $thumb_file);
			}
			if ($thumb_exist) {
				echo <<<_DIVTHUMB_
			<div class="tmb"><a href="media.php" onclick="chooseImage('{$hscJsCC}', '{$hscJsFN}', '{$intWidth}', '{$intHeight}')" onkeypress="chooseImage('{$hscJsCC}', '{$hscJsFN}', '{$intWidth}', '{$intHeight}')" title="{$hscTGTF}"><img src="{$hscMEDA}{$hscencCCol}/{$hscencThFN}" alt="{$hscTGTF}" /></a></div>

_DIVTHUMB_;
			} else {
				// When you do not make the thumbnail with mpg and wmv, etc.
				echo "\t\t\t<div class=\"media\">".hsc(strtoupper(pathinfo($filename,PATHINFO_EXTENSION)))."</div>\n";
			}
			echo "\t\t\t";
			if ($intWidth||$intHeight){
				echo $intWidth . ' x ' . $intHeight;
			}
			echo "<br />\n\t\t\t" . number_format(filesize($filename)/1024, 1)." KB<br />\n\t\t\t"
				. date("Y-m-d", $contents[$i]->timestamp) . "<br class=\"clear\" />\n";
			if ($filetype != 0) {
				// image (gif/jpg/png/swf)
			echo <<<_MEDIAPREVIEW_
			<a href="media.php" onclick="chooseImage('{$hscJsCC}', '{$hscJsFN}', '{$intWidth}', '{$intHeight}')" onkeypress="chooseImage('{$hscJsCC}', '{$hscJsFN}', '{$intWidth}', '{$intHeight}')" title="{$hscTGTF}">{$hscMedia26}</a>
(<a href="{$hscMEDA}{$hscencCCol}/{$hscencFN}" target="preview" title="{$hscMVTT}{$hscTGTF}" class="imageLink">{$hscMVEW}</a>)
_MEDIAPREVIEW_;
			} else {
			// not image (e.g. mpg)
				echo <<<_MEDIAFILE_
			<a href="media.php" onclick="chooseOther('{$hscJsCC}', '{$hscJsFN}')" onkeypress="chooseOther('{$hscJsCC}', '{$hscJsFN}')" title="{$hscTGTF}">{$hscMedia26}</a>
			(<a href="{$hscMEDA}{$hscencCCol}/{$hscencFN}" target="preview" title="{$hscMVTT}{$hscTGTF}">{$hscMVEW}</a>)

_MEDIAFILE_;
			}
			$hscMedia01 = hsc(_MEDIA_PHP_1);
			$hscMedia03 = hsc(_MEDIA_PHP_3);
			$hscMedia04 = hsc(_MEDIA_PHP_4);
			echo <<<_FORMBLOCK_
			<form method="post" action="media.php">
				<div>
					<input type="hidden" name="ticket" value="{$hscTicket}" />
					<input type="hidden" name="currentCollection" value="{$hscCCol}" />
					<input type="hidden" name="offset" value="{$offset}" />
					<input type="hidden" name="targetfile" value="{$hscTGTF}" />
					<input type="hidden" name="targetthumb" value="{$hscTTGT}" />
					<input type="text" name="newname" class="newname" value="{$hscTGTF}" /><br />
					<input type="submit" name="myaction" value="{$hscMedia03}" title="{$hscMedia04}" onclick="return kakunin(this.value)" onkeypress="return kakunin(this.value)" />
					<input type="submit" name="myaction" value="{$hscMedia01}" onclick="return kakunin(this.value)" onkeypress="return kakunin(this.value)" />
				</div>
			</form>
		</div>

_FORMBLOCK_;
		}
	} // if ($conts_count>0)
	echo "\t\t<div class=\"clear\"><hr class=\"hyde\" /></div>\n";
	media_foot();
}

/**
  * accepts a file for upload
  */
function media_upload($action)
{
	global $DIR_MEDIA, $CONF, $manager,$mediatocu;
	$uploadInfo   = postFileInfo('uploadfile');

	$filename = hsc($uploadInfo['name']);

	$filename  = preg_replace('/%2f/i','/',$filename );
	$filetype     = $uploadInfo['type'];
	$filesize     = $uploadInfo['size'];
	$filetempname = $uploadInfo['tmp_name'];
	$fileerror    = intval($uploadInfo['error']);
	$Prefix_thumb = $mediatocu->Prefix_thumb;
	if ($mediatocu->filename_rule == "ascii") {
		$path_parts = pathinfo($filename);
		$filename   = date('Y-m-d-H-i-s') . '.' . $path_parts['extension'];
	}
	$typeradio = intPostVar('typeradio');

	switch ($fileerror) {
		case 0: // = UPLOAD_ERR_OK
			break;
		case 1: // = UPLOAD_ERR_INI_SIZE
		case 2: // = UPLOAD_ERR_FORM_SIZE
			media_doError(_ERROR_FILE_TOO_BIG);
			break;
		case 3: // = UPLOAD_ERR_PARTIAL
		case 4: // = UPLOAD_ERR_NO_FILE
		case 6: // = UPLOAD_ERR_NO_TMP_DIR
		case 7: // = UPLOAD_ERR_CANT_WRITE
		default:
			// include error code for debugging
			// (see http://www.php.net/manual/en/features.file-upload.errors.php)
			media_doError(_ERROR_BADREQUEST . ' (' . $fileerror . ')');
			break;
	}
	if (stristr($filename, '%00')) {
		media_doError(_MEDIA_PHP_38);
	}
	if (strpos($filename,"\0") !== false) {
		media_doError(_MEDIA_PHP_38);
	}
	if (strpos($filename,$Prefix_thumb) === 0) {
		media_doError(_MEDIA_PHP_44.$Prefix_thumb._MEDIA_PHP_45);
	}
	if ($filesize > $CONF['MaxUploadSize']) {
		media_doError(_ERROR_FILE_TOO_BIG);
	}

	// check file type against allowed types
	$ok           = 0;
	$allowedtypes = explode (',', $CONF['AllowedTypes']);
	foreach ( $allowedtypes as $type ) {
		if (preg_match('/\.' .$type. '$/i',$filename)) {
			$ok = 1;
		}
	}
	if (!$ok) {
		media_doError(_ERROR_BADFILETYPE);
	}

	if (!is_uploaded_file($filetempname)) {
		media_doError(_ERROR_BADREQUEST);
	}

	// prefix filename with current date (YYYY-MM-DD-)
	// this to avoid nameclashes
	if ($CONF['MediaPrefix']) {
		$filename = strftime("%Y%m%d-", time()) . $filename;
	}

	// Filename should not contain '/' or '\'.
	if (preg_match('#(/|\\\\)#',$filename)) media_doError(_ERROR_DISALLOWED);

	$collection = media_requestVar('collection');
	if(!array_key_exists($collection,MEDIADIRS::getCollectionList(false,'normal'))) media_doError(_ERROR_DISALLOWED);
	$res        = MEDIADIRS::addMediaObject($collection, $filetempname, $filename);

	if ($res != '') {
		media_doError($res);
	}
	$uppath = $DIR_MEDIA.$collection . "/";
	$upfile = $DIR_MEDIA.$collection . "/" . $filename;

	$res    = move_uploaded_file($filetempname, $upfile);
	if ($res != '') {
	  media_doError($res);
	}

	if ($action == _MEDIA_PHP_PASTENOW_BUTTON){
		$size = @getimagesize($upfile);
		media_pastenow($collection,$filename,$size[0],$size[1],$size[2],$typeradio);
	} else {
		// shows updated list afterwards
		redirect("media.php?collection=$collection&typeradio=$typeradio");
	}
}
/**
  * accepts a dirname for mkdir
  *
  */
function media_mkdir($action)
{
	global $DIR_MEDIA, $member, $CONF, $manager;
	$typeradio = intPostVar('typeradio');
	if ($action == _MEDIA_PHP_ACTION_MKDIR || $action =='mkdir' ) {
		$current   = media_requestVar('mkdir_collection');
		if(!array_key_exists($current,MEDIADIRS::getCollectionList(false,'normal'))) media_doError(_ERROR_DISALLOWED);
		$mkdirname = postVar('mkdirname');
		if (!($mkdirname && $current)) {
			redirect("media.php?collection=$current&typeradio=$typeradio");
			return;
		}
		// Create member's directory if not exists.
		if (is_numeric($current) && $current==$member->getID() && !is_dir($DIR_MEDIA . '/' . $current)) {
			$oldumask = umask(0000);
			if (!@mkdir($DIR_MEDIA. '/' . $current, 0777)) {
				return _ERROR_BADPERMISSIONS;
			}
			umask($oldumask);
		}
		// Check if valid directory.
		$path      = $current . '/' . $mkdirname ;
		$path      = str_replace('\\','/',$path); // Avoid using "\" in Windows.
		$pathArray = explode('/', $path);
		if (is_numeric($pathArray[0]) && $pathArray[0] !== $member->getID()) {
			media_doError(_MEDIA_PHP_39 . $pathArray[0] . ':' . $member->getID());
		}
		if (in_array('..', $pathArray)) {
			media_doError(_MEDIA_PHP_40);
		}
		// OK. Let's go.
		if (MEDIADIRS::checkHiddenDir($mkdirname)) {
			media_doError(_MEDIA_PHP_48);
		}
		if (@is_dir($DIR_MEDIA . '/' . $current . '/' . $mkdirname)) {
			media_doError(_MEDIA_PHP_47);
		}
		if (is_dir($DIR_MEDIA . '/' . $current)) {
			$res = @mkdir($DIR_MEDIA . '/' . $current . '/' . $mkdirname);
			$res .= @chmod($DIR_MEDIA . '/' . $current . '/' . $mkdirname , 0777);
		}
		if (!$res) {
			media_doError(_MEDIA_PHP_41 . $res );
		}
		// shows updated list afterwards
		redirect("media.php?collection=$current/$mkdirname&typeradio=$typeradio");
	} elseif($action == _MEDIA_PHP_ACTION_RMDIR ||
			 $action == 'rmdir') {
		$rmdir_collection = media_postVar('rmdir_collection');
		if(!array_key_exists($rmdir_collection,MEDIADIRS::getCollectionList(false,'normal'))||array_key_exists($rmdir_collection,MEDIA::getCollectionList())) media_doError(_ERROR_DISALLOWED);
		$pathArray        = explode('/', $rmdir_collection);
		if (is_numeric($pathArray[0]) && $pathArray[0] !== $member->getID()) {
			media_doError(_MEDIA_PHP_39 . $pathArray[0] . ':' . $member->getID());
		}
		if (in_array('..', $pathArray)) {
			media_doError(_MEDIA_PHP_40);
		}
		$res   = @media_rmdir($DIR_MEDIA,$rmdir_collection);
		if ($res) {
			redirect("media.php?typeradio=$typeradio");
		} else {
			media_doError(_MEDIA_PHP_42);
		}
	} else {
		$current     = media_requestVar('collection');
		$exceptReadOnly = true;
		$collections = MEDIADIRS::getCollectionList($exceptReadOnly,'normal');
		if(!array_key_exists($current,MEDIADIRS::getCollectionList(false,'normal'))) media_doError(_ERROR_DISALLOWED);

		media_head();

		if (sizeof($collections) > 0) {
?>
		<h1><?php echo hsc(_MEDIA_MKDIR_TITLE); ?></h1>
		<p><?php echo hsc(_MEDIA_MKDIR_MSG); ?></p>
		<form method="post" action="media.php">
			<table summary="mkdir">
			<tr>
				<th><label for="mkdirname"><?php echo hsc(_MEDIA_PHP_51); ?></label></th>
				<td colspan="2"><input name="mkdirname" id="mkdirname" type="text" size="40" value="" />
				<input type="hidden" name="action" value="<?php echo hsc(_MEDIA_PHP_ACTION_MKDIR); ?>" />
				<input type="hidden" name="typeradio" value="<?php echo hsc($typeradio); ?>" />
				<?php $manager->addTicketHidden() ?></td>
			</tr>
			<tr>
				<th><label for="mkdir_collection"><?php echo hsc(_MEDIA_PHP_52); ?></label></th>
				<td><select name="mkdir_collection" id="mkdir_collection">
<?php
			foreach ($collections as $dirname => $description) {
				echo "\t\t\t\t\t".'<option value="',hsc($dirname),'"';
				if ((string)$dirname === $current) {
					echo ' selected="selected"';
				}
				echo '>' . hsc($description) . "</option>\n";
			}
?>
				</select></td>
				<td><input type="submit" value="<?php echo hsc(_MEDIA_MKDIR_BUTTON); ?>" /></td>
			</tr>
			</table>
		</form>
<?php
		}
		if (sizeof($collections) > 1) {
?>
		<h1><?php echo hsc(_MEDIA_RMDIR_TITLE); ?></h1>
		<p><?php echo hsc(_MEDIA_RMDIR_MSG); ?></p>
		<form method="post" action="media.php">
			<table summary="rmdir">
			<tr>
				<th><input type="hidden" name="action" value="<?php echo hsc(_MEDIA_PHP_ACTION_RMDIR); ?>" />
				<input type="hidden" name="typeradio" value="<?php echo hsc($typeradio); ?>" />
				<label for="rmdir_collection"><?php echo hsc(_MEDIA_PHP_52); ?></label></th>
				<td><select name="rmdir_collection" id="rmdir_collection">
<?php
			$basecollections = MEDIA::getCollectionList();
			foreach ($collections as $dirname => $description) {
//				if (is_numeric($dirname)) continue;
				if (array_key_exists($dirname,$basecollections)) continue;
				echo "\t\t\t\t\t".'<option value="',hsc($dirname),'"';
				if ((string)$dirname === $current) {
					echo ' selected="selected"';
				}
				echo '>',hsc($description),"</option>\n";
			}
?>
				</select></td>
				<td><?php $manager->addTicketHidden() ?> 
				<input type="submit" value="<?php echo hsc(_MEDIA_RMDIR_BUTTON); ?>" /></td>
			</tr>
			</table>
		</form>
<?php
		}
		media_back($current);
		media_foot();
	}
}

function make_thumbnail($DIR_MEDIA, $collection, $upfile, $filename,$size)
{

	global $manager,$mediatocu;

	// Avoid directory traversal
	media_checkFile($DIR_MEDIA,$collection);

	// Thumbnail image size specification

	$thumb_w    = intVal($mediatocu->thumb_w);
	$thumb_h    = intVal($mediatocu->thumb_h);
	$quality    = intVal($mediatocu->thumb_quality);
	$Prefix_thumb = $mediatocu->Prefix_thumb;

	// Thumbnail filename should not contain '/' or '\'.
	if (preg_match('#(/|\\\\)#',$Prefix_thumb.$filename)) media_doError(_ERROR_DISALLOWED);

    $thumb_file = "{$DIR_MEDIA}{$collection}/{$Prefix_thumb}{$filename}";
    // Resize rate
    $moto_w = $size[0];
    $moto_h = $size[1];
    if ($moto_w > $thumb_w || $moto_h > $thumb_h) {
      $ritu_w = $thumb_w /$moto_w;
      $ritu_h = $thumb_h /$moto_h;
      ($ritu_w < $ritu_h) ? $cv_ritu = $ritu_w : $cv_ritu = $ritu_h;
      $w = ceil($moto_w * $cv_ritu);
      $h = ceil($moto_h * $cv_ritu);
    }

    if ($w && $h) {
      // Making preservation of thumbnail image
      thumb_gd($upfile, $thumb_file, $w, $h, $size, $quality); //GD version
    } else {
      //There is no necessity about the resize. 
      thumb_gd($upfile, $thumb_file, $moto_w, $moto_h, $size, $quality); //GD version
    }
}

//Thumbnail making(GD)
function thumb_gd($fname, $thumbfile, $out_w, $out_h, $size, $quality)
{

	$maxMemorySize = ini_get("memory_limit");
	if($maxMemorySize === '')
		$maxMemorySize = 8388608; // default of PHP,8MB
	else
		$maxMemorySize = intval(str_replace('M', '', $maxMemorySize)) * 1048576; // MB to Bytes

	$expectedMemorySize = ($size[0] * $size[1] * 6) + ($out_w * $out_h * 6);
	$memorySpace = $maxMemorySize - ((function_exists('memory_get_usage')) ? memory_get_usage(true) : 0);

	if($expectedMemorySize > $memorySpace){
		return;}

	switch ($size[2]) {
		case 1 ://.gif
			if (function_exists("ImageCreateFromGif")) {
				$img_in = @ImageCreateFromGIF($fname);
			}
			break;
		case 2 ://.jpg
			$img_in = @ImageCreateFromJPEG($fname);
			break;
		case 3 ://.png
			$img_in = @ImageCreateFromPng($fname);
			break;
		default :
			return;
	}
	if (!$img_in) {
		return;
	}
	$img_out = ImageCreateTrueColor($out_w, $out_h);
	//Former image is copied and the thumbnail is made.
	ImageCopyResized($img_out, $img_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
	//Preservation of thumbnail image
	switch ($size[2]) {
		case 1 ://.gif
			@ImageGif($img_out, $thumbfile);
			break;
		case 2 ://.jpg
			@ImageJpeg($img_out, $thumbfile, $quality);
			break;
		case 3 ://.png
			@ImagePng($img_out, $thumbfile);
			break;
	}
	//The memory that maintains the making image is liberated. 
	imagedestroy($img_in);
	imagedestroy($img_out);
}

function media_pastenow($collection,$filename,$width,$height,$filetype,$type){
	if (!MEDIADIRS::isValidCollection($collection)) media_doError(_ERROR_DISALLOWED);
	if(!array_key_exists($collection,MEDIADIRS::getCollectionList(false,'normal'))) media_doError(_ERROR_DISALLOWED);
	$hscJsCC = hsc(str_replace("'","\\'",preg_replace('/%2f/i','/',rawurlencode($collection))));
	$hscJsFN = hsc(str_replace("'","\\'",rawurlencode($filename)));
	$intWidth = intval($width);
	$intHeight = intval($height);
	$inttype = intval($type);
	if ($filetype) {
		$codestring = "chooseImage('{$hscJsCC}', '{$hscJsFN}', '{$intWidth}', '{$intHeight}')";
	} else {
		$codestring = "chooseOther('{$hscJsCC}', '{$hscJsFN}')";
	}

	media_head($inttype);
	echo <<<_PASTENOW_
	<script type="text/javascript">
		{$codestring};
	</script>
_PASTENOW_;

	media_foot();
	exit;
}

function media_loginAndPassThrough()
{
	global $mediatocu;
	if (!isset($mediatocu) || empty($mediatocu)) {
		$closescript = "window.close();";
	} else if ($mediatocu->usetinymce){
		$closescript = 'tinyMCEPopup.close();';
	} elseif ($mediatocu->use_gray_box) {
		$closescript = 'top.window.GB_hide();';
	} else {
		$closescript = "window.close();";
	}
	media_head();
?>
		<h1><?php echo _LOGIN_PLEASE?></h1>

		<form method="post" action="media.php">
		<div>
			<input name="action" value="login" type="hidden" />
			<input name="collection" value="<?php echo hsc(requestVar('collection')); ?>" type="hidden" />
			<?php echo hsc(_LOGINFORM_NAME); ?> <input name="login" value="" />
			<br /><?php echo hsc(_LOGINFORM_PWD); ?> <input name="password" type="password" />
			<br /><input type="submit" value="<?php echo hsc(_LOGIN); ?>" />
		</div>
		</form>
		<p><a href="media.php" onclick="<?php echo hsc($closescript); ?>" onkeypress="<?php echo hsc($closescript); ?>"><?php echo hsc(_POPUP_CLOSE); ?></a></p>
<?php
	media_foot();
	exit;
}

function media_doError($msg)
{
	if (!headers_sent()) media_head();
?>
	<h1><?php echo hsc(_ERROR); ?></h1>
	<p><?php echo hsc($msg); ?></p>
<?php
	media_back();
	media_foot();
	exit;
}

function media_head($typeradio = NULL)
{
	global $CONF,$mediatocu;
	if (!isset($mediatocu) || empty($mediatocu)) { // maybe not login
		$thumb_w = $thumb_h = 100;
		$setType = 0;
		$GreyBox = 0;
		$use_imgpreview = 0;
		$usetinymce = 0;
	} else {
		$thumb_w = intVal($mediatocu->thumb_w);
		$thumb_h = intVal($mediatocu->thumb_h);
		$setType = intval($typeradio);
		$GreyBox = $mediatocu->use_gray_box;
		$use_imgpreview = $mediatocu->use_imgpreview;
		$usetinymce = $mediatocu->usetinymce;
	}
	echo '<' . '?xml version="1.0" encoding="' . _CHARSET .'"?' . '>' . "\n";
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n";
	echo '<html '._HTML_XML_NAME_SPACE_AND_LANG_CODE.">\n";
?>
	<head>
		<meta http-equiv="Pragma" content="no-cache" />
		<meta http-equiv="Cache-Control" content="no-cache, must-revalidate" />
		<meta http-equiv="Expires" content="-1" />
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo hsc(_CHARSET); ?>" />
		<meta http-equiv="Content-Script-Type" content="text/javascript" />
		<meta http-equiv="Content-Style-Type" content="text/css" />
		<title>Mediatocu</title>
		<link rel="stylesheet" type="text/css" href="popups.css" />
		<script type="text/javascript">
			var type = <?php echo intVal($setType); ?>;
			function setType(val) { type = val; }
			function kakunin(value){
				res=confirm('<?php echo hsc(_MEDIA_PHP_8); ?>'+value+'<?php echo hsc(_MEDIA_PHP_9); ?>');
				return res;
			}
			function pageset(page){
				document.forms['mainform'].elements['offset'].value = page;
				document.forms['mainform'].submit();
				return false;
			}
		</script>
<?php
	if ($use_imgpreview){
?>
		<script type="text/javascript" src="<?php echo $CONF['PluginURL'] ; ?>sharedlibs/jQuery/jquery.js"></script>
		<script type="text/javascript" src="<?php echo $CONF['PluginURL'] ; ?>sharedlibs/jQuery/imgpreview.js"></script>
		<script type="text/javascript">
			jQuery(function(){
				$('.imageLink').imgPreview({
					containerID: 'imgPreviewWithStyles',
					imgCSS: { width: 300 }
				});
			});
		</script>
<?php
	}
	if ($usetinymce){
?>
		<script type="text/javascript" src="<?php echo $mediatocu->tinymcepopupurl; ?>"></script>
		<script type="text/javascript">
			function chooseImage(collection, filename, width, height) {
				var win = tinyMCEPopup.getWindowArg("w_n");
				var file_path = "<?php echo $CONF['MediaURL']; ?>" + collection + "/" + filename;
				win.document.getElementById(tinyMCEPopup.getWindowArg("f_n")).value = file_path;
				if (tinyMCEPopup.getWindowArg("file_type") == "image") {
					if (win.ImageDialog.getImageData) win.ImageDialog.getImageData();
					if (win.ImageDialog.showPreviewImage) win.ImageDialog.showPreviewImage(file_path);
				}
				tinyMCEPopup.close();
			}
			function chooseOther(collection, filename) {
				var win = tinyMCEPopup.getWindowArg("w_n");
				var file_path = "<?php echo $CONF['MediaURL']; ?>" + collection + "/" + filename;
				win.document.getElementById(tinyMCEPopup.getWindowArg("f_n")).value = file_path;
				tinyMCEPopup.close();
			}
		</script>
<?php
	} elseif ($GreyBox) {
?>
		<script type="text/javascript">
			function chooseImage(collection, filename, width, height) {
				top.window.focus();
				top.window.includeImage(
					collection,
					filename,
					type == 0 ? 'inline' : 'popup',
					width,
					height
				);
				top.window.GB_hide();
			}
			function chooseOther(collection, filename) {
				top.window.focus();
				top.window.includeOtherMedia(collection, filename);
				top.window.GB_hide();
			}
		</script>
<?php
	} else {
?>
		<script type="text/javascript">
			function chooseImage(collection, filename, width, height) {
				top.opener.focus();
				top.opener.includeImage(
					collection,
					filename,
					type == 0 ? 'inline' : 'popup',
					width,
					height
				);
				window.close();
			}
			function chooseOther(collection, filename) {
				top.opener.focus();
				top.opener.includeOtherMedia(collection, filename);
				window.close();
			}
		</script>
<?php
	}
?>
		<style type="text/css">
			div.tmb, div.media {
				margin : 0 4px 3px 0;
				padding : 0px;
				width : <?php echo $thumb_w ?>px;
				height : <?php echo $thumb_h ?>px;
				line-height : <?php echo $thumb_h ?>px;
				float : left;
				display : inline;
				border : 1px solid #999;
				text-align : center;
			}
			div.tmb {
				background-image: url("bg.gif");
			}
			div.media {
				background-color: #fff;
			}
			div.tmb a, div.media a {
				width : <?php echo $thumb_w ?>px;
				height : <?php echo $thumb_h ?>px;
				display : block;
			}
		</style>
		<base target="_self" />
	</head>
	<body>
	<div class="wrap">
<?php
}

function media_back($currentCollection=false)
{
	echo "\t\t<p><a href=\"media.php";
	if ($currentCollection){
		echo "?collection=".urlencode($currentCollection);
	}
	echo '">'.hsc(_BACK)."</a></p>\n";
}

function media_foot()
{
?>
	</div>
	</body>
</html>
<?php
}


function media_checkFile($dir,$file,$return=false){
	// Anti direcory-traversal rountine.
	global $DIR_MEDIA,$member;
	// member's directory is OK even if not exists.
	if ($dir==$DIR_MEDIA && is_numeric($file)) return $file==$member->getID();
	// The check fails if file does not exists
	$file=realpath($dir.$file);
	$dir=realpath($dir);
	if (strpos($file,$dir)===0) return true;
	if ($return) return false;
	media_doError(_ERROR_DISALLOWED);
	exit;
}

function media_unlink($dir,$file){
	media_checkFile($dir,$file);
	return unlink($dir.$file);
}

function media_rmdir($dir,$file){
	media_checkFile($dir,$file);
	return rmdir($dir.$file);
}

function media_rename($dir,$file,$newfile){
	media_checkFile($dir,$file);
	if (preg_match('#(/|\\\\)#',$newfile)) media_doError(_ERROR_DISALLOWED);
	return rename($dir.$file, $dir.$newfile);
}

function media_collection($type,$name,$option){
	static $data=array();
	if (!isset($data[$type][$name])) {
		switch($type){
			case 'getVar': case 'postVar': case 'requestVar':
				$temp=call_user_func($type,$name);
				break;
			default:
				exit('Unknown error at line '.__LINE__);
		}
		if (strlen($temp)==0 && $option=='acceptnull') return '';
		$temp = str_replace('\\','/',$temp); // Avoid using "\" in Windows.
		if (!MEDIADIRS::isValidCollection($temp)) media_doError(_ERROR_DISALLOWED);
		$data[$type][$name]=$temp;
	}
	return $data[$type][$name];
}

function media_getVar($name,$option=false){
	return media_collection('getVar',$name,$option);
}
function media_postVar($name,$option=false){
	return media_collection('postVar',$name,$option);
}
function media_requestVar($name,$option=false){
	return media_collection('requestVar',$name,$option);
}

function media_sql_num_rows($query){
	return (function_exists('sql_num_rows')) ? sql_num_rows($query) : mysql_num_rows($query);
}

function media_debug($line=null){
	global $CONF;
	if ($CONF['debug']||$CONF['DebugVars']) {
		global $SQLCount;
		echo "<!-- ";
		if ($line) {
			echo 'line:'.intval($line).' ';
		}
		echo 'queries:'.intval($SQLCount)
		;
		if (function_exists('memory_get_usage')) {
			echo ' memory_get_usage:'.floatval(memory_get_usage(true)/1048576).'M';
		}
		echo " -->\n";
	}
}
?>