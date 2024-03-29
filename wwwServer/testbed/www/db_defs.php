<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2009 University of Utah and the Flux Group.
# All rights reserved.

### GORAN
# Enforce the correct driver
#if (extension_loaded('mysql')) {
#    echo "<b>You must disable the PHP <i>mysql</i> extension - pkg deinstall php56-mysqli";
#    exit(1);
#}

### GORAN
#if (!extension_loaded('mysqli')) {
#    echo "<b>You must enable the PHP <i>mysqli</i> extension by installing pkg php56-mysqli-5.6.30";
#    exit(1);
#}

#
# Database Constants
#
require("db_mysqli.php");
require("dbcheck.php");

$TBDBNAME       = "tbdb";
$TBOPSPID	= "emulab-ops";
$NODEDEAD_PID   = $TBOPSPID;
$NODEDEAD_EID   = "hwdown";
$FIRSTUSER      = "elabman";
$NODERELOADING_PID	= $TBOPSPID;
$NODERELOADING_EID	= "reloading";
$NODERELOADPENDING_EID	= "reloadpending";

# All these constants need to go at some point, replaced by data from
# the regex table. 
$TBDB_UIDLEN    = 8;
$TBDB_PIDLEN    = 12;
$TBDB_GIDLEN	= 12;
$TBDB_UNIXGLEN	= 16;
$TBDB_NODEIDLEN = 10;
$TBDB_PHONELEN  = 32;
$TBDB_USRNAMELEN= 64;
$TBDB_EMAILLEN  = 64;
$TBDB_MMLENGTH  = 64;
$TBDB_ARCHIVE_TAGLEN = 64;
$TBDB_ARCHIVE_MSGLEN = 2048;

#
# Current policy is to prefix the EID with the PID. Make sure it is not
# too long for the database. PID is 12, and the max is 32, so the user
# cannot have provided an EID more than 19, since other parts of the system
# may concatenate them together with a hyphen.
#
$TBDB_EIDLEN    = 19;

$TBDB_OSID_OSIDLEN         = 35;
$TBDB_OSID_OSNAMELEN       = 20;
$TBDB_OSID_VERSLEN         = 12;
$TBDB_IMAGEID_IMAGEIDLEN   = 45;
$TBDB_IMAGEID_IMAGENAMELEN = 30;

#
# User status field.
#
define("TBDB_USERSTATUS_ACTIVE",	"active");
define("TBDB_USERSTATUS_NEWUSER",	"newuser");
define("TBDB_USERSTATUS_UNAPPROVED",	"unapproved");
define("TBDB_USERSTATUS_UNVERIFIED",	"unverified");
define("TBDB_USERSTATUS_FROZEN",	"frozen");
define("TBDB_USERSTATUS_ARCHIVED",	"archived");

#
# Type of new account.
#
define("TBDB_NEWACCOUNT_REGULAR",	0x0);
define("TBDB_NEWACCOUNT_PROJLEADER",	0x1);
define("TBDB_NEWACCOUNT_WEBONLY",	0x4);

#
# Trust. Define the trust level as an increasing value. Then define a
# function to return whether the given trust is high enough.
#
$TBDB_TRUST_NONE		= 0;
$TBDB_TRUST_USER		= 1;
$TBDB_TRUST_LOCALROOT		= 2;
$TBDB_TRUST_GROUPROOT		= 3;
$TBDB_TRUST_PROJROOT		= 4;
$TBDB_TRUST_ADMIN		= 5;

#
# Text strings in the DB for above.
# 
define("TBDB_TRUSTSTRING_NONE",		"none");
define("TBDB_TRUSTSTRING_USER",		"user");
define("TBDB_TRUSTSTRING_LOCALROOT",	"local_root");
define("TBDB_TRUSTSTRING_GROUPROOT",	"group_root");
define("TBDB_TRUSTSTRING_PROJROOT",	"project_root");

