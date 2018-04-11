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
package tbExpUtils

import (
	"testbedGS/common/tbConfiguration"
	"time"
	"os"
)

//====================================================================================
//// source:  libdb.pm
// A library of useful DB stuff. Mostly things that get done a lot.
// Saves typing.
//
// XXX: The notion of "uid" is a tad confused. A unix uid is a number,
//      while in the DB a user uid is a string (equiv to unix login).
//      Needs to be cleaned up.
//====================================================================================


func TBDB_EXPT_WORKDIR()         string { return tbConfig.TBDB_EXPT_WORKDIR }
 
func NODERELOADING_PID()         string { return tbConfig.TBOPSPID }
func NODERELOADING_EID()         string { return "reloading" }
func NODERELOADPENDING_EID()     string { return "reloadpending" }
func NODEREPOSITIONING_PID()     string { return tbConfig.TBOPSPID }
func NODEREPOSITIONING_EID()     string { return "repositioning" }
func NODEREPOSPENDING_EID()      string { return "repositionpending" }
func NODEDEAD_PID()              string { return tbConfig.TBOPSPID }
func NODEDEAD_EID()              string { return "hwdown" }
func PLABMOND_PID()              string { return tbConfig.TBOPSPID }
func PLABMOND_EID()              string { return "plab-monitor" }
func PLABTESTING_PID()           string { return tbConfig.TBOPSPID }
func PLABTESTING_EID()           string { return "plab-testing" }
func PLABHOLDING_PID()           string { return tbConfig.TBOPSPID }
func PLABHOLDING_EID()           string { return "plabnodes"}
func PLABDOWN_PID()              string { return tbConfig.TBOPSPID }
func PLABDOWN_EID()              string { return "hwdown" }
func OLDRESERVED_PID()           string { return tbConfig.TBOPSPID }
func OLDRESERVED_EID()           string { return "oldreserved" }
func NFREELOCKED_PID()           string { return tbConfig.TBOPSPID }
func NFREELOCKED_EID()           string { return "nfree-locked" }
func TBOPSPID()                  string { return tbConfig.TBOPSPID }
func EXPTLOGNAME()               string { return tbConfig.EXPTLOGNAME }

func NODEBOOTSTATUS_OKAY()       string { return "okay"  }
func NODEBOOTSTATUS_FAILED()     string { return "failed" }
func NODEBOOTSTATUS_UNKNOWN()    string { return "unknown" }
func NODESTARTSTATUS_NOSTATUS()  string { return "none" }

func NODEFAILMODE_FATAL()        string { return "fatal" }
func NODEFAILMODE_NONFATAL()     string { return "nonfatal"}
func NODEFAILMODE_IGNORE()       string { return "ignore" }

//// Experiment states
func EXPTSTATE_NEW()             string { return "new" }
func EXPTSTATE_PRERUN()          string { return "prerunning" }
func EXPTSTATE_SWAPPED()         string { return "swapped" }
func EXPTSTATE_QUEUED()          string { return "queued" }
func EXPTSTATE_SWAPPING()        string { return "swapping" }
func EXPTSTATE_ACTIVATING()      string { return "activating" }
func EXPTSTATE_ACTIVE()          string { return "active" }
func EXPTSTATE_PANICED()         string { return "paniced" }
func EXPTSTATE_TERMINATING()     string { return "terminating" }
func EXPTSTATE_TERMINATED()      string { return "ended" }
func EXPTSTATE_MODIFY_PARSE()    string { return "modify_parse" }
func EXPTSTATE_MODIFY_REPARSE()  string { return "modify_reparse" }
func EXPTSTATE_MODIFY_RESWAP()   string { return "modify_reswap"}
func EXPTSTATE_RESTARTING()      string { return "restarting"}

//// For the batch_daemon.
func BATCHSTATE_LOCKED()         string { return "locked"}
func BATCHSTATE_UNLOCKED()       string { return "unlocked"}

//// Cancel flags
func EXPTCANCEL_CLEAR()          int { return 0 }
func EXPTCANCEL_TERM()           int { return 1 }
func EXPTCANCEL_SWAP()           int { return 2 }
func EXPTCANCEL_DEQUEUE()        int { return 3 }

func USERSTATUS_ACTIVE()         string { return "active" }
func USERSTATUS_FROZEN()         string { return "frozen" }
func USERSTATUS_UNAPPROVED()     string { return "unapproved" }
func USERSTATUS_UNVERIFIED()     string { return "unverified" }
func USERSTATUS_NEWUSER()        string { return "newuser" }
func USERSTATUS_ARCHIVED()       string { return "archived" }

// We want valid project membership to be non-zero for easy membership
// testing. Specific trust levels are encoded thusly.
//
func PROJMEMBERTRUST_NONE()       int { return 0 }
func PROJMEMBERTRUST_USER()       int { return 1 }
func PROJMEMBERTRUST_ROOT()       int { return 2 }
func PROJMEMBERTRUST_LOCALROOT()  int { return 2 }
func PROJMEMBERTRUST_GROUPROOT()  int { return 3 }
func PROJMEMBERTRUST_PROJROOT()   int { return 4 }
func PROJMEMBERTRUST_ADMIN()      int { return 5 }

