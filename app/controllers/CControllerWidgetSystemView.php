<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetSystemView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$filter = [
			'groupids' => null,
			'maintenance' => null,
			'severity' => null,
			'trigger_name' => '',
			'extAck' => 0
		];

		if (CProfile::get('web.dashconf.filter.enable', 0) == 1) {
			// groups
			if (CProfile::get('web.dashconf.groups.grpswitch', 0) == 0) {
				// null mean all groups
				$filter['groupids'] = null;
			}
			else {
				$filter['groupids'] = getMultiselectGroupIds(
					zbx_objectValues(CFavorite::get('web.dashconf.groups.groupids'), 'value'),
					zbx_objectValues(CFavorite::get('web.dashconf.groups.subgroupids'), 'value')
				);
				$hide_groupids = getMultiselectGroupIds(
					zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.groupids'), 'value'),
					zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.subgroupids'), 'value')
				);

				if ($hide_groupids) {
					// get all groups if no selected groups defined
					if (!$filter['groupids']) {
						$dbHostGroups = API::HostGroup()->get([
							'output' => ['groupid']
						]);
						$filter['groupids'] = zbx_objectValues($dbHostGroups, 'groupid');
					}

					$filter['groupids'] = array_diff($filter['groupids'], $hide_groupids);

					// get available hosts
					$dbAvailableHosts = API::Host()->get([
						'groupids' => $filter['groupids'],
						'output' => ['hostid']
					]);
					$availableHostIds = zbx_objectValues($dbAvailableHosts, 'hostid');

					$dbDisabledHosts = API::Host()->get([
						'groupids' => $hide_groupids,
						'output' => ['hostid']
					]);
					$disabledHostIds = zbx_objectValues($dbDisabledHosts, 'hostid');

					$filter['hostids'] = array_diff($availableHostIds, $disabledHostIds);
				}
				elseif (!$filter['groupids']) {
					// null mean all groups
					$filter['groupids'] = null;
				}
			}

			// hosts
			$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
			$filter['maintenance'] = ($maintenance == 0) ? 0 : null;

			// triggers
			$severity = CProfile::get('web.dashconf.triggers.severity', null);
			$filter['severity'] = zbx_empty($severity) ? null : explode(';', $severity);
			$filter['severity'] = zbx_toHash($filter['severity']);
			$filter['trigger_name'] = CProfile::get('web.dashconf.triggers.name', '');

			$config = select_config();
			$filter['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
		}

		$this->setResponse(new CControllerResponseData([
			'filter' => $filter,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