#
# These are the permission types. Different operations for the varying
# types of things we need to control access to.
#
# Things you can do to a node.
$TB_NODEACCESS_READINFO		= 1;
$TB_NODEACCESS_MODIFYINFO	= 2;
$TB_NODEACCESS_LOADIMAGE	= 3;
$TB_NODEACCESS_REBOOT		= 4;
$TB_NODEACCESS_POWERCYCLE	= 5;
$TB_NODEACCESS_MIN		= $TB_NODEACCESS_READINFO;
$TB_NODEACCESS_MAX		= $TB_NODEACCESS_POWERCYCLE;

# User Info (modinfo web page, etc).
$TB_USERINFO_READINFO		= 1;
$TB_USERINFO_MODIFYINFO		= 2;
$TB_USERINFO_MIN		= $TB_USERINFO_READINFO;
$TB_USERINFO_MAX		= $TB_USERINFO_MODIFYINFO;

# Experiments (also batch experiments).
$TB_EXPT_READINFO		= 1;
$TB_EXPT_MODIFY			= 2;	# Allocate/dealloc nodes
$TB_EXPT_DESTROY		= 3;
$TB_EXPT_UPDATE			= 4;
$TB_EXPT_MIN			= $TB_EXPT_READINFO;
$TB_EXPT_MAX			= $TB_EXPT_UPDATE;

# Projects.
$TB_PROJECT_READINFO		= 1;
$TB_PROJECT_MAKEGROUP		= 2;
$TB_PROJECT_EDITGROUP		= 3;
$TB_PROJECT_GROUPGRABUSERS      = 4;
$TB_PROJECT_BESTOWGROUPROOT     = 5;
$TB_PROJECT_DELGROUP		= 6;
$TB_PROJECT_LEADGROUP		= 7;
$TB_PROJECT_ADDUSER		= 8;
$TB_PROJECT_DELUSER             = 9;
$TB_PROJECT_MAKEOSID		= 10;
$TB_PROJECT_DELOSID		= 11;
$TB_PROJECT_MAKEIMAGEID		= 12;
$TB_PROJECT_DELIMAGEID		= 13;
$TB_PROJECT_CREATEEXPT		= 14;
$TB_PROJECT_MIN			= $TB_PROJECT_READINFO;
$TB_PROJECT_MAX			= $TB_PROJECT_CREATEEXPT;

# OSIDs 
$TB_OSID_READINFO		= 1;
$TB_OSID_MODIFYINFO		= 2;
$TB_OSID_DESTROY		= 3;
$TB_OSID_MIN			= $TB_OSID_READINFO;
$TB_OSID_MAX			= $TB_OSID_DESTROY;

# ImageIDs
$TB_IMAGEID_READINFO		= 1;
$TB_IMAGEID_MODIFYINFO		= 2;
$TB_IMAGEID_ACCESS		= 3;
$TB_IMAGEID_DESTROY		= 4;
$TB_IMAGEID_MIN			= $TB_IMAGEID_READINFO;
$TB_IMAGEID_MAX			= $TB_IMAGEID_DESTROY;

# Experiment states (that matter to us).
$TB_EXPTSTATE_NEW		= "new"; 
$TB_EXPTSTATE_PRERUN		= "prerunning"; 
$TB_EXPTSTATE_SWAPPING		= "swapping";
$TB_EXPTSTATE_SWAPPED		= "swapped";
$TB_EXPTSTATE_ACTIVATING	= "activating";
$TB_EXPTSTATE_ACTIVE		= "active";
$TB_EXPTSTATE_PANICED		= "paniced";
$TB_EXPTSTATE_QUEUED		= "queued";
$TB_EXPTSTATE_MODIFY_RESWAP	= "modify_reswap";

# Interfaces roles.
define("TBDB_IFACEROLE_CONTROL",	"ctrl");
define("TBDB_IFACEROLE_EXPERIMENT",	"expt");
define("TBDB_IFACEROLE_JAIL",		"jail");
define("TBDB_IFACEROLE_FAKE",		"fake");
define("TBDB_IFACEROLE_GW",		"gw");
define("TBDB_IFACEROLE_OTHER",		"other");
define("TBDB_IFACEROLE_OUTER_CONTROL",  "outer_ctrl");

