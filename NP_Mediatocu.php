<?php
class NP_Mediatocu extends NucleusPlugin
{

	function getMinNucleusVersion()
	{
		return '340';
	}

	function getName()
	{
		return 'Mediatocu';
	}

	function getAuthor()
	{
		return 'keiei,T.Kosugi,yamamoto,shizuki,Cacher,Mocchi,and Nucleus JP team';
	}

	function getURL()
	{
		return 'http://japan.nucleuscms.org/wiki/plugins:np_mediatocu';
	}

	function getVersion()
	{
		return '1.1.9';
	}

	function getDescription()
	{
		return _MEDIA_PHP_37;
	}

	function supportsFeature($w)
	{
		return ($w == 'SqlTablePrefix') ? 1 : 0;
	}

/**2005.3--2005.09.26 00:50 keiei edit
	* media-tocu3.01.zip  for register_globals=off
	*
	*/
/**
	*	media-tocu3.02.zip
	* T.Kosugi edit 2006.8.22 for security reason
	*/
/**
	*	media-tocu-dirs1.0.zip
	*		extends media-tocu3.02.zip
	* 1.0.7 m17n and making to plugin. by yamamoto
	* 1.0.6 to put it even by the thumbnail image click, small bug.  by yamamoto
	* 1.0.5 to put it even by the thumbnail image click, it remodels it.  by yamamoto
	* 1.0.4 bug fix mkdir if memberdir is missing incase mkdir
	* 1.0.3 bug fix missing memberdir in uploading file.
	* 1.0.2 add checking filname with null
	* 1.0.1 add first dir check
	*
	*/
// add language definition by yamamoto

	function install()
	{
		$mediatocuoptions = array(
			'thumb_width'		 => array('_MEDIA_PHP_17','text','60','datatype=numerical'),
			'thumb_height'		 => array('_MEDIA_PHP_18','text','45','datatype=numerical'),
			'thumb_quality'		 => array('_MEDIA_PHP_19','text','70','datatype=numerical'),
			'hidden_dir'		 => array('_MEDIA_PHP_43','textarea','thumb,thumbnail,phpthumb,.thumbs,np-mediafiles'),
			'def_dir'			 => array('_MEDIA_PHP_DEF_DIR_VALUE','select',_MEDIA_PHP_32,'_MEDIA_COLLECTIONLISTS'),
			'filename_rule'		 => array('_MEDIA_PHP_33','select','default','_MEDIA_PHP_53'),
			'usemembersettings'	 => array('_MEDIA_MEMBER_SETTING','yesno','no'),
		);
		$mediatocuoptions += $this->MemberSpecificOptions();
		foreach ($mediatocuoptions as $key => $value){
			$this->createOption($key,$value[0],$value[1],$value[2],$value[3]);
		}
	}

	function MemberSpecificOptions() {
		return array(
			'paste_mode_checked' => array('_MEDIA_PHP_27','yesno','no'),
			'use_gray_box'		 => array('_MEDIA_PHP_36','yesno','yes'),
			'use_imgpreview'	 => array('_MEDIA_IMGPRV','yesno','yes'),
			'media_per_page'	 => array('_MEDIA_PHP_22','text','9','datatype=numerical'),
			'popup_width'		 => array('_MEDIA_PHP_23','text','500','datatype=numerical'),
			'popup_height'		 => array('_MEDIA_PHP_24','text','450','datatype=numerical'),
		);
	}