//
// Access types. Duplicated in the web interface. Make changes there too!
//
// Things you can do to a node.
func TB_NODEACCESS_READINFO()     int { return 1 }
func TB_NODEACCESS_MODIFYINFO()   int { return 2 }
func TB_NODEACCESS_LOADIMAGE()    int { return 3 }
func TB_NODEACCESS_REBOOT()       int { return 4 }
func TB_NODEACCESS_POWERCYCLE()   int { return 5 }
func TB_NODEACCESS_MODIFYVLANS()  int { return 6 }
func TB_NODEACCESS_MIN()          int { return TB_NODEACCESS_READINFO() }
func TB_NODEACCESS_MAX()          int { return TB_NODEACCESS_MODIFYVLANS() }

// User Info (modinfo web page, etc).
func TB_USERINFO_READINFO()       int { return 1 }
func TB_USERINFO_MODIFYINFO()     int { return 2 }
func TB_USERINFO_MIN()            int { return TB_USERINFO_READINFO() }
func TB_USERINFO_MAX()            int { return TB_USERINFO_MODIFYINFO() }

// Experiments.
func TB_EXPT_READINFO()           int { return 1 }
func TB_EXPT_MODIFY()             int { return 2 }
func TB_EXPT_DESTROY()            int { return 3 }
func TB_EXPT_UPDATE()             int { return 4 }
func TB_EXPT_MIN()                int { return TB_EXPT_READINFO() }
func TB_EXPT_MAX()                int { return TB_EXPT_UPDATE() }

// Projects.
func TB_PROJECT_READINFO()        int { return 1 }
func TB_PROJECT_MAKEGROUP()       int { return 2 }
func TB_PROJECT_EDITGROUP()       int { return 3 }
func TB_PROJECT_GROUPGRABUSERS()  int { return 4 }
func TB_PROJECT_BESTOWGROUPROOT() int { return 5 }
func TB_PROJECT_DELGROUP()        int { return 6 }
func TB_PROJECT_LEADGROUP()       int { return 7 }
func TB_PROJECT_ADDUSER()         int { return 8 }
func TB_PROJECT_DELUSER()         int { return 9 }
func TB_PROJECT_MAKEOSID()        int { return 10 }
func TB_PROJECT_DELOSID()         int { return 11 }
func TB_PROJECT_MAKEIMAGEID()     int { return 12 }
func TB_PROJECT_DELIMAGEID()      int { return 13 }
func TB_PROJECT_CREATEEXPT()      int { return 14 }
func TB_PROJECT_MIN()             int { return TB_PROJECT_READINFO() }
func TB_PROJECT_MAX()             int { return TB_PROJECT_CREATEEXPT() }

// OSIDs
func TB_OSID_READINFO()           int { return 1 }
func TB_OSID_CREATE()             int { return 2 }
func TB_OSID_DESTROY()            int { return 3 }
func TB_OSID_MIN()                int { return TB_OSID_READINFO() }
func TB_OSID_MAX()                int { return TB_OSID_DESTROY() }
func TB_OSID_OSIDLEN()            int { return 35 }
func TB_OSID_OSNAMELEN()          int { return 20 }
func TB_OSID_VERSLEN()            int { return  12 }

// Magic OSID constants
func TB_OSID_MBKERNEL()           string { return "_KERNEL_" } // multiboot kernel OSID

// Magic MFS constants
func TB_OSID_FREEBSD_MFS()        string { return "FREEBSD-MFS" }
func TB_OSID_FRISBEE_MFS()        string { return "FRISBEE-MFS" }

// ImageIDs
//
// Clarification:
// READINFO is read-only access to the image and its contents
// (This is what people get for shared images)
// ACCESS means complete power over the image and its [meta]data
func TB_IMAGEID_READINFO()        int { return 1 }
func TB_IMAGEID_MODIFYINFO()      int { return 2 }
func TB_IMAGEID_CREATE()          int { return 3 }
func TB_IMAGEID_DESTROY()         int { return 4 }
func TB_IMAGEID_ACCESS()          int { return 5 }
func TB_IMAGEID_MIN()             int { return TB_IMAGEID_READINFO() }
func TB_IMAGEID_MAX()             int { return TB_IMAGEID_ACCESS() }
func TB_IMAGEID_IMAGEIDLEN()      int { return 45 }
func TB_IMAGEID_IMAGENAMELEN()    int { return 30 }

// Node Log Types
var TB_NODELOGTYPE_MISC           = "misc"
func TB_NODELOGTYPES()            string { return ( TB_NODELOGTYPE_MISC )  }
func TB_DEFAULT_NODELOGTYPE()     string { return TB_NODELOGTYPE_MISC }

