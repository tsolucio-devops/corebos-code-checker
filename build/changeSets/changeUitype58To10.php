<?php
/*************************************************************************************************
 * Copyright 2016 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
 * Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
 * file except in compliance with the License. You can redistribute it and/or modify it
 * under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
 * granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
 * applicable law or agreed to in writing, software distributed under the License is
 * distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
 * either express or implied. See the License for the specific language governing
 * permissions and limitations under the License. You may obtain a copy of the License
 * at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
 *************************************************************************************************
 *  Module       : MODULENAME
 *  Version      : 1.0
 *  Author       : JPL TSolucio, S. L.
 *************************************************************************************************/
class changeUitype58To10 extends cbupdaterWorker {
	function applyChange() {
		global $adb;
		if ($this->hasError()) $this->sendError();
		if ($this->isApplied()) {
			$this->sendMsg('Changeset '.get_class($this).' already applied!');
		} else {
			$ui58rs = $adb->query("select fieldid,name
					from vtiger_field
					inner join vtiger_tab on vtiger_tab.tabid=vtiger_field.tabid
					where uitype = '58'");
			while ($ui58 = $adb->fetch_array($ui58rs)) {
				$this->ExecuteQuery("insert into vtiger_fieldmodulerel (fieldid,module,relmodule,status,sequence) values (?,?,'Campaigns',null,0)",
					array($ui58['fieldid'],$ui58['name']));
			}
			$this->ExecuteQuery("UPDATE vtiger_field SET uitype = '10' WHERE uitype = '58'");
			$this->ExecuteQuery("UPDATE vtiger_relatedlists SET name = 'get_dependents_list' WHERE tabid = '26' and related_tabid='2'");
			$this->sendMsg('Changeset '.get_class($this).' applied!');
			$this->markApplied();
		}
		$this->finishExecution();
	}
}

?>