	function init()
	{
		global $manager;

		// include language file for this plugin
		$languagefile = preg_replace( '@\\|/@', '', getLanguageName()) . '.php';
		$langdirectory = $this->getDirectory() . 'lang/';
		if (file_exists($langdirectory . $languagefile)) {
			include_once($langdirectory . $languagefile);
		} else {
			include_once($langdirectory . 'english.php');
		}

		$this->Prefix_thumb = "thumb_";
		$this->thumb_w = $this->getOption('thumb_width');
		$this->thumb_h = $this->getOption('thumb_height');
		$this->thumb_quality = $this->getOption('thumb_quality');
		$this->hiddendir =  array('.','..','CVS') + explode(',', $this->getOption('hidden_dir'));
		$this->def_dir = $this->media_getOption('def_dir');
		$this->filename_rule = $this->media_getOption('filename_rule');
		$this->usemembersettings = ($this->getOption('usemembersettings') == 'yes');
		$this->paste_mode_checked = ($this->media_getOption('paste_mode_checked') == "yes");
		$this->use_gray_box = ($this->media_getOption('use_gray_box') == 'yes');
		$this->use_imgpreview = ($this->media_getOption('use_imgpreview') == 'yes');
		$this->media_per_page = $this->media_getOption('media_per_page');
		$this->usetinymce = $manager->pluginInstalled('NP_TinyMCE');
		if ($this->usetinymce) {
			$tinyMCE = $manager->getPlugin('NP_TinyMCE');
					global $member;
			$this->usetinymce = ($tinyMCE->getMemberOption($member->getID(),'use_tinymce') != 'no');
			$tinymcever = $tinyMCE->getVersion();
			$this->tinymcepopupurl = ($tinymcever < '3.3'||$tinymcever == '3.3b1'||$tinymcever == '3.3b2') ? 'jscripts/': 'mce_core/';
			$this->tinymcepopupurl = $tinyMCE->getAdminURL().$this->tinymcepopupurl.'tiny_mce/tiny_mce_popup.js';
		}
	}

	function getEventList()
	{
		return array(
			'AdminPrePageHead',
			'BookmarkletExtraHead',
			'PreSendContentType',
			'PrePluginOptionsEdit',
			'PrePluginOptionsUpdate',
			'PostPluginOptionsUpdate'
		);
	}

	function event_AdminPrePageHead(&$data)
	{
		$action = $data['action'];
		if (($action != 'createitem') && ($action != 'itemedit')) {
			return;
		}
		$this->_getExtraHead($data['extrahead']);
	}

	function event_BookmarkletExtraHead(&$data)
	{
		$this->_getExtraHead($data['extrahead']);
	}

	function event_PreSendContentType(&$data)
	{
		$pageType = $data['pageType'];
		if (	($pageType == 'bookmarklet-add')
			||	($pageType == 'bookmarklet-edit')
			||	($pageType == 'admin-createitem')
			||	($pageType == 'admin-itemedit')
		   ) {
			if ($data['contentType'] == 'application/xhtml+xml') {
				$data['contentType'] = 'text/html';
			}
		}
	}

	function event_PrePluginOptionsEdit($data)
	{
		$thisid = $this->getID();
		if ($data['context'] == 'global') {
			if ($data['plugid'] === $thisid) {
				foreach($data['options'] as $key => $value){
					$description = $value['description'];
					if (defined($description)) {
						$data['options'][$key]['description'] = constant($description);
					}
					if (!strcmp($value['type'], 'select')) {
						$typeinfo = $value['typeinfo'];
						if ($typeinfo == '_MEDIA_COLLECTIONLISTS'){
							global $DIR_LIBS;
							include_once($DIR_LIBS . 'MEDIA.php');
							include_once($this->getDirectory() . 'mediadirs.php');
							$collections = MEDIADIRS::getCollectionList(false,'common');
							$collectionsstr = _MEDIA_PHP_32."|"._MEDIA_PHP_32;
							foreach ($collections as $key2 => $value2){
								$collectionsstr .= "|".$value2."|".$key2;
							}
							$data['options'][$key]['typeinfo'] = $collectionsstr;
						} elseif (defined($typeinfo)) {
							$data['options'][$key]['typeinfo'] = constant($typeinfo);
						}
					}
				}
			}
		} else {
			foreach($data['options'] as $key => $value){
				$description = $value['description'];
				if (($value['pid'] == $thisid) && (defined($description))) {
					$data['options'][$key]['description'] = constant($description);
				}
			}
		}
	}