# Node states that the web page cares about.
define("TBDB_NODESTATE_ISUP",		"ISUP");
define("TBDB_NODESTATE_PXEWAIT",	"PXEWAIT");
define("TBDB_NODESTATE_POWEROFF",	"POWEROFF");
define("TBDB_NODESTATE_ALWAYSUP",	"ALWAYSUP");

# User Interface types
define("TBDB_USER_INTERFACE_EMULAB",	"emulab");
define("TBDB_USER_INTERFACE_PLAB",	"plab");
$TBDB_USER_INTERFACE_LIST = array(TBDB_USER_INTERFACE_EMULAB,
				  TBDB_USER_INTERFACE_PLAB);

# Linktest levels.
$linktest_levels	= array();
$linktest_levels[0]	= "Skip Linktest";
$linktest_levels[1]	= "Connectivity and Latency";
$linktest_levels[2]	= "Plus Static Routing";
$linktest_levels[3]	= "Plus Loss";
$linktest_levels[4]	= "Plus Bandwidth";
define("TBDB_LINKTEST_MAX", 4);

#
# Convert a trust string to the above numeric values.
#
function TBTrustConvert($trust_string)
{
    global $TBDB_TRUST_NONE;
    global $TBDB_TRUST_USER;
    global $TBDB_TRUST_LOCALROOT;
    global $TBDB_TRUST_GROUPROOT;
    global $TBDB_TRUST_PROJROOT;
    global $TBDB_TRUST_ADMIN;
    $trust_value = 0;

    #
    # Convert string to value. Perhaps the DB should have done it this way?
    # 
    if (strcmp($trust_string, "none") == 0) {
	    $trust_value = $TBDB_TRUST_NONE;
    }
    elseif (strcmp($trust_string, "user") == 0) {
	    $trust_value = $TBDB_TRUST_USER;
    }
    elseif (strcmp($trust_string, "local_root") == 0) {
	    $trust_value = $TBDB_TRUST_LOCALROOT;
    }
    elseif (strcmp($trust_string, "group_root") == 0) {
	    $trust_value = $TBDB_TRUST_GROUPROOT;
    }
    elseif (strcmp($trust_string, "project_root") == 0) {
	    $trust_value = $TBDB_TRUST_PROJROOT;
    }
    elseif (strcmp($trust_string, "admin") == 0) {
	    $trust_value = $TBDB_TRUST_ADMIN;
    }
    else {
	    TBERROR("Invalid trust value $trust_string!", 1);
    }

    return $trust_value;
}

#
# Return true if the given trust string is >= to the minimum required.
# The trust value can be either numeric or a string; if a string its
# first converted to the numeric equiv.
#
function TBMinTrust($trust_value, $minimum)
{
    global $TBDB_TRUST_NONE;
    global $TBDB_TRUST_ADMIN;

    if ($minimum < $TBDB_TRUST_NONE || $minimum > $TBDB_TRUST_ADMIN) {
	    TBERROR("Invalid minimum trust $minimum!", 1);
    }

    #
    # Sleazy?
    #
    if (gettype($trust_value) == "string") {
	$trust_value = TBTrustConvert($trust_value);
    }
    
    return $trust_value >= $minimum;
}

#
# Confirm a valid node type
#
# usage TBValidNodeType($type)
#       returns 1 if valid
#       returns 0 if not valid
#
function TBValidNodeType($type)
{
    $query_result =
	DBQueryFatal("select type from node_types where type='$type'");

    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    return 1;
}

function TBUidNodeLastLogin($uid)
{
    return 0;		# DETER nodes don't syslog to users.

    $query_result =
	DBQueryFatal("select * from uidnodelastlogin where uid='$uid'");

    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row   = mysql_fetch_array($query_result);
    return $row;
}

