<?php

/*
 * Copyright 2010, Capgemini
 * Authors: Franck Villaume - capgemini
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/* addCategory action page */

$nameCategory = $_POST['nameCategory'];

if ($nameCategory != "") {
    try {
	    $clientSOAP = new SoapClient("http://".forge_get_config('server','mantisbt')."/api/soap/mantisconnect.php?wsdl", array('trace'=>true, 'exceptions'=>true));
	    $clientSOAP->__soapCall('mc_project_add_category', array("username" => $username, "password" => $password, "p_project_id" => $idProjetMantis, "p_category_name" => $nameCategory));
    } catch (SoapFault $soapFault) {
        $feedback = 'Error : '.$soapFault->faultstring;
        session_redirect('plugins/mantisbt/?type=admin&id='.$id.'&pluginname=mantisbt&error_msg='.urlencode($feedback));
    }
    $feedback = 'Op&eacute;ration r&eacute;ussie';
    session_redirect('plugins/mantisbt/?type=admin&id='.$id.'&pluginname=mantisbt&feedback='.urlencode($feedback));
}

?>
