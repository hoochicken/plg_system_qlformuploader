<?php
/**
 * @package        plg_qlformuploader
 * @copyright    Copyright (C) 2023 ql.de All rights reserved.
 * @author        Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 || later; see LICENSE.txt
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

defined('_JEXEC') || die ('Restricted Access');

class plgQlformuploaderFiler
{
    public array $arrImage = [];
    public array $arrValidMimeType = [];
    public bool $return = false;
    public array $arrMessages = [];
    private string $mime = '';
    public Registry $params;
    private stdClass $module;
    private $form;
    private array $arrMimeTypes;
    private $module_params;
    private string $name = 'qlformuploader';
    private string $full_name = 'plg_system_qlformuploader';
    private string $tablename = '#__qlformuploader_logs';
    private ?Exception $exception;

    /**
     * wants to have file path && folder path
     * @return bool true on success, false on failure
     */
    public function __construct($module, $module_params, $form)
    {
        require_once(JPATH_BASE . '/plugins/system/qlformuploader/php/classes/plgQlformulploaderMimeTypes.php');
        require_once(JPATH_BASE . '/plugins/system/qlformuploader/php/classes/plgQlformuploaderMessager.php');
        $this->loadLanguage();
        $this->module = $module;
        $this->module_params = $module_params;
        $this->form = $form;
        $obj_mimetyper = new plgQlformulploaderMimeTypes();

        $this->arrMimeTypes = $obj_mimetyper->get('arrMimeTypes');
    }

    /**
     *
     */
    function loadLanguage()
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load($this->full_name, JPATH_ADMINISTRATOR, $lang->getTag(), true);
        $lang->load($this->full_name, JPATH_ADMINISTRATOR, $lang->getDefault(), true);
    }

    public function saveFile(array $file, string $destinationFolder = '')
    {
        if (4 === $file['error']) {
            return [
                'name' => 'no-name',
                'current' => 'no-file-current',
                'hyperlink' => 'no-hyperlink',
                'link' => 'no-link',
                'error' => false,
                'errorUploadServer' => true,
                'errorUploadFileCheck' => true,
            ];
        } /*<=case no file uploaded because none has been chosen*/
        try {
            if (empty($destinationFolder)) throw new Exception(Text::_('PLG_SYSTEM_QLFORMFILEUPLOADER_NODESTINATIONDEFINED'));
            //if (1!=$this->checkFile($file,$check)) throw new Exception(Text::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILENOTSAVED'));
            jimport('joomla.filesystem.file');
            $file['destinationFolder'] = $destinationFolder;
            $this->mkDir($file['destinationFolder']);
            $file['destinationFile'] = $this->getFilename($file['name'], $this->module_params->get('fileupload_filename'));
            $file['destination'] = $file['destinationFolder'] . '/' . $file['destinationFile'];
            $file['fileUploaded'] = File::move($file['tmp_name'], $file['destination']);
            chmod($file['destination'], 444);
            $file['current'] = $file['destination'];
            $fileBare = str_replace(JPATH_ROOT, '', $file['destination']);
            $file['link'] = sprintf('%s://%s/%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST'], $fileBare);
            $file['hyperlink'] = sprintf('<a href="%s" target="_blank">%s</a>', $file['link'], $file['destinationFile']);
        } catch (Exception $e) {
            foreach ($this->arrMessages as $v) Factory::getApplication()->enqueueMessage($v['str']);
            $this->arrMessages[] = ['str' => $e->getMessage(), 'type' => 'error'];
            $file['fileUploaded'] = false;
        }

        return $file;
    }


    function checkFiles($files)
    {
        $check = [];
        $check['filemaxsize'] = $this->module_params->get('fileupload_maxfilesize', 10000);
        $check['filetypeallowed'] = explode(',', (string)$this->module_params->get('fileupload_filetypeallowed', ''));
        foreach ($files as $k => $v) {
            $mixChecker = $this->checkFile($v, $check);
            if (true === $mixChecker) $files[$k]['allowed'] = true;
            else {
                $files[$k] = $mixChecker;
                $files[$k]['allowed'] = false;
            }
        }
        reset($files);
        return $files;
    }

    public function checkFile(array $file, array $check): bool
    {
        try {
            if (empty($file['tmp_name']) && !$this->form->getField($file['fieldname'])->getAttribute('required')) return true;
            if ((!isset($file['current']) || !file_exists($file['current'])) && $this->form->getField('filefield')->getAttribute('required')) throw new Exception(Text::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILENOTFOUND'));
            if (isset($file['error']) && 0 !== $file['error']) throw new Exception(sprintf(Text::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEERROR'), $file['error']));
            if (!$this->checkFileSize($file['size'], $check['filemaxsize'])) throw new Exception(sprintf(Text::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEINVALIDSIZE'), $file['name'], $file['size'], $check['filemaxsize']));
            if (!$this->checkFileEnding($file['name'], $check['filetypeallowed'])) throw new Exception(Text::sprintf('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEINVALIDENDING', $file['name']));
            if ($this->module_params->get('fileinfoUse', 0) && !$this->checkFileMimeType($file['tmp_name'], $check['filetypeallowed'])) throw new Exception(Text::sprintf('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEINVALIDTYPE', $file['name']));
            if ($this->module_params->get('fileinfoUse', 0) && !$this->checkFileConsistenceEndingType($file['type'], $file['name'])) throw new Exception(Text::_('PLG_SYSTEM_QLFORMFILEUPLOADER_INVALIDCONSISTENCE'));
        } catch (Exception $e) {
            $this->exception = $e;
            return false;
        }
        return true;
    }

    /**
     * Method to get new && hopefully unique filename
     *
     * @param string $filename original filename with ending
     * @param string $change type of change
     * @return  string  new filename
     */
    private function getFilename(string $filename, string $change = ''): string
    {
        $arrFilename = explode('.', $filename);
        $ending = array_pop($arrFilename);
        $filename = implode($arrFilename);
        $datetime = date('Ymd-His');
        $rand = mt_rand(1, 100) . mt_rand(1, 100) . mt_rand(1, 100) . '-' . uniqid();
        switch ($change) {
            case 'datetime-rand-filename':
                $filename = $datetime . '-' . $rand . '-' . $filename;
                break;
            case 'filename-datetime':
                $filename = $filename . '-' . $datetime;
                break;
            case 'datetime-filename':
                $filename = $datetime . '-' . $filename;
                break;
            case 'datetime-rand':
                $filename = $datetime . '-' . $rand;
                break;
            case 'filename':
                break;
            case 'filename-datetime-rand':
                $filename = $filename . '-' . $datetime . '-' . $rand;
            case 'datetime-filename-rand':
            default :
                $filename = $datetime . '-' . $filename . '-' . $rand;
        }
        return $filename . '.' . $ending;
    }

    public function checkFileSize(float $filesize, float $filesizeMax)
    {
        return ($filesize <= $filesizeMax);
    }

    public function checkFileEnding(string $filename, array $arrValidMimeType): bool
    {
        $this->ending = $this->getFileEnding($filename);
        return false !== array_search($this->ending, $arrValidMimeType);
    }

    private function getFileEnding(string $filename): string
    {
        $arrFilename = explode('.', $filename);
        return array_pop($arrFilename);
    }

    public function checkFileMimeType(string $strFilename, array $arrValidMimeType = []): bool
    {
        if (!class_exists('finfo')) return true;
        $obj_finfo = new finfo(FILEINFO_MIME_TYPE);
        $this->mime = $obj_finfo->file($strFilename);
        $arrValidMimeType = array_flip($arrValidMimeType);
        $array = array_intersect_key($this->arrMimeTypes, $arrValidMimeType);
        return in_array($this->mime, $array ?? []);
        if (function_exists('mime_content_type')) $fileType = mime_content_type($strFilename);
        return true;
    }

    public function checkFileConsistenceEndingType(): bool
    {
        if (!class_exists('finfo')) return true;
        $mimeOfEnding = $this->arrMimeTypes[$this->ending];
        if ($mimeOfEnding == $this->mime) return true;
        return false;
    }

    public function mkDir($destination)
    {
        if (is_dir($destination)) {
            return;
        }
        $arrDestination = array_filter(explode('/', $destination));
        $arrPath = [];
        foreach ($arrDestination as $v) {
            $v = trim($v);
            if (empty($v)) continue;
            $arrPath[] = $v;
            $path = '/' . implode('/', $arrPath);
            if (!is_dir($path)) {
                mkdir($path);
                chmod($path, 0755);
            }
        }
    }

    public function logg($file, $destination, $module)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');;
        $data = $this->loggGetData($file, $destination, $module);
        $db->insertObject($this->tablename, $data, 'id');
    }

    private function loggGetData($file, $destination, $module): stdClass
    {
        $data = new stdClass;
        $data->created = date('Y-m-d H:i:s');
        $data->fieldname = $file['fieldname'];
        $data->filename = $file['name'];
        $data->tmp_name = $file['tmp_name'];
        $data->filesize = $file['size'];
        $data->filetype = $file['type'];
        $data->filedestination = $destination;
        $data->error_upload_server = $file['error'];
        $data->error_upload_file_check_msg = $file['errorMsg'];
        $data->error_upload_file_check = !empty($data->error_upload_file_check_msg) ? '1' : '0';
        $data->user_id = Factory::getApplication()->getIdentity()->getParam('id', 0);
        $data->user_email = Factory::getApplication()->getIdentity()->getParam('email') ?? '';
        $data->module_id = $module->id;
        $data->module_title = $module->title;
        $data->module_params = json_encode($this->module_params);
        return $data;
    }

    public function getException(): Exception
    {
        return $this->exception;
    }

    public function clearException()
    {
        $this->exception = null;
    }
}