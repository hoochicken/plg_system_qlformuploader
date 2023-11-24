<?php
/**
 * @package		plg_qlformuploader
 * @copyright	Copyright (C) 2017 ql.de All rights reserved.
 * @author 		Mareike Riegel mareike.riegel@ql.de
 * @license		GNU General Public License version 2 or later; see LICENSE.txt*/

defined('_JEXEC') or die ('Restricted Access');

;

class clsQllicence
{
    public function __construct($licence)
    {
        $this->licence=$licence;
    }
    function checkIfAllowed()
    {
        echo '<pre>';print_r($_SERVER);
        return true;
    }
    function algorithm($a,$b,$c)
    {

    }
}