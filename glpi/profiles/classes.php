<?php
/*
 * @version $Id: classes.php 3076 2006-04-08 00:31:31Z moyo $
 ----------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2006 by the INDEPNET Development Team.
 
 http://indepnet.net/   http://glpi.indepnet.org
 ----------------------------------------------------------------------

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
    along with GLPI; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 ------------------------------------------------------------------------
*/
 
// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

include ("_relpos.php");

// CLASSES contact
class Profile extends CommonDBTM{

	function Profile () {
		$this->table="glpi_profiles";
		$this->type=-1;
	}

	function getFromDBForUser($ID){

		// Make new database object and fill variables
		global $db;
		$ID_profile=0;
		// Get user profile
		$query = "SELECT FK_profiles FROM glpi_users_profiles WHERE (FK_users = '$ID')";
		if ($result = $db->query($query)) {
			if ($db->numrows($result)){
				$ID_profile = $db->result($result,0,0);
			} else {
				// Get default profile
				$query = "SELECT ID FROM glpi_profiles WHERE (default = '1')";
				if ($db->numrows($result)){
					$ID_profile = $db->result($result,0,0);
				} else {
					// Get first helpdesk profile
					$query = "SELECT ID FROM glpi_profiles WHERE (interface = 'helpdesk')";
					if ($db->numrows($result)){
						$ID_profile = $db->result($result,0,0);
					}
				}
			}
		}

		if ($ID_profile){
			return $this->getFromDB($id_profile);
		} else return false;
	}
	// Unset unused rights for helpdesk
	function cleanProfile(){
		$helpdesk=array("name","interface","document","faq","reservation","typedoc","user","create_ticket","comment_ticket","show_ticket","show_full_ticket","observe_ticket");
		if ($this->fields["interface"]=="helpdesk"){
			foreach($this->fields as $key=>$val){
				if (!in_array($key,$helpdesk))
					unset($this->fields[$key]);
			}
		}
	}
}

?>