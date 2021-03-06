<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is:  vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
********************************************************************************/
require_once 'include/logging.php';
require_once 'modules/CustomView/CustomView.php';

class ListViewSession {

	public $module = null;
	public $viewname = null;
	public $start = null;
	public $sorder = null;
	public $sortby = null;
	public $page_view = null;

	/**initializes ListViewSession */
	public function __construct() {
		global $log,$currentModule;
		$log->debug('Entering ListViewSession() method ...');
		$this->module = $currentModule;
		$this->sortby = 'ASC';
		$this->start =1;
	}

	public static function getCurrentPage($currentModule, $viewId) {
		if (!empty($_SESSION['lvs'][$currentModule][$viewId]['start'])) {
			return $_SESSION['lvs'][$currentModule][$viewId]['start'];
		}
		return 1;
	}

	public static function getRequestStartPage() {
		$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : 1;
		if (!is_numeric($start)) {
			$start = 1;
		}
		if ($start < 1) {
			$start = 1;
		}
		$start = ceil($start);
		return $start;
	}

	public static function getListViewNavigation($currentRecordId) {
		global $currentModule, $current_user, $adb;
		$list_max_entries_per_page = GlobalVariable::getVariable('Application_ListView_PageSize', 20, $currentModule);
		$reUseData = false;
		$displayBufferRecordCount = 10;
		$bufferRecordCount = 15;
		if ($currentModule == 'Documents') {
			$result = $adb->pquery('select folderid from vtiger_notes where notesid=?', array($currentRecordId));
			$folderId = $adb->query_result($result, 0, 'folderid');
		}
		$cv = new CustomView();
		$viewId = $cv->getViewId($currentModule);
		if (!empty($_SESSION[$currentModule.'_DetailView_Navigation'.$viewId])) {
			$recordNavigationInfo = json_decode($_SESSION[$currentModule.'_DetailView_Navigation'.$viewId], true);
			if (count($recordNavigationInfo) == 1) {
				foreach ($recordNavigationInfo as $recordIdList) {
					if (in_array($currentRecordId, $recordIdList)) {
						$reUseData = true;
					}
				}
			} else {
				$recordList = array();
				$recordPageMapping = array();
				$searchKey = 0;
				foreach ($recordNavigationInfo as $start => $recordIdList) {
					foreach ($recordIdList as $index => $recordId) {
						$recordList[] = $recordId;
						$recordPageMapping[$recordId] = $start;
						if ($recordId == $currentRecordId) {
							$searchKey = count($recordList)-1;
						}
					}
				}
				if ($searchKey > $displayBufferRecordCount -1 && $searchKey < count($recordList)-$displayBufferRecordCount) {
					$reUseData= true;
				}
			}
		}

		if ($reUseData === false) {
			$recordNavigationInfo = array();
			if (!empty($_REQUEST['start'])) {
				$start = ListViewSession::getRequestStartPage();
			} else {
				$start = ListViewSession::getCurrentPage($currentModule, $viewId);
			}
			$startRecord = (($start - 1) * $list_max_entries_per_page) - $bufferRecordCount;
			if ($startRecord < 0) {
				$startRecord = 0;
			}

			$list_query = $_SESSION[$currentModule.'_listquery'];
			$instance = CRMEntity::getInstance($currentModule);
			$instance->getNonAdminAccessControlQuery($currentModule, $current_user);
			if ($currentModule=='Documents' && !empty($folderId)) {
				$list_query = preg_replace("/[\n\r\s]+/", " ", $list_query);
				$hasOrderBy = stripos($list_query, 'order by');
				if ($hasOrderBy>0) {
					$list_query = substr($list_query, 0, $hasOrderBy-1)." AND vtiger_notes.folderid=$folderId ".substr($list_query, $hasOrderBy);
				} else {
					$list_query .= " AND vtiger_notes.folderid=$folderId";
					$order_by = $instance->getOrderByForFolder($folderId);
					$sorder = $instance->getSortOrderForFolder($folderId);
					$tablename = getTableNameForField($currentModule, $order_by);
					$tablename = (($tablename != '')?($tablename."."):'');
					if (!empty($order_by)) {
						$list_query .= ' ORDER BY '.$tablename.$order_by.' '.$sorder;
					}
				}
			}
			if ($start !=1) {
				$recordCount = ($list_max_entries_per_page+2 * $bufferRecordCount);
			} else {
				$recordCount = ($list_max_entries_per_page+ $bufferRecordCount);
			}
			$list_query .= " LIMIT $startRecord, $recordCount";

			$resultAllCRMIDlist_query=$adb->pquery($list_query, array());
			$navigationRecordList = array();
			while ($forAllCRMID = $adb->fetch_array($resultAllCRMIDlist_query)) {
				$navigationRecordList[] = $forAllCRMID[$instance->table_index];
			}

			$pageCount = 0;
			$current = $start;
			if ($start ==1) {
				$firstPageRecordCount = $list_max_entries_per_page;
			} else {
				$firstPageRecordCount = $bufferRecordCount;
				$current -=1;
			}

			$searchKey = array_search($currentRecordId, $navigationRecordList);
			$recordNavigationInfo = array();
			if ($searchKey !== false) {
				foreach ($navigationRecordList as $index => $recordId) {
					if (!isset($recordNavigationInfo[$current]) || !is_array($recordNavigationInfo[$current])) {
						$recordNavigationInfo[$current] = array();
					}
					if ($index == $firstPageRecordCount  || $index == ($firstPageRecordCount+$pageCount * $list_max_entries_per_page)) {
						$current++;
						$pageCount++;
					}
					$recordNavigationInfo[$current][] = $recordId;
				}
			}
			coreBOS_Session::set($currentModule.'_DetailView_Navigation'.$viewId, json_encode($recordNavigationInfo));
		}
		return $recordNavigationInfo;
	}