// Node History Stuff.
var TB_NODEHISTORY_OP_FREE       = "free"
var TB_NODEHISTORY_OP_ALLOC      = "alloc"
var TB_NODEHISTORY_OP_MOVE       = "move"

// Reload Types.
func TB_RELOADTYPE_NETDISK()      string { return "netdisk" }
func TB_RELOADTYPE_FRISBEE()      string { return "frisbee" }
func TB_DEFAULT_RELOADTYPE()      string { return TB_RELOADTYPE_FRISBEE() }

// Experiment priorities.
func TB_EXPTPRIORITY_LOW()        int { return 0 }
func TB_EXPTPRIORITY_HIGH()       int { return 20 }

// Assign exit status for too few nodes.
func TB_ASSIGN_TOOFEWNODES()      int { return 2 }

// System PID.
func TB_OPSPID()                  string { return tbConfig.TBOPSPID }

//
// Events we may want to send
//
var TBDB_TBEVENT_NODESTATE       = "TBNODESTATE"
var TBDB_TBEVENT_NODEOPMODE      = "TBNODEOPMODE"
var TBDB_TBEVENT_CONTROL         = "TBCONTROL"
var TBDB_TBEVENT_COMMAND         = "TBCOMMAND"
var TBDB_TBEVENT_EXPTSTATE       = "TBEXPTSTATE"

//
// For nodes, we use this set of events.
//
func TBDB_NODESTATE_ISUP()        string { return "ISUP" }
func TBDB_NODESTATE_ALWAYSUP()    string { return "ALWAYSUP" }
func TBDB_NODESTATE_REBOOTED()    string { return "REBOOTED" }
func TBDB_NODESTATE_REBOOTING()   string { return "REBOOTING" }
func TBDB_NODESTATE_SHUTDOWN()    string { return "SHUTDOWN" }
func TBDB_NODESTATE_BOOTING()     string { return "BOOTING" }
func TBDB_NODESTATE_TBSETUP()     string { return "TBSETUP" }
func TBDB_NODESTATE_RELOADSETUP() string { return "RELOADSETUP" }
func TBDB_NODESTATE_MFSSETUP()    string { return "MFSSETUP" }
func TBDB_NODESTATE_TBFAILED()    string { return "TBFAILED" }
func TBDB_NODESTATE_RELOADING()   string { return "RELOADING" }
func TBDB_NODESTATE_RELOADDONE()  string { return "RELOADDONE" }
func TBDB_NODESTATE_RELOADDONE_V2() string { return "RELOADDONEV2" }
func TBDB_NODESTATE_UNKNOWN()     string { return "UNKNOWN" }
func TBDB_NODESTATE_PXEWAIT()     string { return "PXEWAIT" }
func TBDB_NODESTATE_PXEWAKEUP()   string { return "PXEWAKEUP" }
func TBDB_NODESTATE_PXEBOOTING()  string { return "PXEBOOTING" }
func TBDB_NODESTATE_POWEROFF()    string { return "POWEROFF" }

var TBDB_NODEOPMODE_ANY          = "*"  // // A wildcard opmode
var TBDB_NODEOPMODE_NORMAL       = "NORMAL"
var TBDB_NODEOPMODE_DELAYING     = "DELAYING"
var TBDB_NODEOPMODE_UNKNOWNOS    = "UNKNOWNOS"
var TBDB_NODEOPMODE_RELOADING    = "RELOADING"
var TBDB_NODEOPMODE_NORMALv1     = "NORMALv1"
var TBDB_NODEOPMODE_MINIMAL      = "MINIMAL"
var TBDB_NODEOPMODE_PCVM         = "PCVM"
var TBDB_NODEOPMODE_RELOAD       = "RELOAD"
var TBDB_NODEOPMODE_RELOADMOTE   = "RELOAD-MOTE"
var TBDB_NODEOPMODE_RELOADPCVM   = "RELOAD-PCVM"
var TBDB_NODEOPMODE_DELAY        = "DELAY"
var TBDB_NODEOPMODE_BOOTWHAT     = "_BOOTWHAT_"  //// A redirection opmode
var TBDB_NODEOPMODE_UNKNOWN      = "UNKNOWN"

var TBDB_COMMAND_REBOOT          = "REBOOT"
var TBDB_COMMAND_POWEROFF        = "POWEROFF"
var TBDB_COMMAND_POWERON         = "POWERON"
var TBDB_COMMAND_POWERCYCLE      = "POWERCYCLE"

var TBDB_STATED_TIMEOUT_REBOOT   = "REBOOT"
var TBDB_STATED_TIMEOUT_NOTIFY   = "NOTIFY"
var TBDB_STATED_TIMEOUT_CMDRETRY = "CMDRETRY"

