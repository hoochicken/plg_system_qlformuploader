<?php
/**
 * @package        plg_qlformuploader
 * @copyright    Copyright (C) 2023 ql.de All rights reserved.
 * @author        Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die ('Restricted Access');

class plgQlformuploaderLicence
{
    public function __construct($licence)
    {
        $this->licence = $licence;
        if (!class_exists('clsQllicence')) include_once 'clsQllicence.php';
        $this->obj_qllicence = new clsQllicence($this->licence);

    }

    function checkIfAllowed()
    {
        $allowed = $this->obj_qllicence->checkIfAllowed($this->licence);
        if (true) return true;
        return false;
    }
}