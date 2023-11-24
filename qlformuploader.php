<?php
/**
 * @package		plg_qlformuploader
 * @copyright	Copyright (C) 2023 ql.de All rights reserved.
 * @author 		Mareike Riegel mareike.riegel@ql.de
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

//no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

defined('_JEXEC') or die ('Restricted Access');


class plgSystemQlformuploader extends CMSPlugin
{
    private string $installTableSqlFilePath = 'install.mysql.utf8.sql';
    private Joomla\Database\DatabaseDriver|null $db = null;
    private string $tableName = '#__qlformuploader_logs';
    private string $pluginName = 'qlformuploader';

    public function __construct(& $subject, $config)
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_content_qlstatistics', dirname(__FILE__));
        parent::__construct($subject, $config);
        $this->db = Factory::getContainer()->get('DatabaseDriver');

        $tableNameSpecific = $this->getSpecificTableName($this->tableName);
        if (!$this->tableExists($tableNameSpecific)) {
            $this->createTable($this->tableName);
        }
    }
    private function tableExists(string $tableName): bool
    {
        $tables = $this->db->getTableList();
        return in_array($tableName, $tables);
    }

    private function createTable($tableName)
    {
        $filePath = sprintf('%s/plugins/system/%s/sql/%s', JPATH_BASE, $this->pluginName, $this->installTableSqlFilePath);
        $sql = file_get_contents($filePath);
        $sql = str_replace($tableName, $this->getSpecificTableName($tableName), $sql);
        $this->db->setQuery($sql);
        $this->db->execute();
    }

    private function getSpecificTableName(string $tableName): string
    {
        return str_replace('#__', Factory::getApplication()->get('dbprefix', ''), $tableName);
    }
}