func TBDB_ALLOCSTATE_FREE_CLEAN()        string { return "FREE_CLEAN" }
func TBDB_ALLOCSTATE_FREE_DIRTY()        string { return "FREE_DIRTY" }
func TBDB_ALLOCSTATE_DOWN()              string { return "DOWN" }
func TBDB_ALLOCSTATE_DEAD()              string { return "DEAD" }
func TBDB_ALLOCSTATE_RELOAD_TO_FREE()    string { return "RELOAD_TO_FREE" }
func TBDB_ALLOCSTATE_RELOAD_PENDING()    string { return "RELOAD_PENDING" }
func TBDB_ALLOCSTATE_RES_RELOAD()        string { return "RES_RELOAD" }
func TBDB_ALLOCSTATE_RES_REBOOT_DIRTY()  string { return "RES_REBOOT_DIRTY" }
func TBDB_ALLOCSTATE_RES_REBOOT_CLEAN()  string { return "RES_REBOOT_CLEAN" }
func TBDB_ALLOCSTATE_RES_INIT_DIRTY()    string { return "RES_INIT_DIRTY" }
func TBDB_ALLOCSTATE_RES_INIT_CLEAN()    string { return "RES_INIT_CLEAN" }
func TBDB_ALLOCSTATE_RES_READY()         string { return "RES_READY" }
func TBDB_ALLOCSTATE_RES_RECONFIG()      string { return "RES_RECONFIG" }
func TBDB_ALLOCSTATE_RES_TEARDOWN()      string { return "RES_TEARDOWN"}
func TBDB_ALLOCSTATE_UNKNOWN()           string { return "UNKNOWN"}


var TBDB_TBCONTROL_RESET         = "RESET"
var TBDB_TBCONTROL_RELOADDONE    = "RELOADDONE"
var TBDB_TBCONTROL_RELOADDONE_V2 = "RELOADDONEV2"
var TBDB_TBCONTROL_TIMEOUT       = "TIMEOUT"
var TBDB_TBCONTROL_PXEBOOT       = "PXEBOOT"
var TBDB_TBCONTROL_BOOTING       = "BOOTING"
var TBDB_TBCONTROL_CHECKGENISUP  = "CHECKGENISUP"

// Constant we use for the timeout field when there is no timeout for a state
var TBDB_NO_STATE_TIMEOUT        = 0

//
// Node name we use in the widearea_* tables to represent a generic local node.
// All local nodes are considered to have the same network characteristcs.
//
var TBDB_WIDEAREA_LOCALNODE      = "boss"

//
// We should list all of the DB limits.
//
func DBLIMIT_NSFILESIZE()         int64 { return (Power(2,24) - 1) } 

//
// Virtual nodes must operate within a restricted port range. The range
// is effective across all virtual nodes in the experiment. When an
// experiment is swapped in, allocate a funcrange from this and setup
// all the vnodes to allocate from that range. We tell the user this
// range so this they can set up their programs to operate in that range.
//
func TBDB_LOWVPORT()              int { return 30000 }
func TBDB_MAXVPORT()              int { return 60000 }
func TBDB_PORTRANGE()             int { return 256   }

//
// STATS constants.
//
func TBDB_STATS_PRELOAD()         string { return "preload" }
func TBDB_STATS_START()           string { return "start" }
func TBDB_STATS_TERMINATE()       string { return "destroy" }
func TBDB_STATS_SWAPIN()          string { return "swapin" }
func TBDB_STATS_SWAPOUT()         string { return "swapout" }
func TBDB_STATS_SWAPMODIFY()      string { return "swapmod" }
func TBDB_STATS_SWAPUPDATE()      string { return "swapupdate" }
func TBDB_STATS_FLAGS_IDLESWAP()  int { return 0x01 }
func TBDB_STATS_FLAGS_PREMODIFY() int { return 0x02 }
func TBDB_STATS_FLAGS_START()     int { return 0x04 }
func TBDB_STATS_FLAGS_PRESWAPIN() int { return 0x08 }
func TBDB_STATS_FLAGS_BATCHCTRL() int { return 0x10 }
func TBDB_STATS_FLAGS_MODHOSED()  int { return 0x20 }
// Do not export these variables!
// var TBDB_STATS_STARTCLOCK
// var TBDB_STATS_SAVEDSWAPUID


// Jail.
func TBDB_JAILIPBASE()            string { return "172.16.0.0" }
func TBDB_JAILIPMASK()            string { return "255.240.0.0" }

// Reserved node "roles"
func TBDB_RSRVROLE_NODE()         string { return "node" }
func TBDB_RSRVROLE_VIRTHOST()     string { return "virthost" }
func TBDB_RSRVROLE_DELAYNODE()    string { return "delaynode" }
func TBDB_RSRVROLE_SIMHOST()      string { return "simhost" }

// Interfaces roles.
func TBDB_IFACEROLE_CONTROL()     string { return "ctrl" }
func TBDB_IFACEROLE_EXPERIMENT()  string { return "expt" }
func TBDB_IFACEROLE_JAIL()        string { return "jail" }
func TBDB_IFACEROLE_FAKE()        string { return "fake" }
func TBDB_IFACEROLE_GW()          string { return "gw" }
func TBDB_IFACEROLE_OTHER()       string { return "other" }
func TBDB_IFACEROLE_OUTER_CONTROL() string { return "outer_ctrl" }

