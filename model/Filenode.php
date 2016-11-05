<?php

namespace model;

use atomar\core\Auth;
use atomar\core\BeanModel;
use atomar\core\Logger;

/**
 * Represents a single file node.
 * File nodes associate other models with a file in a data store.
 * 
 * In general these are what will be used outside of this module when referring to a file.
 */
class Filenode extends BeanModel {

    /**
     * Grants a user access to a file. By default this includes all IO permissions
     *
     * @param null|User $user the user that is being granted access
     * @param null|array $access the access granted to the user: read, write, delete
     * @return boolean true if successful
     */
    public function grant_user_access(User $user, $access = array()) {
        $defaults = array('read' => '1', 'write'=>'1', 'delete' => '1');
        $access = array_merge($defaults, $access);
        $fileauth = \R::findOne('fileauth', ' user_id=? ', array($user->id));
        if (!$fileauth) {
            $fileauth = \R::dispense('fileauth');
            $fileauth->user = $user;
        }
        // TODO: we should not create the link if the user already has permission to access this file. otherwise we get duplicate data in the db.
        $this->bean->link('filenode_fileauth', array(
            'read' => $access['read'],
            'write' => $access['write'],
            'delete' => $access['delete']
        ))->fileauth = $fileauth;
        return true;
    }

    /**
     * Adds an allowed permission to a file. Users with this permission will be able to access the file.
     *
     * @param mixed $permission may be a string/bean or an array of strings/beans of permissions
     */
    public function grant_permission_access($permission = array()) {
        if (is_array($permission)) {
            foreach ($permission as $p) {
                $this->add_permission_access($p);
            }
        } else {
            $this->add_permission_access($permission);
        }
    }

    /**
     * internal utility to add required permissions to the file
     * @param $permission
     */
    private function add_permission_access($permission) {
        if (is_string($permission)) $permission = Auth::get_permission($permission);
        if ($permission) {
            $this->bean->sharedPermissionList[] = $permission;
        }
    }

    /**
     * Checks if the user has access to the file.
     * User access takes precedence over permission access.
     * If the access parameter is left empty this function will return true if the user has been granted
     * access to the file regardless of the granular permissions.
     *
     * @param \model\User $user the user to be authenticated.
     * @param array $access the access permissions that are being asked for. e.g. read/write/delete
     * @return boolean true if the user has access to the file
     */
    public function authenticate(User $user, array $access = array()) {
        // grant access if no specific permissions were added to the file
        if (!count($this->bean->sharedPermissionList) && !count($this->bean->sharedfileauthList)) return true;

        // check user access
        $sql = <<<SQL
SELECT
  `ff`.`id`
FROM
  `filenode_fileauth` AS `ff`
LEFT JOIN `fileauth` AS `fu` ON `fu`.`id`=`ff`.`fileauth_id`
WHERE
  `ff`.`filenode_id`=?
  AND `fu`.`user_id`=?
SQL;
        // add access conditions
        foreach ($access as $key => $value) {
            // TODO: update usage of these to use the latest methods.
            $key = mysql_escape_string($key);
            $value = mysql_escape_string($value);
            $sql .= <<<SQL

  AND `ff`.`$key`='$value'
SQL;
        }
        try {
            $access = \R::getCol($sql, array($this->bean->id, $user->id));
        } catch (\Exception $e) {
            Logger::log_error('File authentication error', $e->getMessage());
            $access = false;
        }

        if ($access) {
            return true;
        } else {
            // check permission access
            // TODO: use the access parameters to check authentication here in the same way we did for the user above.
            foreach ($this->bean->sharedPermissionList as $p) {
                if (in_array($p, $user->role->sharedPermissionList)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @deprecated use break_access() instead
     */
    public function delete() {
        $this->break_access();
    }

    /**
     * Revokes all access to this FileNode
     */
    public function break_access() {
        // destroy the fileauth link
        $sql = <<<SQL
DELETE FROM `filenode_fileauth`
WHERE `filenode_id`=?
SQL;
        \R::exec($sql, array($this->bean->id));
    }
}