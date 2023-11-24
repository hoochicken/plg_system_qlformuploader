<?php
/**
 * @package		plg_qlformuploader
 * @copyright	Copyright (C) 2017 ql.de All rights reserved.
 * @author 		Mareike Riegel mareike.riegel@ql.de
 * @license		GNU General Public License version 2 or later; see LICENSE.txt*/

defined('_JEXEC') or die ('Restricted Access');

class plgQlformuploaderFiler
{
    public $arrImage=array();
    public $arrValidMimeType=array();
    public $return=false;
    public $arrMessages=array();
    private $mime='';
    public $params;
    private $module;
    private $module_params;
    private $name='qlformuploader';
    private $tablename='#__qlformuploader_logs';
    /**
	* wants to have file path and folder path
	* @return bool true on success, false on failure
	*/
    public function __construct($module,$module_params,$form)
    {
        require_once(JPATH_BASE.'/plugins/system/qlformuploader/php/classes/plgQlformulploaderMimeTypes.php');
        require_once(JPATH_BASE.'/plugins/system/qlformuploader/php/classes/plgQlformuploaderMessager.php');
        $this->loadLanguage();
        $this->module=$module;
        $this->module_params=$module_params;
        $this->form=$form;
        $obj_mimetyper=new plgQlformulploaderMimeTypes();

        $this->arrMimeTypes=$obj_mimetyper->get('arrMimeTypes');
        if (true!=$this->checkLicenceAllowed())
        {
            $this->arrMessages[]=array('str'=>JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_NOTALLOWED'),'type'=>'error');
            if (isset($this->arrMessages) AND 0<count($this->arrMessages)) foreach($this->arrMessages as $v)$this->arrMessages=$v;
            return false;
        }
        return true;
    }
    /**
     *
     */
    function loadLanguage()
    {
        $lang = JFactory::getLanguage();
        $lang->load($this->name, JPATH_ADMINISTRATOR, $lang->getTag(), true);
        $lang->load($this->name, JPATH_ADMINISTRATOR, $lang->getDefault(), true);
    }

    /**
     * method to check if uploader is allowed by ql, means having payed licence etc.
     * @return bool
     */
    public function checkLicenceAllowed()
    {
        return true;
    }
    /*
    function saveFiles($arr_files)
    {
        $destinationFolder=$this->module_params->get('fileupload_destination','qlformuploader');
        if (!is_dir($destinationFolder)) throw new Exception(sprintf(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_UPLOADFOLDERINEXISTENT'),$destinationFolder));
        $arrCheck=array();
        $arrCheck['filemaxsize']=$this->module_params->get('fileupload_maxfilesize',10000);
        $arrCheck['filetypeallowed']=explode(',',(string)$this->module_params->get('fileupload_filetypeallowed',''));
        foreach ($arr_files as $k=>$v)
        {
            $arr_files[$k]=$this->saveFile($v,$destinationFolder,$arrCheck,$this->module_params);
            if (0==$v['error'] AND 1==$arr_files[$k]['return'])$this->logg($v,$arr_files[$k]['destination'],$this->module,$this->module_params);
            else continue;
        }
        return $arr_files;
    }*/

    /**
     * Method to check for valid file
     *
     * @param	array	$arr_file consisting array of one file
     * @param	string	$destination of file(s)
     * @param	array	$arrCheck with endings/file types allowed
     * @param	string	$filenameChange naming the type of change that the filename has to undergo
     * @return  bool    true on success; false on failure
     */
    public function saveFile($arr_file,$destinationFolder)
    {
        if (4==$arr_file['error']) return true; /*<=case no file uploaded because none has been chosen*/
        try
        {
            if (''==$destinationFolder) throw new Exception(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_NODESTINATIONDEFINED'));
            //if (1!=$this->checkFile($arr_file,$arrCheck)) throw new Exception(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILENOTSAVED'));
            jimport('joomla.filesystem.file');
            $arr_file['destinationFolder']=$destinationFolder;
            $this->mkDestination($arr_file['destinationFolder']);
            $arr_file['destinationFile']=$this->getFilename($arr_file['name'],$this->module_params->get('fileupload_filename'));
            $arr_file['destination']=$arr_file['destinationFolder'].'/'.$arr_file['destinationFile'];
            //???$destination=JPATH_ROOT.'/'.$arr_file['destination'];
            $arr_file['fileUploaded']=JFile::move($arr_file['tmp_name'],$arr_file['destination']);
            $arr_file['current']=JPATH_ROOT.'/'.$arr_file['destination'];
            $arr_file['current']=$arr_file['destination'];
            //$arr_file['current']=$arr_file['destination'];
        }
        catch(Exception $e)
        {
            foreach ($this->arrMessages as $v) JFactory::getApplication()->enqueueMessage($v['str']);
            $this->arrMessages[]=array('str'=>$e->getMessage(),'type'=>'error');
            $arr_file['fileUploaded']=false;
        }

        return $arr_file;
    }
    /**
     * Method to check if files match for upload params
     *
     * @param	array	$arr_file consisting array of one file
     * @param	array	$arrCheck arraywith data of check params like maxfilesize etc.
     * @return  bool    true on success; false on failure
     */
    function checkFiles($arr_files)
    {
        $arrCheck=array();
        $arrCheck['filemaxsize']=$this->module_params->get('fileupload_maxfilesize',10000);
        $arrCheck['filetypeallowed']=explode(',',(string)$this->module_params->get('fileupload_filetypeallowed',''));
        while(list($k,$v)=each($arr_files))
        {
            $mixChecker=$this->checkFile($v,$arrCheck);
            if (true===$mixChecker) $arr_files[$k]['allowed']=true;
            else
            {
                $arr_files[$k]=$mixChecker;
                $arr_files[$k]['allowed']=false;
            }
        }
        reset($arr_files);
        return $arr_files;
    }
    /**
     * Method to check for valid file
     *
     * @param	array	$arr_file consisting array of one file
     * @param	array	$arrCheck arraywith data of check params like maxfilesize etc.
     * @return  bool    true on success; false on failure
     */
    public function checkFile($arr_file,$arrCheck)
    {
        try
        {
            if (empty($arr_file['tmp_name']) AND true!=$this->form->getField($arr_file['fieldname'])->getAttribute('required')) return;
            if ((true!=isset($arr_file['current']) OR true!=file_exists($arr_file['current'])) AND true==$this->form->getField('filefield')->getAttribute('required')) throw new Exception(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILENOTFOUND'));
            if (isset($arr_file['error']) AND 0!==$arr_file['error'])throw new Exception(sprintf(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEERROR'),$arr_file['error']));
            if (true!=$this->checkFileSize($arr_file['size'],$arrCheck['filemaxsize']))throw new Exception(sprintf(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEINVALIDSIZE'),$arr_file['name'],$arr_file['size'],$arrCheck['filemaxsize']));
            if (true!=$this->checkFileEnding($arr_file['name'],$arrCheck['filetypeallowed']))throw new Exception(JText::sprintf('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEINVALIDENDING',$arr_file['name']));
            if (1==$this->module_params->get('fileinfoUse',0) AND true!=$this->checkFileMimeType($arr_file['tmp_name'],$arrCheck['filetypeallowed']))throw new Exception(JText::sprintf('PLG_SYSTEM_QLFORMFILEUPLOADER_FILEINVALIDTYPE',$arr_file['name']));
            if (1==$this->module_params->get('fileinfoUse',0) AND true!=$this->checkFileConsistenceEndingType($arr_file['type'],$arr_file['name']))throw new Exception(JText::_('PLG_SYSTEM_QLFORMFILEUPLOADER_INVALIDCONSISTENCE'));
        }
        catch(Exception $e)
        {
            JFactory::getApplication()->enqueueMessage($e->getMessage(),'error');
            //$arr_file['errorMsg']=$e->getMessage();
            return $e->getMessage();
        }
        return true;
    }
    /**
     * Method to get new and hopefully unique filename
     *
     * @param	string	$filename original filename with ending
     * @param   string  $change type of change
     * @return  string  new filename
     */
    private function getFilename($filename,$change='')
    {
        $arrFilename=explode('.',$filename);
        $ending=array_pop($arrFilename);
        $filename=implode($arrFilename);
        $datetime=date('Ymd-His-u');
        $rand=mt_rand(1,100).mt_rand(1,100).mt_rand(1,100);
        switch($change)
        {
            case 'datetime-rand-filename':
                $filename=$datetime.'-'.$rand.'-'.$filename;
                break;
            case 'filename-datetime':
                $filename=$filename.'-'.$datetime;
                break;
            case 'datetime-filename':
                $filename=$datetime.'-'.$filename;
                break;
            case 'datetime-rand':
                $filename=$datetime.'-'.$rand;
                break;
            case 'filename':
                break;
            case 'filename-datetime-rand':
                $filename=$filename.'-'.$datetime.'-'.$rand;
            case 'datetime-filename-rand':
            default :
                $filename=$datetime.'-'.$filename.'-'.$rand;
        }
        return $filename.'.'.$ending;
    }

    /**
     * Method to generate an almost unique filename
     *
     * @param	array	$arr_file consisting array of one file
     * @return  bool    true on success; false on failure
     */
    public function checkFileSize($filesize,$filesizeMax)
    {
        if((int)$filesize<=(int)$filesizeMax)return true;
        return false;
    }
    /**
     * Method to generate an almost unique filename
     *
     * @param	array	$arr_file consisting array of one file
     * @return  bool    true on success; false on failure
     */
    public function checkFileEnding($filename,$arrValidMimeType)
    {
        $this->ending=$this->getFileEnding($filename);
        if(false!==array_search($this->ending,$arrValidMimeType)) return true;
        return false;
    }
    /**
     * Method to get file ending i. e. string after last '.' in filename
     *
     * @param string $filename if filename
     * @return string
     */
    private function getFileEnding($filename)
    {
        $arrFilename=explode('.',$filename);
        return array_pop($arrFilename);
    }

    /**
     * Method to check if file type is within allowed (mime) file types
     *
     * @param string $strFilename file name
     * @param array $arrValidMimeType
     * @return bool  true on success, false on failure
     */
    public function checkFileMimeType($strFilename,$arrValidMimeType=array())
    {
        if (!class_exists('finfo'))return true;
        $obj_finfo=new finfo(FILEINFO_MIME_TYPE);
        $this->mime=$obj_finfo->file($strFilename);
        $arrValidMimeType=array_flip($arrValidMimeType);
        $array=array_intersect_key($this->arrMimeTypes,$arrValidMimeType);
        if(in_array($this->mime,$array))return true;
        return false;
        if(function_exists('mime_content_type'))$fileType=mime_content_type($strFilename);
        return true;
    }
    /**
     * Method to generate an almost unique filename
     *
     * @param	array	$arr_file consisting array of one file
     * @return  bool    true on success; false on failure
     */
    public function checkFileConsistenceEndingType()
    {
        if (!class_exists('finfo'))return true;
        $mimeOfEnding=$this->arrMimeTypes[$this->ending];
        if($mimeOfEnding==$this->mime)return true;
        return false;
    }

    private function mkDestination($destination)
    {
        if(1==$this->module_params->get('fileupload_stripRoot',1)) $destination=str_replace(JPATH_ROOT,'',$destination);
        $arrDestination=explode('/',$destination);
        $arrPath=array();
        foreach($arrDestination as $v)
        {
            $v=trim($v);
            if (empty($v))continue;
            $arrPath[]=$v;
            $path=implode('/',$arrPath);
            if (!is_dir($path))
            {
                mkdir($path);
                chmod($path,0755);
            }
        }
    }
    public function logg($arr_file,$destination,$module)
    {
        $db=JFactory::getDbo();
        $data=$this->loggGetData($arr_file,$destination,$module);
        $db->insertObject($this->tablename,$data,'id');
    }
    private function loggGetData($arr_file,$destination,$module)
    {
        $data=new stdClass;
        $data->created=date('Y-m-d H:i:s');
        $data->fieldname=$arr_file['fieldname'];
        $data->filename=$arr_file['name'];
        $data->tmp_name=$arr_file['tmp_name'];
        $data->filesize=$arr_file['size'];
        $data->filetype=$arr_file['type'];
        $data->filedestination=$destination;
        $data->errorUploadServer=$arr_file['error'];
        $data->errorUploadFileCheckMsg=$arr_file['errorMsg'];
        if(!empty($data->errorUploadFileCheckMsg))$data->errorUploadFileCheck=1; else $data->errorUploadFileCheck=0;
        $data->user_id=JFactory::getUser()->get('id');
        $data->user_email=JFactory::getUser()->get('email');
        $data->module_id=$module->id;
        $data->module_title=$module->title;
        $data->module_params=json_encode($this->module_params);
        return $data;
    }
}