// Routertypes.
func TBDB_ROUTERTYPE_NONE()       string { return "none" }
func TBDB_ROUTERTYPE_OSPF()       string { return "ospf" }
func TBDB_ROUTERTYPE_STATIC()     string { return "static" }
func TBDB_ROUTERTYPE_MANUAL()     string { return "manual" }

// User Interface types.
func TBDB_USER_INTERFACE_EMULAB() string { return "emulab" }
func TBDB_USER_INTERFACE_PLAB()   string { return "plab" }

// Key Stuff
func TBDB_EVENTKEY(pid,eid string)    string { return TBExptUserDir(pid, eid + "/tbdata/eventkey") }
func TBDB_WEBKEY(pid,eid string)      string { return TBExptUserDir(pid, eid + "/tbdata/webkey") }

// Security Levels.
func TBDB_SECLEVEL_GREEN()        int { return 0 }
func TBDB_SECLEVEL_BLUE()         int { return 1 }
func TBDB_SECLEVEL_YELLOW()       int { return 2 }
func TBDB_SECLEVEL_ORANGE()       int { return 3 }
func TBDB_SECLEVEL_RED()          int { return 4 }

// This is the level at which we get extremely cautious when swapping out
func TBDB_SECLEVEL_ZAPDISK()     int { return TBDB_SECLEVEL_YELLOW() }

// A hash of all tables that contain information about physical nodes - the
// value for each key is the list of columns that could contain the node's ID.
//

var TBDB_PHYSICAL_NODE_TABLES  = map[string][]string {
	"current_reloads"       : { "node_id" },
	"delays"                : { "node_id" },
	"iface_counters"        : { "node_id" },
	"interfaces"            : { "node_id" },
	"interface_settings"    : { "node_id" },
	"interface_state"       : { "node_id" },
	"last_reservation"      : { "node_id" },
	"linkdelays"            : { "node_id" },
	"location_info"         : { "node_id" },
	"next_reserve"          : { "node_id" },
	"node_activity"         : { "node_id" },
	"node_auxtypes"         : { "node_id" },
	"node_features"         : { "node_id" },
	"node_hostkeys"         : { "node_id" },
	"node_idlestats"        : { "node_id" },
	"node_status"           : { "node_id" },
	"node_rusage"           : { "node_id" },
	"nodeipportnum"         : { "node_id" },
	"nodelog"               : { "node_id" },
	"nodes"                 : { "node_id", "phys_nodeid" },
	"ntpinfo"               : { "node_id" },
	"outlets"               : { "node_id" },
	"partitions"            : { "node_id" },
	"port_counters"         : { "node_id" },
	"reserved"              : { "node_id" },
	"scheduled_reloads"     : { "node_id" },
	"state_triggers"        : { "node_id" },
	"switch_stacks"         : { "node_id" },
	"tiplines"              : { "node_id" },
	"tmcd_redirect"         : { "node_id" },
	"uidnodelastlogin"      : { "node_id" },
	"v2pmap"                : { "node_id" },
	"vinterfaces"           : { "node_id" },
	"widearea_nodeinfo"     : { "node_id" },
	"widearea_accounts"     : { "node_id" },
	"widearea_delays"       : { "node_id1", "node_id2" },
	"widearea_recent"       : { "node_id1", "node_id2" },
	"wires"                 : { "node_id1", "node_id2" },
	"node_startloc"         : { "node_id" },
	"node_history"          : { "node_id" },
	"node_bootlogs"         : { "node_id" },
	"node_utilization"      : { "node_id" },
}


func TBdbfork() {
	// TODO EventFork() // in lib/event.pm
}
func Power(a, b int) int64 {
	var p int64 =  1
	for b > 0 {
		if b&1 != 0 {
			p = p * int64(a)
		}
		b >>= 1
		a *= a
	}
	return p
}

//====================================================================================
//# Return the user's experiment directory name. This is a path in the /proj
//# tree. We keep these separate to avoid NFS issues, and users generally
//# messing with things they should not (by accident or otherwise).
//====================================================================================
func TBExptUserDir(pid, eid string) string {
	// TODO
	//query_result = DBQueryFatal("select path from experiments " +
	//"where pid='" + pid + "' and eid='" + eid + "'")
	//my ($path) = $query_result->fetchrow_array;
	// return $path;

	return "/proj/" + pid + "/exp/" + eid
}

