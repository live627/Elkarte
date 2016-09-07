<?php

/**
 * Functions to support the permissions controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 */

/**
 * Class to initialize inline permissions sub-form and save its settings
 */
class Inline_Permissions_Form
{
	/**
	 * @var string[]
	 */
	private $permissions = array();

	/**
	 * @var string[]
	 */
	private $illegal_permissions = array();

	/**
	 * @var string[]
	 */
	private $illegal_guest_permissions = array();

	/**
	 * @var int[]
	 */
	private $excluded_groups = array();

	private $permissionsObject;
	private $db;

	/**
	 * @return string[]
	 */
	public function getPermissions()
	{
		return $this->permissions;
	}

	/**
	 * @param string[] $permissions
	 */
	public function setPermissions($permissions)
	{
		$this->permissions = $permissions;

		// Load the permission settings for guests
		$this->permissionList = array_map(
			function ($permission)
			{
				return $permission[1];
			}, $this->permissions
		);

		// Load the permission settings for guests
		$this->illegal_guest_permissions = array_intersect(
			array_map(
				function ($permission)
				{
					return str_replace(array('_any', '_own'), '', $permission[1]);
				}, $this->permissions
			), $this->permissionsObject->getIllegalGuestPermissions()
		);
	}

	/**
	 * @return int[]
	 */
	public function getExcludedGroups()
	{
		return $this->excluded_groups;
	}

	/**
	 * @param int[] $excluded_groups
	 */
	public function setExcludedGroups($excluded_groups)
	{
		$this->excluded_groups = $excluded_groups;
	}

	/**
	 * @param string $permission
	 */
	public function add($permission)
	{
		$this->permissions[] = $permission;
	}

	public function __construct()
	{
		$this->db = database();

		// Make sure they can't do certain things,
		// unless they have the right permissions.
		$this->permissionsObjectObject = new Permissions;
		$this->illegal_permissions = $this->permissionsObjectObject->getIllegalPermissions();
	}

	/**
	 * Save the permissions of a form containing inline permissions.
	 *
	 * @param string[] $permissions
	 */
	public function save()
	{
		global $context;

		// Almighty session check, verify our ways.
		checkSession();
		validateToken('admin-mp');

		$insertRows = array();
		foreach ($this->permissionList as $permission)
		{
			if (!isset($_POST[$permission]))
				continue;

			foreach ($_POST[$permission] as $id_group => $value)
			{
				if (in_array($value, array('on', 'deny')) && !in_array($permission, $this->illegal_permissions))
					$insertRows[] = array((int) $id_group, $permission, $value == 'on' ? 1 : 0);
			}
		}

		// Remove the old permissions...
		$this->db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE permission IN ({array_string:permissions})
			' . (empty($this->illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'illegal_permissions' => $this->illegal_permissions,
				'permissions' => $this->permissions,
			)
		);

		// ...and replace them with new ones.
		if (!empty($insertRows))
			$this->db->insert('insert',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$insertRows,
				array('id_group', 'permission')
			);

		// Do a full child update.
		$permissionsObject->updateChild(array(), -1);

		// Just in case we cached this.
		updateSettings(array('settings_updated' => time()));
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variables for each permission.
	 * This function is used by several settings screens to set specific permissions.
	 *
	 * @uses ManagePermissions language
	 * @uses ManagePermissions template
	 */
	public function init()
	{
		global $context, $txt, $modSettings;

		loadLanguage('ManagePermissions');
		loadTemplate('ManagePermissions');
		// No permissions? Not a great deal to do here.
		if (!allowedTo('manage_permissions'))
			return;

		// Load the permission settings for guests
		foreach ($this->permissions as $permission)
			$context[$permission[1]] = array(
				-1 => array(
					'id' => -1,
					'name' => $txt['membergroups_guests'],
					'is_postgroup' => false,
					'status' => 'off',
				),
				0 => array(
					'id' => 0,
					'name' => $txt['membergroups_members'],
					'is_postgroup' => false,
					'status' => 'off',
				),
			);

		$request = $this->db->query('', '
			SELECT id_group, CASE WHEN add_deny = {int:denied} THEN {string:deny} ELSE {string:on} END AS status, permission
			FROM {db_prefix}permissions
			WHERE id_group IN (-1, 0)
				AND permission IN ({array_string:permissions})',
			array(
				'denied' => 0,
				'permissions' => $this->permissionList,
				'deny' => 'deny',
				'on' => 'on',
			)
		);
		while ($row = $this->db->fetch_assoc($request))
			$context[$row['permission']][$row['id_group']]['status'] = $row['status'];
		$this->db->free_result($request);

		$request = $this->db->query('', '
			SELECT mg.id_group, mg.group_name, mg.min_posts, IFNULL(p.add_deny, -1) AS status, p.permission
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission IN ({array_string:permissions}))
			WHERE mg.id_group NOT IN (1, 3)
				AND mg.id_parent = {int:not_inherited}' . (empty($modSettings['permission_enable_postgroups']) ? '
				AND mg.min_posts = {int:min_posts}' : '') . '
			ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
			array(
				'not_inherited' => -2,
				'min_posts' => -1,
				'newbie_group' => 4,
				'permissions' => $this->permissionList,
			)
		);
		while ($row = $this->db->fetch_assoc($request))
		{
			// Initialize each permission as being 'off' until proven otherwise.
			foreach ($this->permissions as $permission)
				if (!isset($context[$permission[1]][$row['id_group']]))
					$context[$permission[1]][$row['id_group']] = array(
						'id' => $row['id_group'],
						'name' => $row['group_name'],
						'is_postgroup' => $row['min_posts'] != -1,
						'status' => 'off',
					);

			$context[$row['permission']][$row['id_group']]['status'] = empty($row['status']) ? 'deny' : ($row['status'] == 1 ? 'on' : 'off');
		}
		$this->db->free_result($request);

		// Some permissions cannot be given to certain groups. Remove them.
		foreach ($this->permissions as $permission)
		{
			foreach ($this->excluded_groups as $group)
			{
				if (isset($context[$permission[1]][$group]))
					unset($context[$permission[1]][$group]);
			}
			if (isset($permission['excluded_groups']))
			{
				foreach ($permission['excluded_groups'] as $group)
				{
					if (isset($context[$permission[1]][$group]))
						unset($context[$permission[1]][$group]);
				}
			}
		}

		// Are any of these permissions that guests can't have?
		foreach ($this->illegal_guest_permissions as $permission)
		{
			if (isset($context[$permission][-1]))
				unset($context[$permission][-1]);
		}
	}
}
