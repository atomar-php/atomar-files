<?php

namespace files;

use atomar\core\HookReceiver;

class Hooks extends HookReceiver
{
    function hookPermission()
    {
        return array(
            'files-download',
            'files-upload'
        );
    }

    function hookRoute($ext)
    {
        return $this->loadRoute($ext, 'public');
    }

    function hookStaticAssets($module)
    {
        return $this->loadRoute($module, 'assets');
    }

    function hookLibraries()
    {
        return array (
            'lib/DataStore.php',
            'lib/LocalDataStore.php',
            'lib/FileManager.php'
        );
    }

    function hookInstall()
    {
        $sql = <<<SQL
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name_searchable` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `size` int(11) unsigned DEFAULT NULL,
  `ext` varchar(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by_id` int(11) unsigned DEFAULT NULL,
  `is_uploaded` enum('0','1') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `data_store` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_file_user` (`created_by_id`),
  KEY `hash` (`hash`),
  KEY `data_store` (`data_store`),
  CONSTRAINT `cons_fk_file_created_by_id_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `filenode` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_filenode_file` (`file_id`),
  CONSTRAINT `cons_fk_filenode_file_id_id` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `filenode_fileuser` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `read` tinyint(1) unsigned DEFAULT NULL,
  `delete` tinyint(1) unsigned DEFAULT NULL,
  `filenode_id` int(11) unsigned DEFAULT NULL,
  `fileuser_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_filenode_fileuser_fileuser` (`fileuser_id`),
  KEY `index_foreignkey_filenode_fileuser_filenode` (`filenode_id`),
  CONSTRAINT `cons_fk_filenode_fileuser_filenode_id_id` FOREIGN KEY (`filenode_id`) REFERENCES `filenode` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  CONSTRAINT `cons_fk_filenode_fileuser_fileuser_id_id` FOREIGN KEY (`fileuser_id`) REFERENCES `fileuser` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `fileupload` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ttl` int(11) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `file_id` int(11) unsigned,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_fileupload_file` (`file_id`),
  KEY `token` (`token`(191)),
  CONSTRAINT `cons_fk_fileupload_file_id_id` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `fileuser` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_fileuser_user` (`user_id`),
  CONSTRAINT `cons_fk_fileuser_user_id_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `filenode_permission` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `permission_id` int(11) unsigned DEFAULT NULL,
  `filenode_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_for_filenode_permission_permission_id` (`permission_id`),
  KEY `index_for_filenode_permission_filenode_id` (`filenode_id`),
  CONSTRAINT `filenode_permission_ibfk_2` FOREIGN KEY (`filenode_id`) REFERENCES `filenode` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `filenode_permission_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permission` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

SET foreign_key_checks = 1;
SQL;
        \R::begin();
        try {
            \R::exec($sql);
            \R::commit();
            set_success('Files installed');
        } catch (\Exception $e) {
            \R::rollback();
            throw $e;
        }
    }

    function hookUninstall()
    {
        $sql = <<<SQL
SET foreign_key_checks = 0;
DROP TABLE IF EXISTS `file`;
DROP TABLE IF EXISTS `filenode`;
DROP TABLE IF EXISTS `filenode_fileuser`;
DROP TABLE IF EXISTS `filenode_permission`;
DROP TABLE IF EXISTS `fileupload`;
DROP TABLE IF EXISTS `fileuser`;
SET foreign_key_checks = 1;
SQL;
        \R::begin();
        try {
            \R::exec($sql);
            \R::commit();
        } catch (\Exception $e) {
            \R::rollback();
            throw new $e;
        }
    }
}