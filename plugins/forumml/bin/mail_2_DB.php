<?php
#
# Copyright (c) STMicroelectronics, 2005. All Rights Reserved.

 # Originally written by Jean-Philippe Giola, 2005
 #
 # This file is a part of codendi.
 #
 # codendi is free software; you can redistribute it and/or modify
 # it under the terms of the GNU General Public License as published by
 # the Free Software Foundation; either version 2 of the License, or
 # (at your option) any later version.
 #
 # codendi is distributed in the hope that it will be useful,
 # but WITHOUT ANY WARRANTY; without even the implied warranty of
 # MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 # GNU General Public License for more details.
 #
 # You should have received a copy of the GNU General Public License along
 # with this program; if not, write to the Free Software Foundation, Inc.,
 # 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 #
 # $Id$
 #

/* This script allows the transfer of an mbox-formatted mail to ForumML database.
 *  First argument: mailing-list name
 *  Second argument: type of transfer, depending on the input
 * 		'1': transfer from '/var/run/forumml/mail_tmp_xyz' temporary file (1-message mbox file)
 * 		'2': transfer from whole list archive (real mbox file)
 *  Third argument: temporary file name (used when 2nd arg = 1)
*/

ini_set('max_execution_time', 0);
ini_set('memory_limit', -1);

require_once('pre.php');
error_reporting(E_ALL);
require 'Mail/Mbox.php';
require_once(dirname(__FILE__).'/../include/ForumML_mimeDecode.class.php');
require_once(dirname(__FILE__).'/../include/ForumMLInsert.class.php');
require_once(dirname(__FILE__).'/../include/ForumML_FileStorage.class.php');
require_once('common/plugin/PluginManager.class.php');
require_once 'plugins_utils.php';
require_once('www/mail/mail_utils.php');
require_once('utils.php');

$list = $argv[1];
// get list id and group id from list name
$sql = "SELECT group_id, group_list_id FROM mail_group_list WHERE list_name=$1";
$res = db_query_params($sql,array(db_escape_string($list)));
if (db_numrows($res) > 0) {
	$id_list = db_result($res,0,'group_list_id');
	$gr_id = db_result($res,0,'group_id');
} else {
	$stderr = fopen('php://stderr', 'w');
	fwrite($stderr, "Invalid mailing-list $list \n");
	fclose($stderr);
	exit;
}

$plugin_manager =& PluginManager::instance();
$p =& $plugin_manager->getPluginByName('forumml');
if ($p && $plugin_manager->isPluginAvailable($p) ) {
    $info =& $p->getPluginInfo();
	if ($argv[2] == 2) {
		// get list archive		
		$forumml_arch = $GLOBALS['forumml_arch'];
		$mbox_file = $forumml_arch."/private/".$list.".mbox/".$list.".mbox";
		// check if mbox file exists
		if (! is_file($mbox_file)) {
			$stderr = fopen('php://stderr', 'w');
			fwrite($stderr, "Invalid mbox file $mbox_file \n");
			fclose($stderr);
			exit;	
		}

        // Do not import from archives if there are already messages for this list
        $sql = "SELECT NULL FROM plugin_forumml_message WHERE id_list=$1 LIMIT 1";
        $res = db_query_params($sql,array(db_ei($id_list)));
        if ($res && db_numrows($res) > 0) {
            $stderr = fopen('php://stderr', 'w');
			fwrite($stderr, "Cannot import messages from archive.\nThere are already messages in the database for $list ($mbox_file)\n");
			fclose($stderr);
			exit;
        }
	} else {
		// get 3rd argument
		$temp_file = $argv[3];
		// get temp file parent dir
		$forumml_tmp = $GLOBALS['forumml_tmp'];
		$mbox_file = $forumml_tmp."/".$temp_file;
print "mboxfile=".$mbox_file;
	}

	// Open the mail that has been temporary stored
    $mbox = new Mail_Mbox($mbox_file);
	$mbox->open();
	if (PEAR::isError($mbox)) {
		print "Unable to open mbox: ".$mbox->getMessage().PHP_EOL;
	} else {
        $nbMailInserted = 0;
print "nb mail inserted=".$nbMailInserted;
		$num_msg        = $mbox->size();
		for ($i = 0; $i < $num_msg; $i++) {
			$thisMessage = $mbox->get($i);
			if (PEAR::isError($thisMessage)) {
				print "Unable to get message $i: ".$thisMessage->getMessage().PHP_EOL;
			} else {
                // Decode email
				$args['include_bodies'] = TRUE;
				$args['decode_bodies']  = TRUE;
				$args['decode_headers'] = TRUE;
				$args['crlf']           = "\r\n";
				$decoder = new ForumML_mimeDecode($thisMessage, "\r\n");
				$structure = $decoder->decode($args);

                // Get ForumML storage
                $forumml_dir     = $GLOBALS['forumml_dir'];
                $forumml_storage = new ForumML_FileStorage($forumml_dir);

                // Store email
				$insert = new ForumMLInsert($id_list);
                $msgId  = $insert->storeEmail($structure, $forumml_storage);
                if ($msgId) {
                    $nbMailInserted++;
                }
			}
		}

        // Display message when importing a mail archive
        if ($argv[2] == 2) {
            if ($num_msg == $nbMailInserted) {
                echo 'Operation Completed.'.$num_msg.' imported'.PHP_EOL;
            } else {
                echo '*** Error: '.$num_msg.' in '.$mbox_file.' file but '. $nbMailInserted.' stored in database'.PHP_EOL;
            }
        }
	}
}

// delete temporary file
if ($argv[2] == 1) {
	if (is_file($mbox_file)) {
		unlink($mbox_file);
	}
}

?>
