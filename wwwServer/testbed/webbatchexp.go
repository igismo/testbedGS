/********************************************************************************
#
#    <"testbedGS" - Runtime structures and modular distributed component
#      architecture providing infrastructure and platform to build testbeds>
#
#    Copyright (C) <2018>  <Goran Scuric, goran@usa.net, igismo.com>
#
#    GNU GENERAL PUBLIC LICENSE ... Version 3, 29 June 2007
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation, either version 3 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
#*********************************************************************************/
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================
package main
import (
	// "os/exec"
	// "log"
)
/*
"webbatchexp $batcharg      ## not batched:  ""
							## exp_ batched: -i ,
                            ## exp_swapin:   -f
 							## exp_savedisk: -s
             -E $exp_desc
             $exp_swappable ## exp_swappable:      -S
							## exp_autoswap:       -a
                            ## exp_idleswap:       -l <time>
							## exp_swappable != 1: -L
             $linktestarg   ## exp_linktest:       -t
             -p $exp_pid
             -g $exp_gid
             -e $exp_id
			 $thensfile"
             SUEXEC_ACTION_IGNORE);
*/


//=======================================================================
// webbatchexp()
// Send a message to expMaster requesting to parse the nsfile/xmlfile
// Unless pid/eid object already exists, expMaster will create a new object
// and pass all parameters and the ns/xml file to it.
// The message code is , rcvr=EXP_MGR, sender=PHP_MGR ??
// TODO figure out the content and how to pass the file
// For this to work we need the EXP_MGR IP address and other ....
// For now read that from a system file on the phpServer, later it has to
// be initialized from whatever officdMgr tells us
// Also allow more than one phpServer !! - so we need instance name
// Maybe a separate phpMgr is solution that handles all of that ??
//=======================================================================
func webbatchexp () {


	}