	public static function getRequestCurrentPage($currentModule, $query, $viewid, $queryMode = false) {
		global $adb;
		$list_max_entries_per_page = GlobalVariable::getVariable('Application_ListView_PageSize', 20, $currentModule);
		$start = 1;
		if (isset($_REQUEST['query']) && $_REQUEST['query'] == 'true' && (empty($_REQUEST['start']) || $_REQUEST['start']!="last")) {
			return ListViewSession::getRequestStartPage();
		}
		if (!empty($_REQUEST['start'])) {
			$start = $_REQUEST['start'];
			if ($start == 'last') {
				$count_result = $adb->query(mkCountQuery($query));
				$noofrows = $adb->query_result($count_result, 0, "count");
				if ($noofrows > 0) {
					$start = ceil($noofrows/$list_max_entries_per_page);
				}
			}
			if (!is_numeric($start)) {
				$start = 1;
			} elseif ($start < 1) {
				$start = 1;
			}
			$start = ceil($start);
		} elseif (!empty($_SESSION['lvs'][$currentModule][$viewid]['start'])) {
			$start = $_SESSION['lvs'][$currentModule][$viewid]['start'];
		}
		if (!$queryMode) {
			coreBOS_Session::set('lvs^'.$currentModule.'^'.$viewid.'^start', (int)$start);
		}
		return $start;
	}

	public static function setSessionQuery($currentModule, $query, $viewid) {
		if (isset($_SESSION[$currentModule.'_listquery'])) {
			if ($_SESSION[$currentModule.'_listquery'] != $query) {
				coreBOS_Session::delete($currentModule.'_DetailView_Navigation'.$viewid);
			}
		}
		coreBOS_Session::set($currentModule.'_listquery', $query);
	}

	public static function hasViewChanged($currentModule) {
		if (empty($_SESSION['lvs'][$currentModule]['viewname'])) {
			return true;
		}
		if (empty($_REQUEST['viewname'])) {
			return false;
		}
		if ($_REQUEST['viewname'] != $_SESSION['lvs'][$currentModule]['viewname']) {
			return true;
		}
		return false;
	}
}
?>