#
# Return the last login for the users node.
# 
function TBUsersLastLogin($uid)
{
    global $USERNODE;

    $query_result =
	DBQueryFatal("select uid, date, time from uidnodelastlogin where uid='$uid' and node_id='$USERNODE'");

    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row   = mysql_fetch_array($query_result);
    return $row;
}

#
# Number of globally free PCs. See ptopgen and libdb for corresponding
# usage of the eventstate. A node is not really considered free for use
# unless it is also in the ISUP/PXEWAIT state.
#
function TBFreePCs()
{
    $query_result =
	DBQueryFatal("select count(a.node_id) from nodes as a ".
		     "left join reserved as b on a.node_id=b.node_id ".
		     "left join node_types as nt on a.type=nt.type ".
		     "left join nodetypeXpid_permissions as p ".
		     "  on a.type=p.type ".
                     "left join node_type_attributes as nta ".
                     "  on a.type=nta.type and nta.attrkey = 'special_hw' ".
		     "where b.node_id is null and a.role='testnode' ".
                     "  and a.reserved_pid is null ".
                     "  and nt.class = 'pc' and p.pid is null ".
                     "  and nta.attrvalue != 1 and ".
                     "      (a.eventstate='" . TBDB_NODESTATE_ISUP . "' or ".
                     "       a.eventstate='" . TBDB_NODESTATE_POWEROFF . "' or ".
                     "       a.eventstate='" . TBDB_NODESTATE_PXEWAIT . "') and".
		     "      (p.pid is null)");
    
    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row = mysql_fetch_row($query_result);
    return $row[0];
}

function TBTotalPCs()
{
    $query_result =
	DBQueryFatal("select count(n.node_id) from nodes as n ".
		     "left join node_types as nt on n.type=nt.type ".
		     "left join reserved as r on n.node_id=r.node_id ".
		     "left join node_type_attributes as nta ".
		     "  on n.type=nta.type and nta.attrkey = 'special_hw' ".
		     "where nt.class='pc' and nta.attrvalue!=1");
    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row = mysql_fetch_row($query_result);
    return $row[0];
}

function TBReservedPCs()
{
    $query_result =
	DBQueryFatal("select count(a.node_id) from nodes as a ".
		     "left join reserved as b on a.node_id=b.node_id ".
		     "left join node_types as nt on a.type=nt.type ".
		     "left join nodetypeXpid_permissions as p ".
		     "  on a.type=p.type ".
		     "where b.node_id is null and a.role='testnode' ".
                     "  and a.reserved_pid is not null ".
		     "  and nt.class = 'pc' and p.pid is null and ".
                     "      (a.eventstate='" . TBDB_NODESTATE_ISUP . "' or ".
                     "       a.eventstate='" . TBDB_NODESTATE_POWEROFF . "' or ".
                     "       a.eventstate='" . TBDB_NODESTATE_PXEWAIT . "') and".
		     "      (p.pid is null)");
    
    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row = mysql_fetch_row($query_result);
    return $row[0];
}

#
# Number of logged in users
#
function TBLoggedIn()
{
    $query_result =
	DBQueryFatal("select count(distinct uid) from login ".
		     "where timeout > UNIX_TIMESTAMP(now())");

    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row = mysql_fetch_row($query_result);
    return $row[0];
}

#
# Number of active experiments.
#
function TBActiveExperiments()
{
    $query_result =
	DBQueryFatal("select count(*) from experiments where ".
		     "state='active' and pid!='emulab-ops' and ".
		     "pid!='testbed'");
    
    if (mysql_num_rows($query_result) != 1) {
	return 0;
    }
    $row = mysql_fetch_array($query_result);
    return $row[0];
}

#
# Number of PCs reloading.
#
function TBReloadingPCs()
{
    global $NODERELOADING_PID, $NODERELOADING_EID, $NODERELOADPENDING_EID;

    $query_result =
	DBQueryFatal("select count(*) from reserved ".
		     "where pid='$NODERELOADING_PID' and ".
		     "      (eid='$NODERELOADING_EID' or ".
		     "       eid='$NODERELOADPENDING_EID')");
    
    if (mysql_num_rows($query_result) == 0) {
	return 0;
    }
    $row = mysql_fetch_row($query_result);
    return $row[0];
}

