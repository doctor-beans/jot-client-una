<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup	Messenger Messenger
 * @ingroup		UnaModules
 *
 * @{
 */

/**
 * Module  representation
 */
class BxMessengerTemplate extends BxBaseModNotificationsTemplate
{
	function __construct(&$oConfig, &$oDb)
	{
		parent::__construct($oConfig, $oDb);
	}
	
	/**
	* Attach js and css files for messenger depends on page with messenger block
	*@param string $sMode 
	*/
	public function loadCssJs($sMode = 'all'){
		$aCss = array(
						BX_DIRECTORY_PATH_PLUGINS_PUBLIC . 'emoji/css/|emoji.css', 
						'dropzone.css',
						'semantic.min.css',
						'main.css'
					 );
		$aJs = array(
						'primus.js',
						'connect.js',
						'messenger.js',
						'emoji/js/util.js',
						'emoji/js/config.js',
						'emoji/js/emoji-picker.js',
						'emoji/js/jquery.emojiarea.js',
						'status.js',
						'dropzone.js',
						'feather.min.js',
						'RecordRTC.min.js',
						'adapter.js',
						'record-video.js',
						'semantic.min.js'
					); 
		
		if ($this->_oConfig->CNF['IS_PUSH_ENABLED'] && !getParam('sys_push_app_id'))
			array_push($aJs, 'https://cdn.onesignal.com/sdks/OneSignalSDK.js');
		
		if ($sMode == 'all'){
			array_push($aCss, 'admin.css', 'messenger.css');
			array_push($aJs, 'columns.js');
		}	
	
		$this->addCss($aCss);
		$this->addJs($aJs); 
	}
	
	/**
	* Main function to build post messages area with messages history
	*@param int $iProfileId logged member id
	*@param int $iLotId id of conversation. It can be empty if new talk
	*@param int $iType type of talk (Private, Public and etc..)
	*@param int $iJotId jot id, allows to load history from jot's position  
	*@param string $sEmptyContent  html content which may be added to the center of the talk when there are no messages yet
	*@return string html code 
	*/
	public function getPostBoxWithHistory($iProfileId, $iLotId = BX_IM_EMPTY, $iType = BX_IM_TYPE_PUBLIC, $iJotId = BX_IM_EMPTY, $sEmptyContent = ''){
		$oCNF = $this->_oConfig-> CNF;
		$aVars = $aJots = $aLotInfo = array();
		$aParams = array(
			'content' => $sEmptyContent,
			'id'	  => $iLotId,
			'name'	  => '',
			'user_id' => $iProfileId,
			'type' => $iType,
			'bx_if:post_area' => array(
				'condition' => $iProfileId,
				'content' 	=> array(
					'place_holder' => $oCNF['SERVER_URL'] ? _t('_bx_messenger_post_area_message') : _t('_bx_messenger_server_is_not_installed')
				)
			)
		);
		
		$oProfile = $this -> getObjectUser($aParams['user_id']);
	    if($oProfile)
			$aParams['name'] = bx_js_string($oProfile -> getDisplayName());
		
		$iUnreadLotsJots = $this -> _oDb -> getUnreadJotsMessagesCount($iProfileId, $iLotId);
		if ($iUnreadLotsJots && !$iJotId)
			$iJotId = $this -> _oDb -> getFirstUnreadJot($iProfileId, $iLotId);
		
		$iLeftJots = 0;
		if ($iJotId)
			$iLeftJots = $this -> _oDb -> getLeftJots($iLotId, $iJotId);
		
		$aParams['bx_if:show_scroll_area'] = array(
				'condition' => $iJotId && $iLeftJots > (int)$oCNF['MAX_JOTS_BY_DEFAULT']/2,
				'content' => array(
					'id' => $iLotId,
					'bx_if:check_as_read' => array(
						'condition' => $iUnreadLotsJots,
						'content' => array()
					)
				)
		);
		
		if ($iLotId)
		{
			$aOptions = array(
								'lot_id' => $iLotId,
								'limit' => $oCNF['MAX_JOTS_BY_DEFAULT'],
								'start' => $iJotId,
								'display' => true,
								'select' => true
							 );
						
			$aParams['content'] = $this -> getJotsOfLot($iProfileId, $aOptions);
		}
		
		$aParams['url'] = '';
		if ($iType != BX_IM_TYPE_PRIVATE)
			$aParams['url'] = isset($aLotInfo[$oCNF['FIELD_URL']]) ? $aLotInfo[$oCNF['FIELD_URL']] : $this-> _oConfig -> getPageIdent();
		
		BxDolSession::getInstance()-> exists($iProfileId);
		return $this -> parseHtmlByName('chat_window.html', $aParams);
	}
  
  	/**
	* Main function to build post message block for any page
	*@param int $iProfileId logged member id
	*@param int $iLotId id of conversation. It can be empty if new talk
	*@param int $iJotId id of the message in history
	*@param int $iType type of talk (Private, Public and etc..)
	*@param boolean $bShowMessanger show empty chat window if there is no history
	*@return string html code 
	*/
	public function getTalkBlock($iProfileId, $iLotId = BX_IM_EMPTY, $iJotId = BX_IM_EMPTY, $iType = BX_IM_TYPE_PUBLIC, $bShowMessanger = false, &$sReturnTitle = ''){
		$sTitle = '';
		$aLotInfo = array(); 
		if ($iLotId)
		{
			$aLotInfo = $this -> _oDb -> getLotInfoById($iLotId); 
				if ($this -> _oDb -> isAuthor($iLotId, $iProfileId) || isAdmin())
				{
					$aMenu[] = array('name' => _t("_bx_messenger_lots_menu_add_part"), 'title' => '', 'action' => "oMessenger.createLot({lot:{$iLotId}});");
					$aMenu[] = array('name' => _t("_bx_messenger_lots_menu_delete"), 'title' => '', 'action' => "if (confirm('" . bx_js_string(_t('_bx_messenger_delete_lot')) . "')) oMessenger.onDeleteLot($iLotId);");
				}		
		}
			  
		if (!empty($aLotInfo))
		{
			$iType = $aLotInfo[$this -> _oConfig -> CNF['FIELD_TYPE']];
			$sTitle = isset($aLotInfo[$this -> _oConfig -> CNF['FIELD_TITLE']]) && $aLotInfo[$this -> _oConfig -> CNF['FIELD_TITLE']] ? $aLotInfo[$this -> _oConfig -> CNF['FIELD_TITLE']] : $this -> getParticipantsNames($iProfileId, $iLotId);
			$sTitle = $this -> _oDb -> isLinkedTitle($iType) ? _t('_bx_messenger_linked_title', '<a href ="'. $aLotInfo[$this -> _oConfig -> CNF['FIELD_URL']] .'">' . $sTitle . '</a>') : _t($sTitle);
			$sReturnTitle = html2txt($sTitle);
		}

		$aMenu[] = array('name' => _t("_bx_messenger_lots_menu_leave"), 'title' => '', 'action' => "if (confirm('" . bx_js_string(_t('_bx_messenger_leave_chat_confirm')) . "')) oMessenger.onLeaveLot($iLotId);");
		$iUnreadLotsJots = $bIsMuted = $bIsStarred = 0;		
		if ($iProfileId)
		{
			$iUnreadLotsJots = $this -> _oDb -> getUnreadJotsMessagesCount($iProfileId, $iLotId);
			$bIsMuted = $this -> _oDb -> isMuted($iLotId, $iProfileId);
			$bIsStarred = $this -> _oDb -> isStarred($iLotId, $iProfileId);
		}
		
		return $this -> parseHtmlByName('talk.html', array(
				'bx_repeat:settings' => $aMenu,
				'bx_if:count' => array(
									'condition' => $iUnreadLotsJots,
									'content' => array(
										'back_count' => $iUnreadLotsJots
									)
								),
				'bx_if:show_lot_menu' => array(
									'condition' => $iProfileId,
									'content' => array(
										'id' => $iLotId,
										'mute' => (int)$bIsMuted,
										'mute_title' => bx_js_string( $bIsMuted ? _t('_bx_messenger_lots_menu_mute_info_on') : _t('_bx_messenger_lots_menu_mute_info_off')),
										'settings_title' => _t('_bx_messenger_lots_menu_settings_title'),
										'star_title' => bx_js_string( !$bIsStarred ? _t('_bx_messenger_lots_menu_star_on') : _t('_bx_messenger_lots_menu_star_off')),
										'star' => (int)$bIsStarred,										
										'star_fill_color' => $this -> _oConfig -> CNF['STAR_BACKGROUND_COLOR'],										
										'bell_icon' => $bIsMuted ? $this -> _oConfig -> CNF['BELL_ICON_OFF'] : $this -> _oConfig -> CNF['BELL_ICON_ON'],
										'star_icon' => $this -> _oConfig -> CNF['STAR_ICON'],
									)
								),				
				'back_title' => bx_js_string(_t('_bx_messenger_lots_menu_back_title')),
				'star_color' => $bIsStarred ? $this -> _oConfig -> CNF['STAR_BACKGROUND_COLOR']: 'none',
				'star_icon' => $this -> _oConfig -> CNF['STAR_ICON'],
				'title' => $sTitle,
				'post_area' => !$bShowMessanger && empty($aLotInfo) ?
								MsgBox(_t('_bx_messenger_txt_msg_no_results')) : 
								$this-> getPostBoxWithHistory($iProfileId, $iLotId, $iType, $iJotId, MsgBox(_t('_bx_messenger_what_do_think')))
		));
	}	
	
	/**
	* Create top of the block with participants names and statuses
	*@param int $iProfileId logget member id
	*@param int $iLotId id of conversation. It can be empty if new talk
	*/
	private function getParticipantsNames($iProfileId, $iLotId){
		$aNickNames = array();
		$sCode = '';
		
		$aParticipantsList = $this -> _oDb -> getParticipantsList($iLotId, true, $iProfileId);
		if (empty($aParticipantsList))
			return '';
		
		$iCount = count($aParticipantsList);
		$aParticipantsList = array_slice($aParticipantsList, 0, $this -> _oConfig -> CNF['PARAM_ICONS_NUMBER']); 
	
		if (count($aParticipantsList) == 1)
		{
			$oProfile = $this -> getObjectUser($aParticipantsList[0]);
			
			if ($oProfile)
			{				
				$bOnline = method_exists($oProfile, 'isOnline') ? $oProfile -> isOnline(): false;
				$aNickNames['bx_repeat:users'][] = array(
										'profile_username' => $oProfile -> getUrl(),
										'username' =>  $oProfile -> getDisplayName(),
										'status' => ($bOnline ? 
													$this -> getOnlineStatus($oProfile-> id(), 1):
													$this -> getOnlineStatus($oProfile-> id(), 0))
									  );	
			}
			$sCode = $this -> parseHtmlByName('status_usernames.html', $aNickNames);
		}
		else
		{
			foreach($aParticipantsList as $iParticipant)
			{			
				$oProfile = $this -> getObjectUser($iParticipant);
				if ($oProfile)
					$aNickNames[] = $oProfile->getDisplayName();
			}
			
			$sOthers = $iCount > (int)$this -> _oConfig -> CNF['PARAM_ICONS_NUMBER'] ? _t('_bx_messenger_lot_title_participants_number', $iCount - (int)$this -> _oConfig -> CNF['PARAM_ICONS_NUMBER']) : '';
			
			$sCode = $this -> parseHtmlByName('simple_usernames.html', array('usernames' => implode(', ', $aNickNames) . " {$sOthers}"));
		}
		
		return $sCode;
	}

	/**
	* New conversation area with top and send area (right side block of the main window)
	*@param int $iProfileId logget member id
	*@param int $iLotId id of conversation. It can be empty if new talk
	*@param boolean $bFirstTime create private talk window with participants selector at the top
	*@param int $iJotId use jot it as position in history to display history
	*@return string html code
	*/
	public function getLotWindow($iProfileId = BX_IM_EMPTY, $iLotId = BX_IM_EMPTY, $bFirstTime = false, $iJotId = 0)
	{
		$aProfiles = array();
		$aParticipants = array();
		$iViewer = bx_get_logged_profile_id();
		
		if ($iProfileId)
		{
			$aLot = $this -> _oDb -> getLotByUrlAndPariticipantsList(BX_IM_EMPTY_URL, array($iViewer, $iProfileId));
			$iLotId = empty($aLot) ? BX_IM_EMPTY : $aLot[$this -> _oConfig -> CNF['FIELD_ID']];
			
			$oProfile = $this -> getObjectUser($iProfileId);
			if ($oProfile)
				$aProfiles[] = array(
									'name' => $oProfile -> getDisplayName(),
									'title' => $oProfile -> getDisplayName(),
									'thumb' => $oProfile -> getThumb(),
									'user_id' => $iProfileId
								);
		} 
		else if ($iLotId)
		{
			$aParticipantsList = $this -> _oDb -> getParticipantsList($iLotId);
			foreach($aParticipantsList as $iParticipant){
				if ($iViewer == $iParticipant) continue;
				if ($oProfile = $this -> getObjectUser($iParticipant))
					$aParticipants[] = array(
						'thumb' => $oProfile -> getThumb(),
						'name'	=> $oProfile->getDisplayName(),
						'id'	=> $oProfile-> id()
					);
			}
		}
				
		$aVars = array('bx_if:find_participants' =>	array(
															'condition' => !$bFirstTime && $iProfileId == BX_IM_EMPTY,
															'content' => array(
																'bx_repeat:participants_list' => $aParticipants,
																'back_title' => bx_js_string(_t('_bx_messenger_lots_menu_back_title')),
																'bx_if:edit_mode' => 
																					array(
																						'condition' => ($iLotId && $this -> _oDb -> isAuthor($iLotId, $iViewer)) || isAdmin() || !$iLotId,
																						'content' => array(
																											'lot' => $iLotId,
																										   )
																					),
															)
														),
						'bx_if:user_info' => array(
													'condition' => $iProfileId != BX_IM_EMPTY && !empty($aProfiles),
													'content' => array(
																		'bx_repeat:users' => $aProfiles,
																		'profile_id' => $iProfileId
																		)
													),			
						'chat_area' => !$bFirstTime ? $this -> getPostBoxWithHistory($iProfileId, $iLotId, BX_IM_TYPE_PRIVATE) : '' 
					 );
		
		return $this -> parseHtmlByName('private_chat_window.html', $aVars);
	}
	
	/**
	* Search friends function which shows fiends only if member have no any talks yet
	*@param string $sParam keywords
	*@return string html code
	*/
	function getFriendsList($sParam = ''){
		$iLimit = (int)$this->_oConfig->CNF['PARAM_FRIENDS_NUM_BY_DEFAULT'] ? (int)$this->_oConfig->CNF['PARAM_FRIENDS_NUM_BY_DEFAULT'] : 5;
		
		if (!$sParam)
		{
			bx_import('BxDolConnection');
			$oConnection = BxDolConnection::getObjectInstance('sys_profiles_friends');
			if (!$oConnection)
				return '';
			
			$aFriends = $oConnection -> getConnectionsAsArray ('content', bx_get_logged_profile_id(), 0, false, 0, $iLimit + 1, BX_CONNECTIONS_ORDER_ADDED_DESC);
		} else{
			$aUsers = BxDolService::call('system', 'profiles_search', array($sParam, $iLimit), 'TemplServiceProfiles');
			if (empty($aUsers)) return array();
			
			foreach($aUsers as $iKey => $aValue)
					$aFriends[] = $aValue['value'];
		}			
		
		$aItems['bx_repeat:friends'] = array();
		foreach($aFriends as $iKey => $iValue){
			$oProfile = $this -> getObjectUser($iValue);
			$aItems['bx_repeat:friends'][] = array(	
							'title' => $oProfile -> getDisplayName(),
							'name' => $oProfile -> getDisplayName(),
							'thumb' => $oProfile -> getThumb(),
							'id' => $oProfile -> id(),
					);
		}
 
		return $this -> parseHtmlByName('friends_list.html', $aItems);
	}
	
	/**
	*  List of Lots (left side block content)
	*@param int $iProfileId logget member id
	*@param array $aLots list of lost to show
	*@param boolean $bShowTime display time(last message) in the right side of the lot
	*@return string html code
	*/
	function getLotsPreview($iProfileId, &$aLots, $bShowTime = true){
		$sContent = '';

		foreach($aLots as $iKey => $aLot)
		{
			$aParticipantsList = $this -> _oDb -> getParticipantsList($aLot[$this -> _oConfig -> CNF['FIELD_ID']], true, $iProfileId);
			
			$iParticipantsCount = count($aParticipantsList);
			$aParticipantsList = $iParticipantsCount ? array_slice($aParticipantsList, 0, $this -> _oConfig -> CNF['PARAM_ICONS_NUMBER']) : array($iProfileId);
			
			$aVars['bx_repeat:avatars'] = array();
			$aNickNames = array();
			foreach($aParticipantsList as $iParticipant){
				$oProfile = $this -> getObjectUser($iParticipant);
				if ($oProfile) {
					$aVars['bx_repeat:avatars'][] = array(
						'title' => $oProfile->getDisplayName(),
						'thumb' => $oProfile->getThumb(),
					);
				 
					$aNickNames[] = $oProfile-> getDisplayName();
			      }
			}
			
			if (!empty($aLot[$this -> _oConfig -> CNF['FIELD_TITLE']]))
				$sTitle = _t($aLot[$this -> _oConfig -> CNF['FIELD_TITLE']]);
			else
			{ 
				if ($iParticipantsCount > 3)
					$sTitle = implode(', ', array_slice($aNickNames, 0, $this -> _oConfig -> CNF['PARAM_ICONS_NUMBER'])) . '...';
				else
					$sTitle = implode(', ', $aNickNames);
			}	
			
			$sStatus = '';
			if ($iParticipantsCount == 1 && $oProfile && empty($aLot[$this -> _oConfig -> CNF['FIELD_TITLE']])){
				$sStatus = (method_exists($oProfile, 'isOnline') ? $oProfile -> isOnline() : false) ? 
					$this -> getOnlineStatus($oProfile-> id(), 1) : 
					$this -> getOnlineStatus($oProfile-> id(), 0) ;
			}
			else	
				$sStatus = '<div class="status">' . $iParticipantsCount .'</div>';
	
			
			$aVars[$this -> _oConfig -> CNF['FIELD_ID']] = $aLot[$this -> _oConfig -> CNF['FIELD_ID']];
			$aVars[$this -> _oConfig -> CNF['FIELD_TITLE']] = $sTitle;
			$aVars['status'] = $sStatus;
			
			$aLatestJots = $this -> _oDb -> getLatestJot($aLot[$this -> _oConfig -> CNF['FIELD_ID']]);
			
			$iTime = bx_time_js($aLot[$this -> _oConfig -> CNF['FIELD_ADDED']], BX_FORMAT_DATE);
			
			$aVars[$this -> _oConfig -> CNF['FIELD_MESSAGE']] = $aVars['sender_username'] = '';
			if (!empty($aLatestJots))
			{
				$sMessage = '';
				if (isset($aLatestJots[$this -> _oConfig -> CNF['FIELD_MESSAGE']]))
				{
					$sMessage = $aLatestJots[$this -> _oConfig -> CNF['FIELD_MESSAGE']];
					
					if ($aLatestJots[$this->_oConfig->CNF['FIELD_MESSAGE_AT_TYPE']] == BX_ATT_TYPE_REPOST)
					{
						$sMessage = $this -> _oConfig -> cleanRepostLinks($sMessage, $aLatestJots[$this->_oConfig->CNF['FIELD_MESSAGE_AT']]);
						$sMessage = $sMessage ? $sMessage : _t('_bx_messenger_repost_message');
					}
					
					$sMessage = BxTemplFunctions::getInstance()->getStringWithLimitedLength($sMessage, $this->_oConfig-> CNF['MAX_PREV_JOTS_SYMBOLS']);
				}
				
				if (!$sMessage && $aLatestJots[$this -> _oConfig -> CNF['FIELD_MESSAGE_AT_TYPE']] == BX_ATT_TYPE_FILES)
					$sMessage = _t('_bx_messenger_attached_files_message', $this -> _oDb -> getJotFiles($aLatestJots[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']], true));

				$aVars[$this -> _oConfig -> CNF['FIELD_MESSAGE']] = $sMessage;
				if ($oSender = $this -> getObjectUser($aLatestJots[$this -> _oConfig -> CNF['FIELD_MESSAGE_AUTHOR']]))
				{
	
					$aVars['sender_username'] = $oSender -> id() == $iProfileId ? _t('_bx_messenger_you_username_title') : $oSender -> getDisplayName();
					$aVars['sender_username'] .= ':';
				}
				
				$iTime = bx_time_js($aLatestJots[$this -> _oConfig -> CNF['FIELD_MESSAGE_ADDED']], BX_FORMAT_DATE);
			}
			
			$aVars['class'] = (int)$aLot['unread_num'] ? 'unread-lot' : '';
			$aVars['bubble_class'] = (int)$aLot['unread_num'] ? '' : 'hidden';
			$aVars['count'] = (int)$aLot['unread_num'] ? (int)$aLot['unread_num'] : 0;
			$aVars['bx_if:show_time'] = array(
												'condition' => $bShowTime,
												'content' => array(
														'time' => $iTime
													)
												);			
			
			$sContent .= $this -> parseHtmlByName('lots_briefs.html',  $aVars);
		}
		
		return $sContent;
	}
  
  	/**
	* Builds top talk area with Profiles names and Statuses
	*@param int $iProfileId logget member id
	*@param int $iStatus member status
	*@return string html code
	*/
	private function getOnlineStatus($iProfileId, $iStatus){
		switch($iStatus){
			case 0:
					$sTitle = _t('_bx_messenger_offline');
					$sClass = 'offline';
				break;
			case 2:
					$sTitle = _t('_bx_messenger_away');
					$sClass = 'away';
				break;
			default:
					$sTitle = _t('_bx_messenger_online');
					$sClass = 'online';
		}
	
		return $this -> parseHtmlByName('online_status.html', array(
			'id' => (int)$iProfileId,
			'title' => $sTitle,
			'class' => $sClass
		));
	}
	
	/**
	* Get jots list by specified criteria
	*@param int $iProfileId logged member id
	*@param array $aParams options
	*	- int $iLotId 
	*	- string $sUrl of the lot block
	*	- int $iStart jot's id from which to load the messages
	*	- string $sLoad type of the load (new jots or prev from history) 
	*	- int $iLimit number of jots
	*	- boolean $bDisplay make jots visible before loading
	*	- string html code
	*	- boolean load history from defined jot id and to select it
	*/
	public function getJotsOfLot($iProfileId, $aParams){
		
		$iLotId = isset($aParams['lot_id']) ? (int)$aParams['lot_id'] : BX_IM_EMPTY;
		$sUrl = isset($aParams['url']) ? $aParams['url'] : BX_IM_EMPTY_URL;
		$iStart = isset($aParams['start']) ? (int)$aParams['start'] : BX_IM_EMPTY; 
		$sLoad = isset($aParams['load']) ? $aParams['load'] : 'new';
		$iLimit = isset($aParams['limit']) ? (int)$aParams['limit'] : BX_IM_EMPTY; 
		$bDisplay = isset($aParams['display'])? (bool)$aParams['display'] : false;
		$bSelectJot = isset($aParams['select'])? (bool)$aParams['select'] : false;
		
		$aLotInfo = $this -> _oDb -> getLotByIdOrUrl($iLotId, $sUrl, $iProfileId);
		if (empty($aLotInfo))
			return '';
		
		$aStartMiddleJot = array();
		if ($bSelectJot && $iStart)
		{
			$aStartMiddleJot = $this -> _oDb -> getJotsByLotId($aLotInfo[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']], $iStart, 'prev', (int)$this -> _oConfig -> CNF['MAX_JOTS_BY_DEFAULT']/2);
			if (!empty($aStartMiddleJot))
				$iStart = $aStartMiddleJot[0][$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']];
		}
		
		$aJots = $this -> _oDb -> getJotsByLotId($aLotInfo[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']], $iStart, $sLoad, $iLimit, $bSelectJot && $iStart && empty($aStartMiddleJot)); 
		if (empty($aJots))
			return '';
					
		$aVars['bx_repeat:jots'] = array(); 
		foreach($aJots as $iKey => $aJot)
		{
			$oProfile = $this -> getObjectUser($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_AUTHOR']]);
			if ($oProfile) 
			{
				$sAttachment = $sMessage = '';
				$bIsTrash = (int)$aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_TRASH']];
				$bIsLotAuthor = $this -> _oDb -> isAuthor($iLotId, $iProfileId);
				if ($bIsTrash)
					$sMessage = $this -> getMessageIcons($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']], 'delete', isAdmin() || $bIsLotAuthor); 
				else
				{
					$sMessage = $this -> _oConfig -> bx_linkify($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE']]);
					$sAttachment = !empty($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_AT_TYPE']]) ? $this -> getAttachment($aJot) : '';
				}
				
				
				$aVars['bx_repeat:jots'][] = array(
						'title' => $oProfile->getDisplayName(),
						'time' => bx_time_js($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_ADDED']], BX_FORMAT_DATE_TIME),
						'url' => $oProfile->getUrl(),
						'thumb' => $oProfile->getThumb(),
						'id' => $aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']],
						'message' => $sMessage,
						'attachment' => $sAttachment,
						'display' => !$bDisplay ? 'style="display:none;"' : '',
						'bx_if:blink-jot' => array(
							'condition' => $bSelectJot && $aParams['start'] == $aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']],
							'content' => array()
						),
						'display_message' => '',
						'bx_if:jot_menu' => array
						(
							'condition' => $iProfileId && !$bIsTrash,
							'content'	=> array(
													'bx_if:delete' => array
													(
														'condition' => isAdmin() || $aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_AUTHOR']] == $iProfileId || $bIsLotAuthor,
														'content'	=> array(
															'confirm_delete' => bx_js_string(_t('_bx_messenger_remove_jot_confirm'))
														)
													)
												)
						),
						'edit_icon' => $aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_EDIT_BY']] && !$bIsTrash ? 
							$this -> parseHtmlByName('edit_icon.html',
									array(
											'edit' => _t('_bx_messenger_edit_by', 
											bx_process_output($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_LAST_EDIT']], BX_DATA_DATETIME_TS), 
											$this -> getObjectUser($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_EDIT_BY']]) -> getDisplayName()),							
										)
									): ''
					);
				
				$this -> _oDb -> readMessage($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_ID']], $iProfileId);
			}
		}	
		return $this -> parseHtmlByName('jots.html',  $aVars);
	}

	/**
	* Builds left column with content 
	*@param int $iLotId  id of the lot to select by default
	*@param int $iJotId  id of the jot to select by default
	*@param int $iProfileId logget member id
	*@param int $iTalkPerson id of profile to talk with 
	*@return string html code
	*/
	public function getLotsColumn($iLotId = BX_IM_EMPTY, $iJotId = BX_IM_EMPTY, $iProfileId, $iTalkPerson = BX_IM_EMPTY){
		$sContent = '';
		
		$aMyLots = $this -> _oDb -> getMyLots($iProfileId);
		if (!empty($aMyLots))
			$sContent = $this -> getLotsPreview($iProfileId, $aMyLots);
		else 
			$sContent = $this -> getFriendsList();
		
		$aVars = array(
			'items' => $sContent,
			'create_lot_title' => bx_js_string(_t('_bx_messenger_lots_menu_create_lot_title')),
			'star_title' => bx_js_string(_t('_bx_messenger_lots_menu_star_title')),
			'search_for_title' => bx_js_string(_t('_bx_messenger_search_for_lost_title')),
			'bx_repeat:menu' => array(
										array('menu_title' => _t("_bx_messenger_lots_type_all"), 'type' => 0, 'count' => '')
									 ),
			'profile' => (int)$iTalkPerson,
			'lot_id' => (int)$iLotId,
			'jot_id' => (int)$iJotId ? $iJotId : $this -> _oDb -> getFirstUnreadJot($iProfileId, $iLotId),
			'star_icon' => $this -> _oConfig -> CNF['STAR_ICON'],
			'star_color' => $this -> _oConfig -> CNF['STAR_BACKGROUND_COLOR']
		);
		
		/**
		 *  @deprecated
		*/
		$aMyLotsTypes = $this -> _oDb -> getMemberLotsTypes($iProfileId);
		$aMenu = $this -> _oDb -> getAllLotsTypes();
		foreach($aMenu as $iKey => $aValue){
			$sName	= $aValue[$this -> _oConfig -> CNF['FIELD_TYPE_NAME']];
			$iCount	= isset($aMyLotsTypes[$aValue[$this -> _oConfig -> CNF['FIELD_TYPE_ID']]]) ? $aMyLotsTypes[$aValue[$this -> _oConfig -> CNF['FIELD_TYPE_ID']]] : 0;
			$aVars['bx_repeat:menu'][] = array('menu_title' => _t("_bx_messenger_lots_type_{$sName}"), 'type' => $aValue[$this -> _oConfig -> CNF['FIELD_TYPE_ID']], 'count' => $iCount ? "($iCount)" : '');
		}	
		
		return $this -> parseHtmlByName('lots_list.html', $aVars);
	}

	/**
	* Create js configuration for the messenger depends on administratin settings
	*@param int $iProfileId logget member id
	*@return string html code
	*/
	public function loadConfig($iProfileId){
		$aUrlInfo = parse_url(BX_DOL_URL_ROOT); 
		$oEmbed = BxDolEmbed::getObjectInstance();
        if($oEmbed)
            $sEmbedTemplate = $oEmbed->getLinkHTML('__url__');
				
		$aVars = array(
			'profile_id' => (int)$iProfileId,
			'server_url' => $this->_oConfig-> CNF['SERVER_URL'],
			'online' => bx_js_string(_t('_bx_messenger_online')),
			'offline' => bx_js_string(_t('_bx_messenger_offline')),
			'away' => bx_js_string(_t('_bx_messenger_away')),
			'repost_of_the_message' => bx_js_string(_t('_bx_messenger_repost_message')),
			'close_video_confirm' => bx_js_string(_t('_bx_messenger_close_video_confirm')),
			'video_is_not_supported' => bx_js_string(_t('_bx_messenger_video_record_is_not_supported')),
			'bx_messenger_search_no_results' => bx_js_string(_t('_bx_messenger_search_no_results')),
			'bx_messenger_search_query_issue' => bx_js_string(_t('_bx_messenger_search_query_issue')),
			'video_exceed' => bx_js_string(_t('_bx_messenger_max_video_file_exceeds', $this->_oConfig->CNF['MAX_VIDEO_LENGTH'])),	
			'message_length' => (int)$this->_oConfig->CNF['MAX_SEND_SYMBOLS'] ? (int)$this->_oConfig-> CNF['MAX_SEND_SYMBOLS'] : 0,
			'ip' => gethostbyname($aUrlInfo['host']),
			'smiles' => (int)$this->_oConfig-> CNF['CONVERT_SMILES'],
			'ebmed_template' => $sEmbedTemplate,
			'bx_if:onsignal' => array(
										'condition'	=> (int)$iProfileId && $this->_oConfig-> CNF['IS_PUSH_ENABLED'] && !getParam('sys_push_app_id'),
										'content' => array(
											'one_signal_api' => $this->_oConfig-> CNF['PUSH_APP_ID'],
											'short_name' => $this->_oConfig-> CNF['PUSH_SHORT_NAME'],
											'safari_key' => $this->_oConfig-> CNF['PUSH_SAFARI_WEB_ID'],
											'jot_chat_page_url' => $this->_oConfig-> CNF['URL_HOME'],
											'notification_request' => bx_js_string(_t('_bx_messenger_notification_request')),
											'notification_request_yes' => bx_js_string(_t('_bx_messenger_notification_request_yes')),
											'notification_request_no' => bx_js_string(_t('_bx_messenger_notification_request_no')),
											'profile_id' => (int)$iProfileId,
										)
									)
		);

		return $this -> parseHtmlByName('config.html', $aVars);
	}
	
	public function getMessageIcons($iJotId, $sType = 'edit', $isAdmin = false)
	{ 
		$sContent = '';		
		if (!($aJotInfo = $this -> _oDb -> getJotById($iJotId)))
			return $sContent;
		
		$sDate = bx_process_output($aJotInfo[$this -> _oConfig -> CNF['FIELD_MESSAGE_LAST_EDIT']], BX_DATA_DATETIME_TS);
		$sEditorName = $aJotInfo[$this -> _oConfig -> CNF['FIELD_MESSAGE_EDIT_BY']] ? $this -> getObjectUser($aJotInfo[$this -> _oConfig -> CNF['FIELD_MESSAGE_EDIT_BY']]) -> getDisplayName() : '';
		
		switch($sType)
		{
			case 'edit':
				$sContent = $aJotInfo[$this -> _oConfig -> CNF['FIELD_MESSAGE_EDIT_BY']] ? 
								$this -> parseHtmlByName('edit_icon.html',
									array(
											'edit' => _t('_bx_messenger_edit_by', $sDate, $sEditorName)
										)
								) : '';
				break;
			case 'delete':
				$sContent = $this -> parseHtmlByName('deleted_jot.html',
						array(
								'bx_if:allow_to_delete' => array(
															'condition' => $isAdmin,
															'content'	=>
															array(
																	'message' => bx_js_string(_t('_bx_messenger_confirm_delete_completely')),
																	'id' => $iJotId
																),
															),
								'info' => $sEditorName ? _t('_bx_messenger_deleted_by', $sDate, $sEditorName) : ''
							)
					);
				break;
		}
		
		return $sContent;
	}
	
	/**
	* Create profile html template for jot which is used when member posts a message
	*@param int $iProfileId logget member id
	*@return string html code
	*/
	public function getMembersJotTemplate($iProfileId){
		if (!$iProfileId) return '';
		
		$oProfile = $this -> getObjectUser($iProfileId);
		if ($oProfile)
		{
			$aVars['bx_repeat:jots'][] = array
			(
				'title' => $oProfile->getDisplayName(),
				'time' => bx_time_js(time(), BX_FORMAT_TIME),
				'url' => $oProfile->getUrl(),
				'thumb' => $oProfile->getThumb(),
				'display' => 'style="display:flex;"',
				'display_message' => 'style="display:none;"',
				'id' => 0,
				'message' => '',
				'attachment' => '',
				'bx_if:blink-jot' => array(
					'condition' => false,
					'content' => array()
				),
				'bx_if:jot_menu' => array(
					'condition' => $iProfileId,
					'content'	=> array(
							'bx_if:delete' => array(
								'condition' => true,
								'content'	=> array(
									'confirm_delete' => bx_js_string(_t('_bx_messenger_remove_jot_confirm'))
								)
							),
						)
				),
				'edit_icon' => ''
			);
					
			return $this -> parseHtmlByName('jots.html',  $aVars);
		}
		
		return '';
	}
	
	/**
	* Returns attachment according jot's attachment type
	*@param array $aJot jot info
	*@return string html code
	*/	
	function getAttachment(&$aJot){
		$sHTML = '';
		$iViewer = bx_get_logged_profile_id();
		
		if (!empty($aJot))
		{
			switch($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_AT_TYPE']])
			{
				case 'repost':
						$sHTML = $this -> getJotAsAttachment($aJot[$this->_oConfig->CNF['FIELD_MESSAGE_AT']]);
						break;
				case 'files':
						$aFiles = $this -> _oDb -> getJotFiles($aJot[$this->_oConfig->CNF['FIELD_MESSAGE_ID']]);						
						$aItems = array(
							'bx_repeat:images' => array(),
							'bx_repeat:files' => array(),
							'bx_repeat:videos' => array()
						);
						
						$aTranscodersVideo = array();
						if (isset($this -> _oConfig -> CNF['OBJECT_VIDEOS_TRANSCODERS']) && $this -> _oConfig -> CNF['OBJECT_VIDEOS_TRANSCODERS'])
							$aTranscodersVideo = array (
								'poster' => BxDolTranscoderImage::getObjectInstance($this -> _oConfig -> CNF['OBJECT_VIDEOS_TRANSCODERS']['poster']),
								'mp4' => BxDolTranscoderVideo::getObjectInstance($this -> _oConfig -> CNF['OBJECT_VIDEOS_TRANSCODERS']['mp4']),
								'webm' => BxDolTranscoderVideo::getObjectInstance($this -> _oConfig -> CNF['OBJECT_VIDEOS_TRANSCODERS']['webm']),
							);
				
						$oStorage = new BxMessengerStorage($this->_oConfig-> CNF['OBJECT_STORAGE']);
						foreach($aFiles as $iKey => $aFile)
						{
								$isAuthor = $aFile[$this -> _oConfig -> CNF['FIELD_ST_AUTHOR']] == $iViewer || isAdmin();
								$isVideo = !empty($aTranscodersVideo) && $aTranscodersVideo['mp4']->isMimeTypeSupported($aFile[$this->_oConfig->CNF['FIELD_ST_TYPE']]) && $aTranscodersVideo['webm']->isMimeTypeSupported($aFile[$this->_oConfig->CNF['FIELD_ST_TYPE']]);
								
								if ($oStorage -> isImageFile($aFile[$this->_oConfig->CNF['FIELD_ST_TYPE']]))
								{
									$sPhotoThumb = '';
									if ($aFile[$this->_oConfig->CNF['FIELD_ST_TYPE']] != 'image/gif' && $oImagesTranscoder = BxDolTranscoderImage::getObjectInstance($this->_oConfig->CNF['OBJECT_IMAGES_TRANSCODER_PREVIEW']))
										$sPhotoThumb = $oImagesTranscoder->getFileUrl((int)$aFile[$this->_oConfig->CNF['FIELD_ST_ID']]);					
									
									$sFileUrl = BxDolStorage::getObjectInstance($this->_oConfig-> CNF['OBJECT_STORAGE'])->getFileUrlById((int)$aFile[$this->_oConfig->CNF['FIELD_ST_ID']]);
									$aItems['bx_repeat:images'][] = array(
										'url' => $sPhotoThumb ? $sPhotoThumb : $sFileUrl,
										'name' => $aFile[$this->_oConfig->CNF['FIELD_ST_NAME']],
										'id' => $aFile[$this->_oConfig->CNF['FIELD_ST_ID']],
										'delete_code' => $this -> deleteFileCode($aFile[$this->_oConfig->CNF['FIELD_ST_ID']], $isAuthor)
									);
								}elseif ($isVideo)
								{
									$sFileUrl = BxDolStorage::getObjectInstance($this->_oConfig-> CNF['OBJECT_STORAGE'])->getFileUrlById((int)$aFile[$this->_oConfig->CNF['FIELD_ST_ID']]);
									$aItems['bx_repeat:videos'][] = array(
										'id' => $aFile[$this->_oConfig->CNF['FIELD_ST_ID']],
										'video' => BxTemplFunctions::getInstance()->videoPlayer(
														$aTranscodersVideo['poster']->getFileUrl($aFile[$this->_oConfig->CNF['FIELD_ST_ID']]), 
														$aTranscodersVideo['mp4']->getFileUrl((int)$aFile[$this->_oConfig->CNF['FIELD_ST_ID']]), 
														$aTranscodersVideo['webm']->getFileUrl((int)$aFile[$this->_oConfig->CNF['FIELD_ST_ID']]),
														false, ''
													),
										'delete_code' => $this -> deleteFileCode($aFile[$this->_oConfig->CNF['FIELD_ST_ID']], $isAuthor)
									);
								}
								else
									$aItems['bx_repeat:files'][] = array(
										'type' => $oStorage -> getFontIconNameByFileName($aFile[$this->_oConfig->CNF['FIELD_ST_NAME']]),
										'name' => $aFile[$this->_oConfig->CNF['FIELD_ST_NAME']],
										'file_type' => $aFile[$this->_oConfig->CNF['FIELD_ST_TYPE']],
										'id' => $aFile[$this->_oConfig->CNF['FIELD_MESSAGE_ID']],
										'delete_code' => $this -> deleteFileCode($aFile[$this->_oConfig->CNF['FIELD_MESSAGE_ID']], $isAuthor),
										'url' => BX_DOL_URL_ROOT
									);
						}
						
						$sHTML = $this -> parseHtmlByName('files.html', $aItems);
						break;
			}
			
			
		}
		
		return $sHTML;
	}
	
	/**
	* Returns Jot content as attachment(repost) for a message
	*@param int $iJotId jot id
	*@return string html code
	*/
	function getJotAsAttachment($iJotId){
		$sMessage = $sHTML = '';
		
		$aJot = $this -> _oDb -> getJotById($iJotId);
		if (empty($aJot))
			return $sHTML;
		
		$iAttachedJotId = $this -> _oDb -> hasAttachment($iJotId);
		if ($iJotId != $iAttachedJotId)
		{
			$sOriginalMessage = $this->_oConfig->cleanRepostLinks($aJot[$this->_oConfig->CNF['FIELD_MESSAGE']], $iAttachedJotId);
			if (!$sOriginalMessage)
				$aJot = $this -> _oDb -> getJotById($iAttachedJotId);
			
			$sMessage = $aJot[$this->_oConfig->CNF['FIELD_MESSAGE']];
		}
		
		if ($aJot[$this->_oConfig->CNF['FIELD_MESSAGE_AT_TYPE']] == BX_ATT_TYPE_FILES)
			$sMessage = $aJot[$this->_oConfig->CNF['FIELD_MESSAGE']] . $this -> getAttachment($aJot);
		else
			$sMessage = $this -> _oConfig -> bx_linkify($aJot[$this->_oConfig->CNF['FIELD_MESSAGE']]);
		
		if (!empty($aJot))
		{
			$aLotsTypes = $this -> _oDb -> getLotsTypesPairs();
			$oProfile = $this -> getObjectUser($aJot[$this->_oConfig->CNF['FIELD_MESSAGE_AUTHOR']]);
			$aLotInfo =  $this -> _oDb -> getLotByJotId($iJotId, false);
			$sHTML = $this -> parseHtmlByName('repost.html', array(
					'icon' => $oProfile -> getThumb(),
					'message' => $sMessage,
					'username' => $oProfile -> getDisplayName(),
					'message_type' => !empty($aLotInfo) && isset($aLotInfo[$this->_oConfig->CNF['FIELD_TYPE']])? _t('_bx_messenger_lots_message_type_' . $aLotsTypes[$aLotInfo[$this->_oConfig->CNF['FIELD_TYPE']]]) : '',
					'date' => bx_process_output($aJot[$this->_oConfig->CNF['FIELD_MESSAGE_ADDED']], BX_DATA_DATETIME_TS),
				));
		}
		
		return $sHTML;
	}
	
	/**
	* Returns user profile even if it was removed from the site 
	*@param int $iProfileId profile id 
	*@return object instance of Profile
	*/
	private function getObjectUser($iProfileId)
	{
		bx_import('BxDolProfile');
		$oProfile = BxDolProfile::getInstance($iProfileId);
		if (!$oProfile)
		{
			bx_import('BxDolProfileUndefined');
			$oProfile = BxDolProfileUndefined::getInstance();
		}

		return $oProfile;
	}
	
	/**
	* Returns right side file's menu in talk history. Allows to  remove or donwload the file
	*@param int $iFileId file id in storage table
	*@param boolean $bAuthor is the vendor of the file
	*@return string html
	*/
	public function deleteFileCode($iFileId, $bAuthor = false){
		return $this -> parseHtmlByName('file_menu.html', array(
					'id' => (int)$iFileId,
					'url' => BX_DOL_URL_ROOT,
					'bx_if:delete' => array(
						'condition' => $bAuthor,
						'content'	=> array(
							'delete_message' => bx_js_string(_t('_bx_messenger_post_confirm_delete_file')),
							'id' => (int)$iFileId
						)
					 )
				));
	}
	
	/**
	* Returns files uploading form
	*@param int $iProfile viewer profile id
	*@return string html form
	*/
	public function getFilesUploadingForm($iProfileId){
		$oStorage = BxDolStorage::getObjectInstance($this->_oConfig-> CNF['OBJECT_STORAGE']);
		if (!$oStorage)
			return '';	
		
		return $this -> parseHtmlByName('uploader_form.html', array(
			'restrictions_text' => '',
			'delete' => bx_js_string(_t('_bx_messenger_upload_delete')),
			'delete_confirm' => bx_js_string(_t('_bx_messenger_delete_confirm')),
			'all_files_confirm' => bx_js_string(_t('_bx_messenger_delete_all_files_confirm')),
			'message' => bx_js_string(_t('_bx_messenger_upload_drop_area_message')),
			'invalid_file_type' => bx_js_string(_t('_bx_messenger_upload_invalid_file_type')),
			'file_size' => (int)$oStorage -> getMaxUploadFileSize($iProfileId)/(1024*1024), //convert to MB
			'big_file' => bx_js_string($oStorage -> getRestrictionsTextFileSize($iProfileId)),
			'max_files_exceeded' => bx_js_string(_t('_bx_messenger_max_files_upload_error')),
			'number_of_files' => (int)$this->_oConfig-> CNF['MAX_FILES_TO_UPLOAD'],
			'response_error' => bx_js_string(_t('_bx_messenger_invalid_server_response')),
        ));
	}
	
	/**
	* Returns files uploading form
	*@param int $iProfile viewer profile id
	*@return string html form
	*/
	public function getEditJotArea($iJotId)
	{
		$aJot = $this -> _oDb -> getJotById($iJotId);
		return $this -> parseHtmlByName('edit_jot.html', array(
			'place_holder' => _t('_bx_messenger_post_area_message'),
			'content' => $aJot[$this->_oConfig->CNF['FIELD_MESSAGE']]
        ));
	}

	/**
	* Returns body of the jot
	*@param int $iJotId id of the jot
	*@return string html form
	*/	
	public function getJotsBody($iJotId)
	{
		$aJot = $this -> _oDb -> getJotById($iJotId);
		if (empty($aJot))
			return '';

		$sMessage = $this -> _oConfig -> bx_linkify($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE']]);
		$sAttachment = !empty($aJot[$this -> _oConfig -> CNF['FIELD_MESSAGE_AT_TYPE']]) ? $this -> getAttachment($aJot) : '';
		$aVars = array(
			'message' => $sMessage,
			'attachment' => $sAttachment
		);
		
		return $this -> parseHtmlByName('hidden_jot.html',  $aVars);
	}

	/**
	* Returns Video Recording form
	*@param int $iProfile viewer profile id
	*@return string html form
	*/
	public function getVideoRecordingForm($iProfileId){
		return $this -> parseHtmlByName('video_record_form.html', array('max_video_length' => (int)$this->_oConfig->CNF['MAX_VIDEO_LENGTH']  * 60 * 1000));
	}
}

/** @} */
