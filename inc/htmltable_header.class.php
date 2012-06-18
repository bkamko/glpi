<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Damien Touraine
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/**
 * @since version 0.84
**/
abstract class HTMLTable_Header extends HTMLTable_Entity {

   private $name;
   private $father;
   private $itemtype;
   private $colSpan     = 1;
   private $numberCells = 0;


   /**
    * get the table of the header (for a subheader, it is the table of its super header)
    *
    * @return HTMLTable_ the table owning the current header
   **/
   abstract protected function getTable();


   /**
    * get its name and subname : usefull for instance to create an index for arrays
    *
    * @param $header_name [out]     string   header name
    * @param $subheader_name [out]  string   sub header name ( = '' in case of super header)
    *
    * @return nothing
   **/
   abstract function getHeaderAndSubHeaderName(&$header_name, &$subheader_name);


   /**
    * check to see if it is a super header or not
    *
    * @return true if this is a super header
   **/
   abstract function isSuperHeader();


   /**
    * @param $name      string   the name of the header
    * @param $content            see HTMLTable_Entity#__construct()
    * @param $father             HTMLTable_Header object:
    *                            the father of the current column (default NULL)
   **/
   function __construct($name, $content, HTMLTable_Header $father=NULL) {

      parent::__construct($content);

      $this->name       = $name;
      $this->itemtype   = '';
      $this->father     = $father;
   }


   /**
    * @param $itemtype
   **/
   function setItemType($itemtype) {
      $this->itemtype = $itemtype;
   }


   function getItemType() {
      return $this->itemtype;
   }


   function getName() {
      return $this->name;
   }


   /**
    * @param $colSpan
   **/
   function setColSpan($colSpan) {
      $this->colSpan = $colSpan;
   }


   function addCell() {
      $this->numberCells ++ ;
   }


   function hasToDisplay() {
      return ($this->numberCells > 0);
   }


   function getColSpan() {
      return $this->colSpan;
   }


   /**
    * @param $with_content
   **/
   function displayTableHeader($with_content) {

      echo "<th colspan='".$this->colSpan."'>";
      if ($with_content) {
         $this->displayContent();
      } else {
         echo "&nbsp;";
      }
      echo "</th>";
   }


   function getFather() {
      return $this->father;
   }
}
?>