#
# Check if a site-specific variable exists. 
#
# usage: TBSiteVarExists($name)
#        returns 1 if variable exists;
#        returns 0 otherwise.
#
function TBSiteVarExists($name)
{
    global $lastact_query;

    $name  = addslashes( $name );

    $query_result = 
	DBQueryFatal("select name from sitevariables ".
		     "where name='$name'");

    if (mysql_num_rows($query_result) > 0) {
	return 1;
    } else {
	return 0;
    }
}

#
# Get site-specific variable.
# Get the value of the variable, or the default value if
# the value is undefined (NULL).
#
# usage: TBGetSiteVar($name)
#        returns value if variable is defined;
#        dies otherwise.
#
function TBGetSiteVar($name)
{
    global $lastact_query;

    $name  = addslashes( $name );

    $query_result = 
	DBQueryFatal("select value, defaultvalue from sitevariables ".
		     "where name='$name'");

    if (mysql_num_rows($query_result) > 0) {    
	$row = mysql_fetch_array($query_result);

	$value = $row["value"];
	$defaultvalue = $row["defaultvalue"];
	if (isset($value)) { return $value; }
	if (isset($defaultvalue)) { return $defaultvalue; }
    }
    
    TBERROR("Attempted to fetch unknown site variable '$name'!", 1);
}

#
# Count available planetlab nodes.
#
# usage: TBPlabAvail()
#        returns the number of free PlanetLab nodes of each type
#        returns an empty array on error
#
function TBPlabAvail() {
    $types = array();
    #
    # We have to do this in two queries, due to the fact that we do pcplabtypes
    # different from the way we do other types (it's on the vnodes, not in the
    # node_autypes for the pnode.)
    #
    # XXX - hardcodes hwdown and emulab-ops
    #
    $tables = "nodes AS n " .
	      "LEFT JOIN widearea_nodeinfo AS w ON n.phys_nodeid = w.node_id " .
              "LEFT JOIN node_auxtypes AS na ON n.node_id = na.node_id " .
	      "LEFT JOIN reserved AS r ON n.phys_nodeid = r.node_id " .
	      "LEFT JOIN node_status AS ns ON n.phys_nodeid = ns.node_id " .
	      "LEFT JOIN node_features AS nf ON n.phys_nodeid = nf.node_id " .
	      "LEFT JOIN node_features AS nf2 ON n.phys_nodeid = nf2.node_id";
    $available = "ns.status='up' AND (nf.feature='+load' AND nf.weight < 1.0) " .
                 "AND (nf2.feature='+disk' and nf2.weight < 1.0) " .
		 "AND !(r.pid = 'emulab-ops' and r.eid = 'hwdown')";

    #
    # Grab pcplab nodes
    #
    $query_result = DBQueryFatal("SELECT count(*), count(distinct w.site) " .
                                 "FROM $tables " .
				 "WHERE (n.type='pcplabphys') ".
				 "    AND($available)");
    if (mysql_num_rows($query_result)) {
	$row = mysql_fetch_row($query_result);
	$types['pcplab'] = array($row[0],$row[1]);
    }

    #
    # Grab the more specific types
    #
    $query_result = DBQueryFatal("SELECT na.type, count(*), " .
				 "count(distinct w.site) FROM $tables " .
				 "WHERE (n.type='pcplabphys') " .
				 "    AND ($available) " .
				 "GROUP BY na.type");
    while ($row = mysql_fetch_row($query_result)) {
	$types[$row[0]] = array($row[1],$row[2]);
    }

    return $types;
}

#
# Return firstinit state.
#
function TBGetFirstInitState()
{
    $firstinit = TBGetSiteVar("general/firstinit/state");
    if ($firstinit == "Ready")
	return null;
    return $firstinit;
}
function TBSetFirstInitState($newstate)
{
    $query_result = 
	DBQueryFatal("update sitevariables set value='$newstate' ".
		     "where name='general/firstinit/state'");
}
function TBGetFirstInitPid()
{
    return TBGetSiteVar("general/firstinit/pid");
}
function TBSetFirstInitPid($pid)
{
    $query_result = 
	DBQueryFatal("update sitevariables set value='$pid' ".
		     "where name='general/firstinit/pid'");
}