type ExpRSRCtype struct {
	idx             int64 // auto_increment
	exptidx        	int64
	lastidx        	int64
	tstamp          time.Time // datetime
	uid_idx        	int64
	swapin_time    	int64
	swapout_time    int64
	swapmod_time   	int64
	byswapmod      	int
	byswapin       	int
	vnodes         	int
	pnodes         	int
	wanodes        	int
	plabnodes      	int
	simnodes       	int
	jailnodes      	int
	delaynodes     	int
	linkdelays     	int
	walinks        	int
	links          	int
	lans           	int
	shapedlinks    	int
	shapedlans     	int
	wirelesslans   	int
	minlinks       	int
	maxlinks       	int
	delay_capacity 	int
	batchmode      	int
	archive_tag		string
	Input_data_idx	int64
	thumbnail       os.File // mediumblob
}
type ExpSTATStype struct {
	// show columns from experiment_stats;
	Pid					string
	Pid_idx				int
	Eid 					string
	Eid_uuid				string
	Creator       			string
	Creator_idx			int
	Exptidx				int64
	Rsrcidx				int64
	Lastrsrc				int64
	Gid           			string
	Gid_idx       			int
	Created       			time.Time
	Destroyed     			time.Time
	Last_activity 			time.Time
	Swapin_count  			int
	Swapin_last   			time.Time
	Swapout_count 			int
	Swapout_last   		time.Time
	Swapmod_count  		int
	Swapmod_last   		time.Time
	Swap_errors    		int
	Swap_exitcode  		int
	Idle_swaps     		int
	Swapin_duration		int64
	Batch            		int
	Elabinelab        		int
	Elabinelab_exptidx		int64
	Security_level     	int
	Archive_idx        	int64
	Last_error         	int64
	Dpdbname           	string
}
type ExpType struct {
	// mysql> show columns from experiments
	Eid                   string
	Eid_uuid              string
	Pid_idx               int64
	Gid_idx               int64
	Pid                   string
	Gid                   string
	Creator_idx           int
	Swapper_idx           int64
	Expt_created          time.Time
	Expt_expires          time.Time
	Expt_name             string
	Expt_head_uid         string
	Expt_start            time.Time
	Expt_end              time.Time
	Expt_terminating      time.Time
	Expt_locked           time.Time
	Expt_swapped          time.Time
	Expt_swap_uid         string
	Swappable             int
	Priority              int
	Noswap_reason         string
	Idleswap              int
	Idleswap_timeout      int
	Noidleswap_reason     string
	Autoswap              int
	Autoswap_timeout      int
	Batchmode             int
	Shared                int
	State                 string
	Maximum_nodes         int
	Minimum_nodes         int
	Virtnode_count        int
	Testdb                string
	Path                  string
	Logfile               string
	Logfile_open          int
	Attempts              int
	Canceled              int
	Batchstate            int
	Event_sched_pid       int
	Prerender_pid         int64
	Uselinkdelays         int
	Forcelinkdelays       int
	Multiplex_factor      int
	Uselatestwadata       int
	Usewatunnels          int
	Wa_delay_solverweight float32
	Wa_bw_solverweight    float32
	Wa_plr_solverweight   float32
	Swap_requests         int
	Last_swap_req         time.Time
	Idle_ignore           int
	Sync_server           string
	Cpu_usage             int
	Mem_usage             int
	Keyhash               string // WAS int
	Eventkey              string
	Idx                   int64
	Sim_reswap_count      int
	Veth_encapsulate      int
	Encap_style           int // num('alias','veth','veth-ne','vlan','vtun','egre','gre','default')
	Allowfixnode          int
	Jail_osname           string
	Delay_osname          string
	Use_ipassign          int
	Ipassign_args         string
	Linktest_level        int
	Linktest_pid          int64
	Useprepass            int
	Usemodelnet           int
	Modelnet_cores        int
	Modelnet_edges        int
	Modelnetcore_osname   string
	Modelnetedge_osname   string
	elab_in_elab          int
	elabinelab_eid        string
	elabinelab_exptidx    int64
	elabinelab_cvstag     string
	elabinelab_nosetup    int
	elabinelab_singlenet  int
	Security_level        int
	Lockdown              int
	Paniced               int
	Panic_date            time.Time
	Delay_capacity        int
	Savedisk              int
	Locpiper_pid          int64
	Locpiper_port         int64
	Instance_idx          int64
	Dpdb                  int
	Dpdbname              string
	Dpdbpassword          string
	Geniflags             int64
}

type GROUPtype  struct {
	// mysql> show columns from groups;
	pid              string
	gid              string
	pid_idx          int
	gid_idx          int
	gid_uuid         string
	leader           string
	leader_idx       int
	created          time.Time
	description      string
	unix_gid         int
	unix_name        string
	expt_count       int
	expt_last        time.Time
	wikiname         string
	mailman_password string
}

