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
//=============================================================================
// FILE NAME: tbConfig.go
// DESCRIPTION: configuration, IP addresses, ...
// Notice that Docker provides resolution within created networks
// By defaulkt we create "TB-NETWORK" network where all testbed modules live.
// Each docker testbed module will have a preassigned name like "TB-OFFICEMGR",
// which will be translated by the dockers DNS into its IP address
//
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================

package tbConfig

import (
	"time"
)
//"testbedGS/common/tbMsgUtils"
//"strconv"

//# Configure variables

var BOSSNODE    = "boss.minibed.deterlab.net"
var CONTROL     = "users.minibed.deterlab.net"
var TBOPS       = "testbed-ops@minibed.deterlab.net"
var DBNAME              = "tbdb"
var TESTMODE            = 0
var TBOPSPID            = "emulab-ops"
var EXPTLOGNAME         = "activity.log"

var TBDIR     		   	= "/testbed"  // "/Users/scuric/go/testbed"
var TB          		= TBDIR
var PROJROOT            = TBDIR + "/proj"
var TBDB_EXPT_WORKDIR  	= TBDIR + "/expwork"
var TBDB_EXPT_INFODIR  	= TBDIR + "/expinfo"
var TBDB_PROJDIR		= TBDIR + "/proj"
var TBDB_USERDIR		= TBDIR // + "/proj"
var TBlogPath           = TBDIR + "/log/"
var TBversion           = time.Now()

var TBAPPROVAL    = "testbed-approval@minibed.deterlab.net"
var TBAUDIT       = "testbed-ops@minibed.deterlab.net"
var TBBASE        = "https://www.minibed.deterlab.net"
var TBWWW         = "<https://www.minibed.deterlab.net/>"

var parser      = "$TB/libexec/parse-ns"
var ExpDirList   = [] string {"tbdata", "bin", "tmp", "logs", "archive",
					"datastore", "tftpboot", "swapinfo", "containers", }
//strconv.FormatInt(tbMsgUtils.TBtimestamp(),10)

var TBnetwork           = "TB-NETWORK"

// Office Master
var TBofficeMgrName     = "TB-OFFICEMASTER"
var TBofficeMgrPort     = "1200"
var TBofficeMgr         = TBofficeMgrName + ":" + TBofficeMgrPort

// Experiment Master
var TBexpMgrName        = "TB-EXPMASTER"
var TBexpMgrPort        = "1200"
var TBexpMgr            = TBexpMgrName + ":" + TBexpMgrPort

// Resource Manager Master
var TBrsrcMasterName       = "TB-RSRCMASTER"
var TBrsrcMgrPort       = "1200"
var TBrsrcMgr           = TBrsrcMasterName + ":" + TBrsrcMgrPort

// Mysql DataBase Manager Master
var TBdBaseMasterName   = "TB-DBASEMASTER"
var TBdBaseMasterPort   = "1200"
var TBdBaseMaster       = TBdBaseMasterName + ":" + TBdBaseMasterPort

var TBmysqlMasterName   = "TB-MYSQLMASTER"
var TBmysqlMasterPort   = "1200"
var TBmysqlMaster       = TBmysqlMasterName + ":" + TBmysqlMasterPort

var TBmysqlServerName   = "TB-MYSQLMASTER"
var TBmysqlServerPort   = "3306"
var TBmysqlServer       = TBmysqlServerName + ":" + TBmysqlServerPort

// Experiment Master
var TBnfsMasterName        = "TB-NFSMASTER"
var TBnfsMasterPort        = "1200"
var TBnfsMaster            = TBnfsMasterName + ":" + TBnfsMasterPort

// www Master
var TBwwwMasterName        = "TB-WWWMASTER"
var TBwwwMasterPort        = "1200"
var TBwebMasterPort        = "6666"
var TBwwwMaster            = TBwwwMasterName + ":" + TBwwwMasterPort
var TBwebMaster            = TBwwwMasterName + ":" + TBwebMasterPort

// Archive
var MAINSITE    bool = false
var ARCHSUPPORT bool = false
var USEARCHIVE  = (MAINSITE || ARCHSUPPORT)