#
# Get the build and source version numbers, as for the banner.
#
function TBGetVersionInfo(&$major, &$minor, &$build)
{
    $query_result = 
	DBQueryFatal("select value from sitevariables ".
		     "where name='general/version/build'");

    if (mysql_num_rows($query_result)) {
	$row = mysql_fetch_row($query_result);
	$build = $row[0];
    }
    else {
	$build = "Unknown";
    }

    $query_result =
	DBQuery("select value from version_info where name='dbrev'");
    if ($query_result && mysql_num_rows($query_result)) {
	$row = mysql_fetch_row($query_result);
	list($a,$b) = preg_split('/\./', $row[0]);
	$a = (isset($a) && $a != "" ? $a : "x");
	$b = (isset($b) && $b != "" ? $b : "y");
	$major = $a;
	$minor = $b;
    }
    else {
	$major = "X";
	$minor = "Y";
    }
    return 1;
}

#
# Return a node_tye attribute entry.
#
function NodeTypeAttribute($type, $key, &$value)
{
    $query_result =
	DBQueryFatal("select attrvalue from node_type_attributes ".
		     "where type='$type' and attrkey='$key'");
    
    if (!mysql_num_rows($query_result)) {
	$value = null;
	return 0;
    }

    $row = mysql_fetch_row($query_result);
    $value = $row[0];
    return 1;
}

#
# Return a unique index from emulab_indicies for the indicated name.
# Updates the index to be, well, unique.
# Eats flaming death on error.
#
function TBGetUniqueIndex($name)
{
    #
    # Lock the table to avoid conflicts
    #
    DBQueryFatal("lock tables emulab_indicies write");

    $query_result =
	DBQueryFatal("select idx from emulab_indicies ".
		     "where name='$name'");

    $row = mysql_fetch_array($query_result);
    $curidx = $row["idx"];
    if (!isset($curidx)) {
	$curidx = 1;
    }

    $nextidx = $curidx + 1;

    DBQueryFatal("replace into emulab_indicies (name, idx) ".
		 "values ('$name', $nextidx)");
    DBQueryFatal("unlock tables");

    return $curidx;
}

#
# Trivial wrapup of Logile table so we can use it in url_defs.
# 
class Logfile
{
    var	$logfile;

    #
    # Constructor by lookup on unique index.
    #
    ### GORAN function Logfile($logid) {
    function __construct($logid) {
	$safe_id = addslashes($logid);

	$query_result =
	    DBQueryWarn("select * from logfiles ".
			"where logid='$safe_id'");

	if (!$query_result || !mysql_num_rows($query_result)) {
	    $this->logfile = NULL;
	    return;
	}
	$this->logfile   = mysql_fetch_array($query_result);
    }

    # Hmm, how does one cause an error in a php constructor?
    function IsValid() {
	return !is_null($this->logfile);
    }

    # Lookup by ID
    static function Lookup($logid) {
	$foo = new Logfile($logid);

	if (!$foo || !$foo->IsValid())
	    return null;

	return $foo;
    }

    # accessors
    function field($name) {
	return (is_null($this->logfile) ? -1 : $this->logfile[$name]);
    }
    function logid()	     { return $this->field("logid"); }
    function filename()	     { return $this->field("filename"); }
    function isopen()        { return $this->field("isopen"); }
    function uid_idx()       { return $this->field("uid_idx"); }
    function gid_idx()       { return $this->field("gid_idx"); }
}