type PROJECTtype struct {
	// mysql> show columns from projects;
	pid                    string
	pid_idx                int // 0
	status                 int // enum('approved','unapproved','archived') unapproved
	created                time.Time
	archived               time.Time
	expires                time.Time
	name                   string
	URL                    string
	org_type               string
	research_type          string
	funders                string
	addr                   string
	head_uid               string
	head_idx               int // 0
	why                    string
	control_node           string
	unix_gid               int
	public                 int // 0
	public_whynot          string
	expt_count             int
	expt_last              time.Time
	pcremote_ok            string // set('pcplabphys','pcron','pcwa')
	default_user_interface  int   // enum('emulab','plab') , emulab
	cvsrepo_public         int
	allow_workbench        int
	class                  int
}
/*
mysql> describe experiments;
+-----------------------+---------------------------------------------------------------------+------+-----+---------+----------------+
| Field                 | Type                                                                | Null | Key | Default | Extra          |
+-----------------------+---------------------------------------------------------------------+------+-----+---------+----------------+
| eid                   | varchar(32)                                                         | NO   |     |         |                |
| eid_uuid              | varchar(40)                                                         | NO   | MUL |         |                |
| pid_idx               | mediumint(8) unsigned                                               | NO   | MUL | 0       |                |
| gid_idx               | mediumint(8) unsigned                                               | NO   |     | 0       |                |
| pid                   | varchar(12)                                                         | NO   | MUL |         |                |
| gid                   | varchar(16)                                                         | NO   |     |         |                |
| creator_idx           | mediumint(8) unsigned                                               | NO   |     | 0       |                |
| swapper_idx           | mediumint(8) unsigned                                               | YES  |     | NULL    |                |
| expt_created          | datetime                                                            | YES  |     | NULL    |                |
| expt_expires          | datetime                                                            | YES  |     | NULL    |                |
| expt_name             | tinytext                                                            | YES  |     | NULL    |                |
| expt_head_uid         | varchar(8)                                                          | NO   |     |         |                |
| expt_start            | datetime                                                            | YES  |     | NULL    |                |
| expt_end              | datetime                                                            | YES  |     | NULL    |                |
| expt_terminating      | datetime                                                            | YES  |     | NULL    |                |
| expt_locked           | datetime                                                            | YES  |     | NULL    |                |
| expt_swapped          | datetime                                                            | YES  |     | NULL    |                |
| expt_swap_uid         | varchar(8)                                                          | NO   |     |         |                |
| swappable             | tinyint(4)                                                          | NO   |     | 0       |                |
| priority              | tinyint(4)                                                          | NO   |     | 0       |                |
| noswap_reason         | tinytext                                                            | YES  |     | NULL    |                |
| idleswap              | tinyint(4)                                                          | NO   |     | 0       |                |
| idleswap_timeout      | int(4)                                                              | NO   |     | 0       |                |
| noidleswap_reason     | tinytext                                                            | YES  |     | NULL    |                |
| autoswap              | tinyint(4)                                                          | NO   |     | 0       |                |
| autoswap_timeout      | int(4)                                                              | NO   |     | 0       |                |
| batchmode             | tinyint(4)                                                          | NO   | MUL | 0       |                |
| shared                | tinyint(4)                                                          | NO   |     | 0       |                |
| state                 | varchar(16)                                                         | NO   | MUL | new     |                |
| maximum_nodes         | int(6) unsigned                                                     | YES  |     | NULL    |                |
| minimum_nodes         | int(6) unsigned                                                     | YES  |     | NULL    |                |
| virtnode_count        | int(6) unsigned                                                     | YES  |     | NULL    |                |
| testdb                | tinytext                                                            | YES  |     | NULL    |                |
| path                  | tinytext                                                            | YES  |     | NULL    |                |
| logfile               | tinytext                                                            | YES  |     | NULL    |                |
| logfile_open          | tinyint(4)                                                          | NO   |     | 0       |                |
| attempts              | smallint(5) unsigned                                                | NO   |     | 0       |                |
| canceled              | tinyint(4)                                                          | NO   |     | 0       |                |
| batchstate            | varchar(16)                                                         | YES  |     | NULL    |                |
| event_sched_pid       | int(11)                                                             | YES  |     | 0       |                |
| prerender_pid         | int(11)                                                             | YES  |     | 0       |                |
| uselinkdelays         | tinyint(4)                                                          | NO   |     | 0       |                |
| forcelinkdelays       | tinyint(4)                                                          | NO   |     | 0       |                |
| multiplex_factor      | smallint(5)                                                         | YES  |     | NULL    |                |
| uselatestwadata       | tinyint(4)                                                          | NO   |     | 0       |                |
| usewatunnels          | tinyint(4)                                                          | NO   |     | 1       |                |
| wa_delay_solverweight | float                                                               | YES  |     | 0       |                |
| wa_bw_solverweight    | float                                                               | YES  |     | 0       |                |
| wa_plr_solverweight   | float                                                               | YES  |     | 0       |                |
| swap_requests         | tinyint(4)                                                          | NO   |     | 0       |                |
| last_swap_req         | datetime                                                            | YES  |     | NULL    |                |
| idle_ignore           | tinyint(4)                                                          | NO   |     | 0       |                |
| sync_server           | varchar(32)                                                         | YES  |     | NULL    |                |
| cpu_usage             | tinyint(4) unsigned                                                 | NO   |     | 0       |                |
| mem_usage             | tinyint(4) unsigned                                                 | NO   |     | 0       |                |
| keyhash               | varchar(64)                                                         | YES  |     | NULL    |                |
| eventkey              | varchar(64)                                                         | YES  |     | NULL    |                |
| idx                   | int(10) unsigned                                                    | NO   | PRI | NULL    | auto_increment |
| sim_reswap_count      | smallint(5) unsigned                                                | NO   |     | 0       |                |
| veth_encapsulate      | tinyint(4)                                                          | NO   |     | 1       |                |
| encap_style           | enum('alias','veth','veth-ne','vlan','vtun','egre','gre','default') | NO   |     | default |                |
| allowfixnode          | tinyint(4)                                                          | NO   |     | 1       |                |
| jail_osname           | varchar(20)                                                         | YES  |     | NULL    |                |
| delay_osname          | varchar(20)                                                         | YES  |     | NULL    |                |
| use_ipassign          | tinyint(4)                                                          | NO   |     | 0       |                |
| ipassign_args         | varchar(255)                                                        | YES  |     | NULL    |                |
| linktest_level        | tinyint(4)                                                          | NO   |     | 0       |                |
| linktest_pid          | int(11)                                                             | YES  |     | 0       |                |
| useprepass            | tinyint(1)                                                          | NO   |     | 0       |                |
| usemodelnet           | tinyint(1)                                                          | NO   |     | 0       |                |
| modelnet_cores        | tinyint(4) unsigned                                                 | NO   |     | 0       |                |
| modelnet_edges        | tinyint(4) unsigned                                                 | NO   |     | 0       |                |
| modelnetcore_osname   | varchar(20)                                                         | YES  |     | NULL    |                |
| modelnetedge_osname   | varchar(20)                                                         | YES  |     | NULL    |                |
| elab_in_elab          | tinyint(1)                                                          | NO   |     | 0       |                |
| elabinelab_eid        | varchar(32)                                                         | YES  |     | NULL    |                |
| elabinelab_exptidx    | int(11)                                                             | YES  |     | NULL    |                |
| elabinelab_cvstag     | varchar(64)                                                         | YES  |     | NULL    |                |
| elabinelab_nosetup    | tinyint(1)                                                          | NO   |     | 0       |                |
| elabinelab_singlenet  | tinyint(1)                                                          | NO   |     | 0       |                |
| security_level        | tinyint(1)                                                          | NO   |     | 0       |                |
| lockdown              | tinyint(1)                                                          | NO   |     | 0       |                |
| paniced               | tinyint(1)                                                          | NO   |     | 0       |                |
| panic_date            | datetime                                                            | YES  |     | NULL    |                |
| delay_capacity        | tinyint(3) unsigned                                                 | YES  |     | NULL    |                |
| savedisk              | tinyint(1)                                                          | NO   |     | 0       |                |
| locpiper_pid          | int(11)                                                             | YES  |     | 0       |                |
| locpiper_port         | int(11)                                                             | YES  |     | 0       |                |
| instance_idx          | int(10) unsigned                                                    | NO   |     | 0       |                |
| dpdb                  | tinyint(1)                                                          | NO   |     | 0       |                |
| dpdbname              | varchar(64)                                                         | YES  |     | NULL    |                |
| dpdbpassword          | varchar(64)                                                         | YES  |     | NULL    |                |
| geniflags             | int(11)                                                             | NO   |     | 0       |                |
+-----------------------+---------------------------------------------------------------------+------+-----+---------+----------------+
*/
/*
mysql> describe experiment_template_instances;
+---------------+-----------------------+------+-----+---------+----------------+
| Field         | Type                  | Null | Key | Default | Extra          |
+---------------+-----------------------+------+-----+---------+----------------+
| idx           | int(10) unsigned      | NO   | PRI | NULL    | auto_increment |
| parent_guid   | varchar(16)           | NO   | MUL |         |                |
| parent_vers   | smallint(5) unsigned  | NO   |     | 0       |                |
| exptidx       | int(10) unsigned      | NO   | MUL | 0       |                |
| pid           | varchar(12)           | NO   | MUL |         |                |
| eid           | varchar(32)           | NO   |     |         |                |
| uid           | varchar(8)            | NO   |     |         |                |
| uid_idx       | mediumint(8) unsigned | NO   |     | 0       |                |
| logfileid     | varchar(40)           | YES  |     | NULL    |                |
| description   | tinytext              | YES  |     | NULL    |                |
| start_time    | datetime              | YES  |     | NULL    |                |
| stop_time     | datetime              | YES  |     | NULL    |                |
| continue_time | datetime              | YES  |     | NULL    |                |
| runtime       | int(10) unsigned      | YES  |     | 0       |                |
| pause_time    | datetime              | YES  |     | NULL    |                |
| runidx        | int(10) unsigned      | YES  |     | NULL    |                |
| template_tag  | varchar(64)           | YES  |     | NULL    |                |
| export_time   | datetime              | YES  |     | NULL    |                |
| locked        | datetime              | YES  |     | NULL    |                |
| locker_pid    | int(11)               | YES  |     | 0       |                |
+---------------+-----------------------+------+-----+---------+----------------+
*/