	function event_PrePluginOptionsUpdate(&$data) {
		if ($data['plugid'] == $this->getID()){
			$value = $data['value'];
			$optionname = $data['optionname'];
			switch($optionname) {
				case 'thumb_width':
				case 'thumb_height':
				case 'thumb_quality':
					if ($value != $this->getOption($optionname)){
						$this->thumbupdate = true;
					}
					break;
				case 'usemembersettings':
					if ($value != $this->getOption($optionname)){
						if($value == 'yes'){
							$this->memsettingupdate = true;
						} else {
							foreach ($this->MemberSpecificOptions() as $key => $value){
								$this->deleteMemberOption('m_'.$key);
							}
						}
					}
					break;
				default:
					break;
			}
		}
	}

	function event_PostPluginOptionsUpdate() {
		if ($this->thumbupdate) {
			global $DIR_LIBS,$DIR_MEDIA;
			include_once($DIR_LIBS . 'MEDIA.php');
			include_once($this->getDirectory() . 'mediadirs.php');
			$collections = MEDIADIRS::getCollectionList(false,'all');
			foreach ($collections as $value){
				$medialist = MEDIADIRS::getMediaListByCollection($value);
				for ($i=0;$i<sizeof($medialist);$i++) {
					$filename = $medialist[$i]->filename;
					if ((strpos($filename,$this->Prefix_thumb) === 0)
					&& (file_exists($DIR_MEDIA.$value. "/".$filename))){
						@unlink($DIR_MEDIA.$value. "/".$filename);
					}
				}
			}
			$this->thumbupdate = false;
		}
		if ($this->memsettingupdate) {
			foreach ($this->MemberSpecificOptions() as $key => $value){
				$this->createMemberOption('m_'.$key,$value[0],$value[1],$this->getOption($key),$value[3]);
			}
			$this->memsettingupdate = false;
		}
	}

	function _getExtraHead(&$extrahead)
	{
		if ($this->usetinymce){
			return;
		}

		global $CONF;
		$mediaPhpURL  = $this->getAdminURL() . 'media.php';
		if ($this->use_gray_box) {
			$gburl = $CONF['PluginURL'] . 'sharedlibs/greybox/';
			$extrahead .= <<<_EXTRAHEAD_

	<link href="{$gburl}gb_styles.css" rel="stylesheet" type="text/css" media="all" />
	<style TYPE="text/css">
		#GB_window td {
			border : none;
			background : url({$gburl}header_bg.gif);
		}
	</style>
	<script type="text/javascript">
		var GB_ROOT_DIR = "{$gburl}";
		function addMedia() {
			GB_showFullScreen('Mediatocu', '{$mediaPhpURL}');
		}
	</script>
	<script type="text/javascript" src="{$gburl}AJS.js"></script>
	<script type="text/javascript" src="{$gburl}AJS_fx.js"></script>
	<script type="text/javascript" src="{$gburl}gb_scripts.js"></script>

_EXTRAHEAD_;
		} else {
			$popup_width  = intVal($this->media_getOption('popup_width'));
			$popup_height = intVal($this->media_getOption('popup_height'));
			$extrahead .= <<<_EXTRAHEAD_

	<script type="text/javascript">
		function addMedia() {
			window.open('{$mediaPhpURL}', "name" , "width=$popup_width , height=$popup_height , scrollbars=yes , resizable=yes" );
		}
	</script>

_EXTRAHEAD_;
		}
	}

	function media_getOption($name) {
		global $member;
		return (($this->usemembersettings) && array_key_exists($name,$this->MemberSpecificOptions()))
			? $this->getMemberOption($member->getID(), 'm_'.$name)
			: $this->getOption($name);
	}

}
?>