#
# DB Interface.
#
$maxtries = 3;
$DBlinkid = 0;
while ($maxtries) {
    $DBlinkid = mysql_connect("localhost",  basename($_SERVER['SCRIPT_NAME']));
    if ($DBlinkid) {
	break;
    }
    $maxtries--;
    sleep(1);
}
if (! $DBlinkid) {
    USERERROR("Temporary resource error; ".
	      "please try again in a few minutes", 1);
}
if (!mysql_select_db($TBDBNAME, $DBlinkid)) {
    TBERROR("Could not select DB after connecting!", 1);
}

#
# Connect to alternate DB.
#
function DBConnect($dbname)
{
    global $SCRIPT_NAME;

    $linkid = mysql_connect("localhost",  basename($SCRIPT_NAME),  "none");
    if ($linkid) {
	if (!mysql_select_db($dbname, $linkid)) {
	    return null;
	}
    }
    return $linkid;
}

#
# Record last DB error string.
#
$DBErrorString = "";

#
# This mirrors the routine in the PERL code. The point is to avoid
# writing the same thing repeatedly, get consistent error handling,
# and make sure that mail is sent to the testbed list when things go
# wrong!
#
# Argument is a string. Returns the actual query object, so it is up to
# the caller to test it. I would not for one moment view this as
# encapsulation of the DB interface. 
# 
# usage: DBQuery(char *str)
#        returns the query object result.
#
# Sets $DBErrorString is case of error; saving the original query string and
# the error string from the DB module. Use DBFatal (below) to print/email
# that string, and then exit.
#
function DBQuery($query, $linkid = NULL)
{
    global	$TBDBNAME;
    global	$DBErrorString;
    global      $DBlinkid;

    $linkid = is_null($linkid) ? $DBlinkid : $linkid;
    
    # Support for SQL-injection vulnerability checking.  Labeled probe strings
    # should be caught in page input argument checking before they get here.
    $lbl = strpos($query, "**{");
    if ( $lbl !== FALSE ) {
	$end = strpos($query, "}**") + 3;
	# Look for a preceeding single quote, and see if it's backslashed.
	if ( substr($query, $lbl-1, 1) == "'" ) {
	    $lbl--;
	    if ( substr($query, $lbl-1, 1) == '\\' ) $lbl--;
	}
	USERERROR("Probe label: " . substr($query, $lbl, $end-$lbl), 1);
    }

## Note mysqli_query($linkid, $query) vs mysql_query($query) 
    $result = mysql_query($query);

    if (! $result) {
	$DBErrorString =
	    "  Query: $query\n".
	    "  Error: " . mysql_error($linkid);
    }
    return $result;
}

#
# Same as above, but die on error. 
# 
function DBQueryFatal($query, $linkid = NULL)
{
    $result = DBQuery($query, $linkid);

    if (! $result) {
	DBFatal("DB Query failed");
    }
    return $result;
}

#
# Same as above, but just send email on error. This info is useful
# to the TB system, but the caller has to retain control.
# 
function DBQueryWarn($query, $linkid = NULL)
{
    $result = DBQuery($query, $linkid);

    if (! $result) {
	DBWarn("DB Query failed");
    }
    return $result;
}

#
# Warn and send email after a failed DB query. First argument is the error
# message to send. The contents of $DBErrorString is also sent. We do not
# print this stuff back to the user since we might leak stuff out that we
# should not.
# 
# usage: DBWarn(char *message)
#
function DBWarn($message)
{
    global	$PHP_SELF, $DBErrorString;
    
    $text = "$message - In $PHP_SELF\n" .
  	    "$DBErrorString\n";

    TBERROR($text, 0);
}

#
# Same as above, but die after the warning.
# 
# usage: DBFatal(char *message);
#
function DBFatal($message)
{
    global	$PHP_SELF, $DBErrorString;

    $text = "$message - In $PHP_SELF\n" .
  	    "$DBErrorString\n";

    TBERROR($text, 1);
}

#
# Return the number of affected rows, for the last query. Why is this
# not stored in the query result?
# 
function DBAffectedRows($linkid = NULL)
{
    global      $DBlinkid;

    $linkid = is_null($linkid) ? $DBlinkid : $linkid;

    return mysql_affected_rows($linkid);
}

?>
