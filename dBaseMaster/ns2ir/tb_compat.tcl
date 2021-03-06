# -*- tcl -*-
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006, 2008, 2009 University of Utah and the Flux Group.
# All rights reserved.
#

# This is the tb_compact.tcl that deals with all the TB specific commands.
# It should be loaded at the beginning of any ns script using the TB commands.

# We set up some helper stuff in a separate namespace to avoid any conflicts.
namespace eval TBCOMPAT {
    var_import ::GLOBALS::DB
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid

    # This is regular expression that matches slightly more than valid
    # IP addresses.  The only thing it doesn't check is that IP 
    # addresses are in range (i.e. 0-255).
    variable IP {^([0-9]{1,3}\.){3,3}[0-9]{1,3}$}
    # This is a list of invalid IP prefixes to catch users who specify off
    # limits IPs.  Each is a regexp.
    variable bad_prefixes [ list "^192\.168\." ]

    # This is an RE to match a floating point number.
    variable FLOAT {(^[0-9]+(\.[0-9]+)?$)|(^\.[0-9]+$)}

    # This is the default weight for a soft vtype.
    variable default_soft_vtype_weight 0.5

    # This is the default weight for a hard vtype.
    variable default_hard_vtype_weight 1.0

    variable prefix "/usr/testbed"

    # Substitutions for "/proj",
    variable FSDIR_PROJ "/big/proj"
    variable PROJROOT	"/proj"

    # ... "/groups",
    variable FSDIR_GROUPS "/big/groups"
    variable GROUPROOT	  "/groups"

    # ... "/users",
    variable FSDIR_USERS "/big/users"
    variable USERROOT	 "/users"

    # ... "/share", and
    variable FSDIR_SHARE "/big/share"
    variable SHAREROOT	 "/share"

    # ... "/scratch".
    variable FSDIR_SCRATCH ""
    variable SCRATCHROOT   ""

    # This is a general procedure that takes a node, an object (lan or link)
    # it is connected to, and an IP address, and sets the IP address
    # for the node on that object.  It checks both the validity of the
    # IP addresses and the fact that the node is actually a member of the
    # lan/link.
    proc set-ip {node obj ip} {
	variable IP
	variable bad_prefixes

	set caller [lindex [info level -1] 0]
	if {[regexp $IP $ip] == 0} {
	    perror "$caller - $ip is not a valid IP address."
	    return
	}
	foreach pre $bad_prefixes {
	    if { [ regexp $pre $ip ] != 0 } {
	        perror "$caller - $ip is a prohibited IP address."
	        return
	    }
        }
	set port [$node find_port $obj]
	if {$port == -1} {
	    perror "$caller - $node is not connected to $obj."
	    return
	}
	$node ip $port $ip
    }

    # Let's set up a hwtypes table that contains all valid hardware types.
    variable hwtypes
    variable isremote
    variable isvirt
    variable issubnode

    # NSE hack: sim type is not in DB. Just adding it now
    set hwtypes(sim) 1
    set isremote(sim) 0
    set isvirt(sim) 0
    set issubnode(sim) 0

    # The permissions table. Entries in this table indicate who is allowed
    # to use nodes of a particular type. No entries means anyone can use it.
    #
    # We omit this check in anonymous mode.
    #
    variable nodetypeXpid_permissions
    
    # And a os table with valid OS Descriptor names. While we still call
    # them "osids", we are using the user level name not the internal,
    # globally unique name. We leave it to a later phase to deal with it.
    #
    # We omit this check in anonymous mode.
    #
    variable osids

    # The default OSID for the node type. 
    variable default_osids

    # A mapping of event objects and types.
    variable objtypes
    variable eventtypes

    # Existing (reserved nodes).
    variable reserved_list
    variable reserved_type
    variable reserved_node
    set reserved_list {}

    # Input parameters for Templates
    variable parameter_list_defaults
    array set parameter_list_defaults {}

    # Physical node names
    variable physnodes

    ## Feedback related stuff below:

    # Experiment directory name.
    variable expdir

    # ElabInElab stuff. Do not initialize.
    variable elabinelab_maxpcs
    variable elabinelab_hardware
    variable elabinelab_fixnodes
    variable elabinelab_nodeos
    variable elabinelab_source_tarfile ""
    variable elabinelab_tarfiles

    # Mapping of "resource classes" and "reservation types" to bootstrap
    # values, where a resource class is a symbolic string provided by the user
    # (e.g. Client, Server), and a reservation type is a resource name provided
    # by the system (e.g. cpupercent, kbps).  This array will be filled by the
    # tb-feedback methods and then written out to a "bootstrap_data.tcl" file
    # to be read in during future evaluations of the NS file.
    variable BootstrapReservations

    # Table of vnodes/vlinks that were locate on an overloaded pnode.
    variable Alerts

    # Table of "estimated" reservations.  Basically, its our memory of previous
    # guesses for vnodes that have 0% CPU usage on an overloaded pnode.
    variable EstimatedReservations

    # The experiment directory, this is where the feedback related files will
    # be read from and dumped to.  XXX Hacky
    # XXX Hacky II: we must use PROJROOT and not FSDIR_PROJ since these
    # sourced file paths get recorded and used on boss.
    set expdir "${PROJROOT}/${::GLOBALS::pid}/exp/${::GLOBALS::eid}/"

    # XXX Just for now...
    variable tbxlogfile
    if {[file exists "$expdir"]} {
	set logname "$expdir/logs/feedback.log"
	set tbxlogfile [open $logname w 0664];
	catch "exec chmod 0664 $logname"
	puts $tbxlogfile "BEGIN feedback log"
    }

    # Get any Emulab generated feedback data from the experiment directory.
    if {[file exists "${expdir}/tbdata/feedback_data.tcl"]} {
	source "${expdir}/tbdata/feedback_data.tcl"
    }
    # Get any bootstrap feedback data from a previous run.
    if {[file exists "${expdir}/tbdata/bootstrap_data.tcl"]} {
	source "${expdir}/tbdata/bootstrap_data.tcl"
    }
    # Get any estimated feedback data from a previous run.
    if {[file exists "${expdir}/tbdata/feedback_estimate.tcl"]} {
	source "${expdir}/tbdata/feedback_estimate.tcl"
    }

    #
    # Configure the default reservations for an object based on an optional
    # "resource class".  First, the function will check for a reservation
    # specifically made for the object, then it will try to initialize the
    # reservation from the resource class, otherwise it does nothing and
    # returns zero.
    #
    # @param object The object name for which to configure the feedback
    #   defaults.
    # @param rclass The "resource class" of the object or the empty string if
    #   it is not part of any class.  This is just a symbolic string, such as
    #   "Client" or "Server".
    # @return One, if there is an initialized slot in the "Reservations" array
    #   for the given object, or zero if it could not be initialized.
    #
    proc feedback-defaults {object rclass} {
	var_import ::TBCOMPAT::Reservations;  # The reservations to make

	if {[array get Reservations $object,*] == ""} {
	    # No node-specific values exist, try to initialize from the rclass.
	    if {[array get Reservations $rclass,*] != ""} {
		# Use bootstrap feedback from a previous topology,
		set rcdefaults [array get Reservations $rclass,*]
		# ... substitute the node name for the rclass, and
		regsub -all -- $rclass $rcdefaults $object rcdefaults
		# ... add all the reservations to the table.
		array set Reservations $rcdefaults
		set retval 1
	    } else {
		# No feedback exists yet, let the caller fill it in.
		set retval 0
	    }
	} else {
	    # Node-specific values exist, use those.
	    set retval 1
	}
	return $retval
    }

    #
    # Produce an estimate of a vnode's resource usage.  If a guess was already
    # made in the previous iteration, double that value.  Otherwise, we just
    # assume 10%.
    #
    # @param object The object for which to produce the estimate.
    # @param rtype The resource type: cpupercent, rampercent
    # @return The estimated resource usage.
    # 
    proc feedback-estimate {object rtype} {
	var_import ::TBCOMPAT::EstimatedReservations

	if {[array get EstimatedReservations $object,$rtype] != ""} {
	    set retval [expr [set EstimatedReservations($object,$rtype)] * 2]
	} else {
	    set retval 10.0; # XXX get from DB
	}
	set EstimatedReservations($object,$rtype) $retval
	return $retval
    }

    #
    # Record bootstrap feedback data for a resource class.  This function
    # should be called for every member of a resource class so that the one
    # with the highest reservation will be used to bootstrap.
    #
    # @param rclass The "resource class" for which to update the bootstrap
    #   feedback data.  This is just a symbolic string, such as "Client" or
    #   "Server".
    # @param rtype The type of reservation (e.g. cpupercent,kbps).
    # @param res The amount to reserve.
    #
    proc feedback-bootstrap {rclass rtype res} {
	# The bootstrap reservations
	var_import ::TBCOMPAT::BootstrapReservations

	if {$rclass == ""} {
	    # No class to operate on...
	} elseif {([array get BootstrapReservations($rclass,$rtype)] == "") ||
	    ($res > $BootstrapReservations($rclass,$rtype))} {
		# This is either the first time this function was called for
		# this rclass/rtype or the new value is greater than the old.
		set BootstrapReservations($rclass,$rtype) $res
	}
    }

    #
    # Verify that the argument is an http, https, or ftp URL.
    #
    # @param url The URL to check.
    # @return True if "url" looks like a URL.
    #
    # What is xxx:// you might ask? Its part of experimental template code.
    #
    proc verify-url {url} {
	if {[string match "http://*" $url] ||
	    [string match "https://*" $url] ||
	    [string match "ftp://*" $url] ||
	    [string match "xxx://*" $url]} {
	    set retval 1
	} else {
	    set retval 0
	}
	return $retval
    }
}

# IP addresses routines.  These all do some checks and convert into set-ip
# calls.
proc tb-set-ip {node ip} {
    $node instvar portlist
    if {[llength $portlist] != 1} {
	perror "\[tb-set-ip] $node does not have a single connection."
	return
    }
    ::TBCOMPAT::set-ip $node [lindex $portlist 0] $ip
}
proc tb-set-ip-interface {src dst ip} {
    set sim [$src set sim]
    set reallink [$sim find_link $src $dst]
    if {$reallink == {}} {
	perror \
	    "\[tb-set-ip-interface] No connection between $src and $dst."
	return
    }
    ::TBCOMPAT::set-ip $src $reallink $ip
}
proc tb-set-ip-lan {src lan ip} {
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-ip-lan] $lan is not a LAN."
	return
    }
    ::TBCOMPAT::set-ip $src $lan $ip
}
proc tb-set-ip-link {src link ip} {
    if {[$link info class] != "Link"} {
	perror "\[tb-set-ip-link] $link is not a link."
	return
    }
    ::TBCOMPAT::set-ip $src $link $ip
}

#
# Set the netmask. To make it easier to compute subnets later, do
# allow the user to alter the netmask beyond the bottom 3 octets.
# This restricts the user to a lan of 4095 nodes, but that seems okay
# for now. 
# 
proc tb-set-netmask {lanlink netmask} {
    var_import ::TBCOMPAT::IP
    
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan"} {
	perror "\[tb-set-netmask] $lanlink is not a link or a lan."
	return
    }
    if {[regexp $IP $netmask] == 0} {
	perror "\[tb-set-netmask] - $netmask is not a valid IP mask"
	return
    }
    set netmaskint [inet_atohl $netmask]
    # DETER - change mask to FF for benefit of Skaion TGS users.
    if {[expr ($netmaskint & 0xFF000000)] != 0xFF000000} {
	perror "\[tb-set-netmask] - $netmask is too big"
	return
    }
    $lanlink set netmask $netmask
}

# Node state routines.
proc tb-set-hardware {node type args} {
    var_import ::TBCOMPAT::hwtypes
    var_import ::TBCOMPAT::isremote
    var_import ::TBCOMPAT::isvirt
    var_import ::TBCOMPAT::issubnode
    var_import ::GLOBALS::vtypes
    var_import ::GLOBALS::splitmode
    # Remove the passmode
    if {!${GLOBALS::splitmode} && !${GLOBALS::passmode}} {
	if {(! [info exists hwtypes($type)]) &&
	    (! [info exists vtypes($type)])} {
	    perror "\[tb-set-hardware] Invalid hardware type $type."
	    return
	}
    }
    if {! ${GLOBALS::anonymous} && ! ${GLOBALS::passmode}} {
	var_import ::TBCOMPAT::nodetypeXpid_permissions
	var_import ::GLOBALS::pid
	set allowed 1
	
	if {[info exists nodetypeXpid_permissions($type)]} {
	    set allowed 0
	    foreach allowedpid $nodetypeXpid_permissions($type) {
		if {$allowedpid == $pid} {
		    set allowed 1
		}
	    }
	}
	if {! $allowed} {
	    perror "\[tb-set-hardware] No permission to use type $type."
	    return
	}
    }
    set remote 0
    if {[info exists isremote($type)]} {
	set remote $isremote($type)
    }
    set isv 0
    if {[info exists isvirt($type)]} {
	set isv $isvirt($type)
    }
    set issub 0
    if {[info exists isvirt($type)]} {
	set issub $issubnode($type)
    }
    $node set_hwtype $type $remote $isv $issub
}

# DETER Skips on splitmode as well.
proc tb-set-node-os {node os {parentos 0}} {
    if {! ${GLOBALS::anonymous} && ! ${GLOBALS::passmode}
    		&& !${GLOBALS::splitmode}} {
	var_import ::TBCOMPAT::osids
	if {! [info exists osids($os)]} {
	    perror "\[tb-set-node-os] Invalid osid $os."
	    return
	}
	if {$parentos != {} && $parentos != 0} {
	    if {! [info exists osids($parentos)]} {
		perror "\[tb-set-node-os] Invalid parent osid $parentos."
		return
	    }
	}
    }
    $node set osid $os
    if {$parentos != {} && $parentos != 0} {
	$node set parent_osid $parentos
    }
}
proc tb-set-node-cmdline {node cmdline} {
    $node set cmdline $cmdline
}
proc tb-set-node-rpms {node args} {
    if {$args == {}} {
	perror "\[tb-set-node-rpms] No rpms given."
	return
    }
    # Lets assume that a single argument is a string and break it up.
    if {[llength $args] == 1} {
	set args [split [lindex $args 0] " "]
    }
    $node set rpms [join $args ";"]
}
proc tb-set-node-startup {node cmd} {
    $node set startup $cmd
}
proc tb-set-node-tarfiles {node args} {
    if {$args == {}} {
	perror "\[tb-set-node-tarfiles] tb-set-node-tarfiles <node> (<dir> <tar>)+"
	return
    }
    # Lets assume that a single argument is a string and break it up.
    if {[llength $args] == 1} {
	set args [split [lindex $args 0] " "]
    }
    if {[expr [llength $args] % 2] != 0} {
	perror "\[tb-set-node-tarfiles] Arguments should be node and series of pairs."
	return
    }
    set tarfiles {}
    while {$args != {}} {
	set dir [lindex $args 0]
	set tarfile [lindex $args 1]
	
	#
	# Check the install directory to make sure it is not an NFS mount.
	# This check can also act as an alert to the user that the arguments
	# are wrong.  For example, the following line will pass the above
	# checks, but fail this one:
	#
	#   tb-set-node-tarfiles $node /proj/foo/bar.tgz /proj/foo/baz.tgz
	#
	# XXX This is a hack check because they can specify '/' and have
	# "proj/foo/..." in the tarball and still clobber themselves.
	#
	if {[string match "${::TBCOMPAT::PROJROOT}/*" $dir] ||
	    [string match "${::TBCOMPAT::GROUPROOT}/*" $dir] ||
	    [string match "${::TBCOMPAT::USERROOT}/*" $dir] ||
	    [string match "${::TBCOMPAT::SHAREROOT}/*" $dir] ||
	    (${::TBCOMPAT::SCRATCHROOT} != "" &&
	     [string match "${::TBCOMPAT::SCRATCHROOT}/*" $dir])} {
	    perror "\[tb-set-node-tarfiles] '$dir' refers to an NFS directory instead of the node's local disk."
	    return
	} elseif {![string match "/*" $dir]} {
	    perror "\[tb-set-node-tarfiles] '$dir' is not an absolute path."
	    return
	}

	# Skip the rest in passmode.
	if {${GLOBALS::anonymous} || ${GLOBALS::passmode}} {
	    return
	}

	# If splitting don't test the filename for correctness.  Later phases
	# will find this.
	if { !${GLOBALS::splitmode} } {
		# Check the tar file to make sure it exists, is readable, etc...
		if {[string match "*://*" $tarfile]} {
		    # It is a URL, check for a valid protocol.
		    if {![::TBCOMPAT::verify-url $tarfile]} {
			perror "\[tb-set-node-tarfiles] '$tarfile' is not an http, https, or ftp URL."
			return
		    }
		} elseif {![string match "${::TBCOMPAT::PROJROOT}/*" $tarfile] &&
			  ![string match "${::TBCOMPAT::GROUPROOT}/*" $tarfile] &&
			  ![string match "${::TBCOMPAT::SHAREROOT}/*" $tarfile] &&
			  (${::TBCOMPAT::SCRATCHROOT} == "" ||
			   ![string match "${::TBCOMPAT::SCRATCHROOT}/*" $tarfile])} {
		    perror "\[tb-set-node-tarfiles] '$tarfile' is not in an allowed directory"
		    return
		} elseif {![file exists $tarfile]} {
		    perror "\[tb-set-node-tarfiles] '$tarfile' does not exist."
		    return
		} elseif {![file isfile $tarfile]} {
		    perror "\[tb-set-node-tarfiles] '$tarfile' is not a file."
		    return
		} elseif {![file readable $tarfile]} {
		    perror "\[tb-set-node-tarfiles] '$tarfile' is not readable."
		    return
		}

		# Make sure the tarfile has a valid extension.
		if {![string match "*.tar" $tarfile] &&
		    ![string match "*.tar.Z" $tarfile] &&
		    ![string match "*.tar.gz" $tarfile] &&
		    ![string match "*.tgz" $tarfile] &&
		    ![string match "*.tar.bz2" $tarfile]} {
		    perror "\[tb-set-node-tarfiles] '$tarfile' does not have a valid extension (e.g. *.tar, *.tar.Z, *.tar.gz, *.tgz)."
		    return
		}
	}
	lappend tarfiles "$dir $tarfile"
	set args [lrange $args 2 end]
    }
    $node set tarfiles [join $tarfiles ";"]
}
proc tb-set-ip-routing {type} {
    var_import ::GLOBALS::default_ip_routing_type

    if {$type == {}} {
	perror "\[tb-set-ip-routing] No type given."
	return
    }
    if {($type != "none") &&
	($type != "ospf")} {
	perror "\[tb-set-ip-routing] Type is not one of none|ospf"
	return
    }
    set default_ip_routing_type $type
}
proc tb-set-node-usesharednode {node weight} {
    $node add-desire "pcshared" $weight
}
proc tb-set-node-sharingmode {node sharemode} {
    $node set sharing_mode $sharemode
}

# Lan/Link state routines.

# This takes two possible formats:
# tb-set-link-loss <link> <loss>
# tb-set-link-loss <src> <dst> <loss>
proc tb-set-link-loss {srclink args} {
    var_import ::TBCOMPAT::FLOAT
    if {[llength $args] == 2} {
	set dst [lindex $args 0]
	set lossrate [lindex $args 1]
	set sim [$srclink set sim]
	set reallink [$sim find_link $srclink $dst]
	if {$reallink == {}} {
	    perror "\[tb-set-link-loss] No link between $srclink and $dst."
	    return
	}
    } else {
	set reallink $srclink
	set lossrate [lindex $args 0]
    }
    if {([regexp $FLOAT $lossrate] == 0) ||
	(($lossrate != 0) && ($lossrate > 1.0) || ($lossrate < 0.000005))} {
	perror "\[tb-set-link-loss] $lossrate is not a valid loss rate."
    }
    $reallink instvar loss
    $reallink instvar rloss
    set adjloss [expr 1-sqrt(1-$lossrate)]
    foreach pair [array names loss] {
	set loss($pair) $adjloss
	set rloss($pair) $adjloss
    }
}

# This takes two possible formats:
# tb-set-link-est-bandwidth <link> <loss>
# tb-set-link-est-bandwidth <src> <dst> <loss>
proc tb-set-link-est-bandwidth {srclink args} {
    if {[llength $args] == 2} {
	set dst [lindex $args 0]
	set bw [lindex $args 1]
	set sim [$srclink set sim]
	set reallink [$sim find_link $srclink $dst]
	if {$reallink == {}} {
	    perror "\[tb-set-link-est-bandwidth] No link between $srclink and $dst."
	    return
	}
    } else {
	set reallink $srclink
	set bw [lindex $args 0]
    }
    $reallink instvar bandwidth
    $reallink instvar ebandwidth 
    $reallink instvar rebandwidth
    foreach pair [array names bandwidth] {
	set ebandwidth($pair) [parse_bw $bw]
	set rebandwidth($pair) [parse_bw $bw]
    }
}

# This takes two possible formats:
# tb-set-link-backfill <link> <bw>
# tb-set-link-backfill <src> <dst> <bw>
proc tb-set-link-backfill {srclink args} {
    if {[llength $args] == 2} {
	set dst [lindex $args 0]
	set bw [lindex $args 1]
	set sim [$srclink set sim]
	set reallink [$sim find_link $srclink $dst]
	if {$reallink == {}} {
	    perror "\[tb-set-link-backfill] No link between $srclink and $dst."
	    return
	}
    } else {
	if {[$srclink info class] != "Link"} {
	    perror "\[tb-set-link-backfill] $srclink is not a link."
	    return
	}
	set reallink $srclink
	set bw [lindex $args 0]
    }
    $reallink instvar bandwidth
    $reallink instvar backfill
    $reallink instvar rbackfill
    foreach pair [array names bandwidth] {
	set backfill($pair) [parse_bw $bw]
	set rbackfill($pair) [parse_bw $bw]
    }
}

# This takes two possible formats:
# tb-set-link-backfill <link> <src> <bw>
proc tb-set-link-simplex-backfill {link src bw} {
    var_import ::TBCOMPAT::FLOAT
    if {[$link info class] != "Link"} {
	perror "\[tb-set-link-simplex-backfill] $link is not a link."
	return
    }
    if {[$src info class] != "Node"} {
	perror "\[tb-set-link-simplex-backfill] $src is not a node."
	return
    }
    set port [$link get_port $src]
    if {$port == {}} {
	perror "\[tb-set-link-simplex-params] $src is not in $link."
	return
    }
    set np [list $src $port]
    foreach nodeport [$link set nodelist] {
	if {$nodeport != $np} {
	    set onp $nodeport
	}
    }
    set realbw [parse_bw $bw]
    $link set backfill($np) $realbw
    $link set rbackfill($onp) $realbw
}

proc tb-set-lan-loss {lan lossrate} {
    var_import ::TBCOMPAT::FLOAT
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-lan-loss] $lan is not a lan."
	return
    }
    if {([regexp $FLOAT $lossrate] == 0) ||
	(($lossrate != 0) && ($lossrate > 1.0) || ($lossrate < 0.000005))} {
	perror "\[tb-set-lan-loss] $lossrate is not a valid loss rate."
    }
    $lan instvar loss
    $lan instvar rloss
    set adjloss [expr 1-sqrt(1-$lossrate)]
    foreach pair [array names loss] {
	set loss($pair) $adjloss
	set rloss($pair) $adjloss
    }
}

proc tb-set-lan-est-bandwidth {lan bw} {
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-lan-est-bandwidth] $lan is not a lan."
	return
    }

    $lan instvar bandwidth
    $lan instvar ebandwidth 
    $lan instvar rebandwidth
    foreach pair [array names bandwidth] {
	set ebandwidth($pair) [parse_bw $bw]
	set rebandwidth($pair) [parse_bw $bw]
    }
}

proc tb-set-lan-backfill {lan bw} {
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-lan-backfill] $lan is not a lan."
	return
    }

    $lan instvar bandwidth
    $lan instvar backfill
    $lan instvar rbackfill
    foreach pair [array names bandwidth] {
	set backfill($pair) [parse_bw $bw]
	set rbackfill($pair) [parse_bw $bw]
    }
}

proc tb-set-node-lan-delay {node lan delay} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-lan-delay] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-node-lan-delay] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-node-lan-delay] $node is not in $lan."
	return
    }

    set rdelay [parse_delay $delay]
    $lan set delay([list $node $port]) $rdelay
    $lan set rdelay([list $node $port]) $rdelay
}


proc tb-set-node-lan-bandwidth {node lan bw} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-lan-bandwidth] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-node-lan-bandwidth] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-node-lan-bandwidth] $node is not in $lan."
	return
    }
    $lan set bandwidth([list $node $port]) [parse_bw $bw]
    $lan set rbandwidth([list $node $port]) [parse_bw $bw]
}
proc tb-set-node-lan-est-bandwidth {node lan bw} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-lan-est-bandwidth] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-node-lan-est-bandwidth] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-node-lan-est-bandwidth] $node is not in $lan."
	return
    }
    $lan set ebandwidth([list $node $port]) [parse_bw $bw]
    $lan set rebandwidth([list $node $port]) [parse_bw $bw]
}
proc tb-set-node-lan-backfill {node lan bw} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-lan-backfill] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-node-lan-backfill] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-node-lan-backfill] $node is not in $lan."
	return
    }
    $lan set backfill([list $node $port]) [parse_bw $bw]
    $lan set rbackfill([list $node $port]) [parse_bw $bw]
}
proc tb-set-node-lan-loss {node lan loss} {
    var_import ::TBCOMPAT::FLOAT
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-lan-loss] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-node-lan-loss] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-node-lan-loss] $node is not in $lan."
	return
    }
    if {([regexp $FLOAT $loss] == 0) ||
	(($loss != 0) && ($loss > 1.0) || ($loss < 0.000005))} {
	perror "\[tb-set-link-loss] $loss is not a valid loss rate."
    }
    $lan set loss([list $node $port]) $loss
    $lan set rloss([list $node $port]) $loss
}
proc tb-set-node-lan-params {node lan delay bw loss} {
    tb-set-node-lan-delay $node $lan $delay
    tb-set-node-lan-bandwidth $node $lan $bw
    tb-set-node-lan-loss $node $lan $loss
}

proc tb-set-node-failure-action {node type} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-failure-action] $node is not a node."
	return
    }
    if {[lsearch -exact {fatal nonfatal ignore} $type] == -1} {
	perror "\[tb-set-node-failure-action] type must be one of fatal|nonfatal|ignore."
	return
    }
    $node set failureaction $type
}

proc tb-fix-node {vnode pnode} {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-fix-node] $vnode is not a node."
	return
    }
    $vnode set_fixed $pnode
}

proc tb-make-soft-vtype {name types} {
    var_import ::TBCOMPAT::hwtypes
    var_import ::TBCOMPAT::isremote
    var_import ::GLOBALS::vtypes
    var_import ::TBCOMPAT::default_soft_vtype_weight

    if { [llength $types] == 0 } {
        perror "\[tb-tb-make-soft-vtype] empty types list."
        return
    }

    foreach type $types {
	if {! [info exists hwtypes($type)]} {
	    perror "\[tb-make-soft-vtype] Invalid hardware type $type."
	}
    }
    set vtypes($name) [Vtype $name $default_soft_vtype_weight $types]
}

proc tb-make-hard-vtype {name types} {
    var_import ::TBCOMPAT::hwtypes
    var_import ::TBCOMPAT::isremote
    var_import ::GLOBALS::vtypes
    var_import ::TBCOMPAT::default_hard_vtype_weight

    if { [llength $types] == 0 } {
        perror "\[tb-tb-make-hard-vtype] empty types list."
        return
    }

    foreach type $types {
	if {! [info exists hwtypes($type)]} {
	    perror "\[tb-make-hard-vtype] Invalid hardware type $type."
	}
    }
    set vtypes($name) [Vtype $name $default_hard_vtype_weight $types]
}

proc tb-make-weighted-vtype {name weight types} {
    var_import ::TBCOMPAT::hwtypes
    var_import ::TBCOMPAT::isremote
    var_import ::GLOBALS::vtypes
    var_import ::TBCOMPAT::FLOAT

    if { [llength $types] == 0 } {
        perror "\[tb-tb-make-tb-weighted-vtype] empty types list."
        return
    }

    foreach type $types {
	if {! [info exists hwtypes($type)]} {
	    perror "\[tb-make-weighted-vtype] Invalid hardware type $type."
            return
	}
	if {$isremote($type)} {
	    perror "\[tb-make-weighted-vtype] Remote type $type not allowed."
	}
    }
    if {([regexp $FLOAT $weight] == 0) ||
	($weight <= 0) || ($weight >= 1.0)} {
	perror "\[tb-make-weighted-vtype] $weight is not a valid weight. (0 < weight < 1)."
        return
    }
    set vtypes($name) [Vtype $name $weight $types]
}

proc tb-set-link-simplex-params {link src delay bw loss} {
    var_import ::TBCOMPAT::FLOAT
    if {[$link info class] != "Link"} {
	perror "\[tb-set-link-simplex-params] $link is not a link."
	return
    }
    if {[$src info class] != "Node"} {
	perror "\[tb-set-link-simplex-params] $src is not a node."
	return
    }
    set port [$link get_port $src]
    if {$port == {}} {
	perror "\[tb-set-link-simplex-params] $src is not in $link."
	return
    }
    if {([regexp $FLOAT $loss] == 0) ||
	(($loss != 0) && (($loss > 1.0) || ($loss < 0.000005)))} {
	perror "\[tb-set-link-simplex-params] $loss is not a valid loss rate."
	return
    }
    set adjloss [expr 1-sqrt(1-$loss)]
    set np [list $src $port]
    foreach nodeport [$link set nodelist] {
	if {$nodeport != $np} {
	    set onp $nodeport
	}
    }

    set realdelay [parse_delay $delay]
    set realbw [parse_bw $bw]
    $link set delay($np) [expr $realdelay / 2.0]
    $link set rdelay($onp) [expr $realdelay / 2.0]
    $link set bandwidth($np) $realbw
    $link set rbandwidth($onp) $realbw
    $link set loss($np) [expr $adjloss]
    $link set rloss($onp) [expr $adjloss]
}

proc tb-set-lan-simplex-backfill {lan node tobw frombw} {
    var_import ::TBCOMPAT::FLOAT
    if {[$node info class] != "Node"} {
	perror "\[tb-set-lan-simplex-params] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-lan-simplex-params] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-lan-simplex-params] $node is not in $lan."
	return
    }
    set realtobw [parse_backfill $tobw]
    set realfrombw [parse_backfill $frombw]

    $lan set backfill([list $node $port]) $realtobw
    $lan set rbackfill([list $node $port]) $realfrombw
}

proc tb-set-lan-simplex-params {lan node todelay tobw toloss fromdelay frombw fromloss} {
    var_import ::TBCOMPAT::FLOAT
    if {[$node info class] != "Node"} {
	perror "\[tb-set-lan-simplex-params] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-set-lan-simplex-params] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-set-lan-simplex-params] $node is not in $lan."
	return
    }
    if {([regexp $FLOAT $toloss] == 0) ||
	(($toloss != 0) && ($toloss > 1.0) || ($toloss < 0.000005))} {
	perror "\[tb-set-link-loss] $toloss is not a valid loss rate."
    }
    if {([regexp $FLOAT $fromloss] == 0) ||
	(($fromloss != 0) && ($fromloss > 1.0) || ($fromloss < 0.000005))} {
	perror "\[tb-set-link-loss] $fromloss is not a valid loss rate."
    }

    set realtodelay [parse_delay $todelay]
    set realfromdelay [parse_delay $fromdelay]
    set realtobw [parse_bw $tobw]
    set realfrombw [parse_bw $frombw]

    $lan set delay([list $node $port]) $realtodelay
    $lan set rdelay([list $node $port]) $realfromdelay
    $lan set loss([list $node $port]) $toloss
    $lan set rloss([list $node $port]) $fromloss
    $lan set bandwidth([list $node $port]) $realtobw
    $lan set rbandwidth([list $node $port]) $realfrombw
}

proc tb-set-uselatestwadata {onoff} {
    var_import ::GLOBALS::uselatestwadata

    if {$onoff != 0 && $onoff != 1} {
	perror "\[tb-set-uselatestwadata] $onoff must be 0/1"
	return
    }

    set uselatestwadata $onoff
}

proc tb-set-usewatunnels {onoff} {
    var_import ::GLOBALS::usewatunnels

    if {$onoff != 0 && $onoff != 1} {
	perror "\[tb-set-usewatunnels] $onoff must be 0/1"
	return
    }

    set usewatunnels $onoff
}

proc tb-use-endnodeshaping {onoff} {
    var_import ::GLOBALS::uselinkdelays

    if {$onoff != 0 && $onoff != 1} {
	perror "\[tb-use-endnodeshaping] $onoff must be 0/1"
	return
    }

    set uselinkdelays $onoff
}

proc tb-force-endnodeshaping {onoff} {
    var_import ::GLOBALS::forcelinkdelays

    if {$onoff != 0 && $onoff != 1} {
	perror "\[tb-force-endnodeshaping] $onoff must be 0/1"
	return
    }

    set forcelinkdelays $onoff
}

proc tb-set-wasolver-weights {delay bw plr} {
    var_import ::GLOBALS::wa_delay_solverweight
    var_import ::GLOBALS::wa_bw_solverweight
    var_import ::GLOBALS::wa_plr_solverweight

    if {($delay < 0) || ($bw < 0) || ($plr < 0)} {
	perror "\[tb-set-wasolver-weights] Weights must be postive integers."
	return
    }
    if {($delay == {}) || ($bw == {}) || ($plr == {})} {
	perror "\[tb-set-wasolver-weights] Must provide delay, bw, and plr."
	return
    }

    set wa_delay_solverweight $delay
    set wa_bw_solverweight $bw
    set wa_plr_solverweight $plr
}

#
# Control emulated for a link
# 
proc tb-set-multiplexed {lanlink onoff} {
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan" } {
	perror "\[tb-set-multiplexed] $link is not a link or a lan."
	return
    }
    $lanlink set emulated $onoff
}

#
# For emulated links, allow bw shaping to be turned off
# 
proc tb-set-noshaping {lanlink onoff} {
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan" } {
	perror "\[tb-set-noshaping] $link is not a link or a lan."
	return
    }
    $lanlink set nobwshaping $onoff
}

#
# For emulated links, allow veth device to be used. Not a user option.
# XXX backward compat, use tb-set-link-encap now.
# 
proc tb-set-useveth {lanlink onoff} {
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan"} {
	perror "\[tb-set-useveth] $link is not a link or a lan."
	return
    }
    if {$onoff == 0} {
	$lanlink set encap "default"
    } else {
	$lanlink set encap "veth"
    }
}

#
# For emulated links, allow specifying encapsulation style.
# Generalizes tb-set-useveth.
# 
proc tb-set-link-encap {lanlink style} {
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan"} {
	perror "\[tb-set-link-encap] $link is not a link or a lan."
	return
    }

    switch -- $style {
	"alias" {
	    set style "alias"
	}
	"gre" {
	    set style "gre"
	}
	"egre" {
	    set style "egre"
	}
	"vtun" {
	    set style "vtun"
	}
	"veth" {
	    set style "veth"
	}
	"veth-ne" {
	    set style "veth-ne"
	}
	"vlan" {
	    set style "vlan"
	}
	default {
	    perror "\[tb-set-link-encap] one of: 'alias', 'veth', 'veth-ne', 'vlan'"
	    return
	}
    }

    $lanlink set encap $style
}


#
# Control linkdelays for lans and links
# 
proc tb-set-endnodeshaping {lanlink onoff} {
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan"} {
	perror "\[tb-set-endnodeshaping] $lanlink is not a link or a lan."
	return
    }
    $lanlink set uselinkdelay $onoff
}

#
# Crude control of colocation of virt nodes. Will be flushed when we have
# a real story. Sets it for the entire link or lan. Maybe set it on a
# per node basis?
#
proc tb-set-allowcolocate {lanlink onoff} {
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan"} {
	perror "\[tb-set-allowcolocate] $lanlink is not a link or a lan."
	return
    }
    $lanlink set trivial_ok $onoff
}

#
# Another crude control. Allow override of multiplex factor that is listed
# in the node_types table. 
#
proc tb-set-colocate-factor {factor} {
    var_import ::GLOBALS::multiplex_factor

    if {$factor < 1 || $factor > 100} {
	perror "\[tb-set-colocate-factor] factor must be 1 <= factor <= 100"
	return
    }

    set multiplex_factor $factor
}

#
# Set the sync server for the experiment. Must a vnode name that has been
# allocated.
#
proc tb-set-sync-server {node} {
    var_import ::GLOBALS::sync_server

    if {[$node info class] != "Node"} {
	perror "\[tb-set-sync-server] $node is not a node."
	return
    }
    set sync_server $node
}

#
# Turn on or of the ipassign program for IP address assignment and route
# calculation
#
proc tb-use-ipassign {onoff} {
    var_import ::GLOBALS::use_ipassign

    set use_ipassign $onoff
}

#
# Give arguments for ipassign
#
proc tb-set-ipassign-args {stuff} {
    var_import ::GLOBALS::ipassign_args

    set ipassign_args $stuff
}

#
# Set the startup command for a node. Replaces the tb-set-node-startup
# command above, but we have to keep that one around for a while. This
# new version dispatched to the node object, which uses a program object.
# 
proc tb-set-node-startcmd {node command} {
    var_import ::GLOBALS::splitmode
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-startcmd] $node is not a node."
	return
    }

    # XXX : don't add the code to catch the exit code if we're just splitting
    if {!$GLOBALS::splitmode} {
	set command "($command ; /usr/local/etc/emulab/startcmddone \$?)"
    }
    set newprog [$node start-command $command]

    return $newprog
}

#
# More crude controls.
#
proc tb-set-mem-usage {usage} {
    var_import ::GLOBALS::mem_usage

    if {$usage < 1 || $usage > 5} {
	perror "\[tb-set-mem-usage] usage must be 1 <= factor <= 5"
	return
    }

    set mem_usage $usage
}
proc tb-set-cpu-usage {usage} {
    var_import ::GLOBALS::cpu_usage

    if {$usage < 1 || $usage > 5} {
	perror "\[tb-set-cpu-usage] usage must be 1 <= factor <= 5"
	return
    }

    set cpu_usage $usage
}

#
# This is nicer syntax for subnodes.
#
proc tb-bind-parent {sub phys} {
    tb-fix-node $sub $phys
}

proc tb-fix-current-resources {onoff} {
    var_import ::GLOBALS::fix_current_resources

    if {$onoff != 0 && $onoff != 1} {
	perror "\[tb-fix-current-resources] $onoff must be 0/1"
	return
    }

    set fix_current_resources $onoff
}

#
# Control veth encapsulation. 
# 
proc tb-set-encapsulate {onoff} {
    var_import ::GLOBALS::vlink_encapsulate

    if {$onoff == 0} {
	set vlink_encapsulate "veth-ne"
    } elseif {$onoff == 1} {
	set vlink_encapsulate "default"
    } else {
	perror "\[tb-set-encapsulate] $onoff must be 0/1"
    }
}

#
# Control virtual link emulation style.
# 
proc tb-set-vlink-emulation {style} {
    var_import ::GLOBALS::vlink_encapsulate

    switch -- $style {
	"alias" {
	    set style "alias"
	}
	"gre" {
	    set style "gre"
	}
	"egre" {
	    set style "egre"
	}
	"vtun" {
	    set style "vtun"
	}
	"veth" {
	    set style "veth"
	}
	"veth-ne" {
	    set style "veth-ne"
	}
	"vlan" {
	    set style "vlan"
	}
	default {
	    perror "\[tb-set-encapsulate] one of: 'alias', 'veth', 'veth-ne', 'vlan'"
	    return
	}
    }
    set vlink_encapsulate $style
}

#
# Control jail and delay nodes osnames. 
# 
proc tb-set-jail-os {os} {
    var_import ::GLOBALS::jail_osname
    
    if {! ${GLOBALS::anonymous} && ! ${GLOBALS::passmode}} {
	var_import ::TBCOMPAT::osids
	if {! [info exists osids($os)]} {
	    perror "\[tb-set-jail-os] Invalid osid $os."
	    return
	}
    }
    set jail_osname $os
}
proc tb-set-delay-os {os} {
    var_import ::GLOBALS::delay_osname
    
    if {! ${GLOBALS::anonymous} && ! ${GLOBALS::passmode}} {
	var_import ::TBCOMPAT::osids
	if {! [info exists osids($os)]} {
	    perror "\[tb-set-delay-os] Invalid osid $os."
	    return
	}
    }
    set delay_osname $os
}

#
# Set the delay capacity override. This is not documented cause we
# do not want people to do this!
#
proc tb-set-delay-capacity {cap} {
    var_import ::GLOBALS::delay_capacity

    if { $cap <= 0 || $cap > 2 } {
	perror "\[tb-set-delay-capacity] Must be 0 < X <= 2"
	return
    }
    set delay_capacity $cap
}

#
# Allow type of lans (but not links) to be changed.
#
proc tb-set-lan-protocol {lanlink protocol} {
    if {[$lanlink info class] != "Lan"} {
	perror "\[tb-set-lan-protocol] $lanlink is not a lan."
	return
    }
    $lanlink set protocol $protocol
}

#
# Allow type of links (but not LANs) to be changed.
#
proc tb-set-link-protocol {lanlink protocol} {
    if {[$lanlink info class] != "Link"} {
	perror "\[tb-set-lan-protocol] $lanlink is not a link."
	return
    }
    $lanlink set protocol $protocol
}

#
# XXX - We need to set the accesspoint for a wireless lan. I have no
# idea how this will eventually be done, but for now just do it manually.
# 
proc tb-set-lan-accesspoint {lanlink node} {
    if {[$lanlink info class] != "Lan"} {
	perror "\[tb-set-lan-accesspoint] $lanlink is not a lan."
	return
    }
    if {[$node info class] != "Node"} {
	perror "\[tb-set-lan-accesspoint] $node is not a node."
	return
    }
    $lanlink set_accesspoint $node
}

#
# Set capabilities for lans and members of lans.
#
proc tb-set-lan-setting {lanlink capkey capval} {
    if {[$lanlink info class] != "Lan"} {
	perror "\[tb-set-lan-setting] $lanlink is not a lan."
	return
    }
    $lanlink set_setting $capkey $capval
}
proc tb-set-node-lan-setting {lanlink node capkey capval} {
    if {[$lanlink info class] != "Lan"} {
	perror "\[tb-set-node-lan-setting] $lanlink is not a lan."
	return
    }
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-lan-setting] $node is not a node."
	return
    }
    $lanlink set_member_setting $node $capkey $capval
}

#
# Turn on or of the use of phys naming; if the user name for the node
# matches a real node in the testbed, do an implicit fix-node to it.
#
proc tb-use-physnaming {onoff} {
    var_import ::GLOBALS::use_physnaming

    set use_physnaming $onoff
}

#
# Write to the tb-experimental log file, as defined by the tbxlogfile global
# variable.  If the tbxlogfile variable is not set, the message is sent to
# /dev/null.
#
# @param msg The message to write to the log file.
#
# @global tbxlogfile The path to the log file, if defined.
#
proc tbx-log {msg} {
    var_import ::TBCOMPAT::tbxlogfile;

    if {[info exists tbxlogfile]} {
	puts $tbxlogfile $msg
    }
}

##
## BEGIN Feedback
##

proc tb-feedback-vnode {vnode hardware args} {
    var_import ::TBCOMPAT::isvirt;        # Make sure $hardware is a vnode.
    var_import ::TBCOMPAT::Reservations;  # The reservations to make for nodes.
    var_import ::TBCOMPAT::BootstrapReservations;  # Bootstrap file.
    var_import ::TBCOMPAT::Alerts;        # Alert indicators
    var_import ::GLOBALS::fix_current_resources

    ::GLOBALS::named-args $args {
	-scale 1.2 -rclass "" -alertscale 2.0 -initscale 0.01
    }

    set fix_current_resources 0

    # Check our inputs,
    if {[$vnode info class] != "Node"} {
	perror "\[tb-feedback-vnode] $vnode is not a node."
	return
    }
    if {(! [info exists isvirt($hardware)]) || (! $isvirt($hardware))} {
	perror "\[tb-feedback-vnode] Unknown hardware type: $hardware"
	return
    }
    if {$(-scale) <= 0.0} {
	perror "\[tb-feedback-vnode] Feedback scale is not greater than zero: $(-scale)"
	return
    }

    tbx-log "BEGIN feedback for $vnode"

    # ... set computed default values, and
    if {[::TBCOMPAT::feedback-defaults $vnode $(-rclass)] == 0} {
	# No feedback exists yet, so we assume 100%.
	set Reservations($vnode,cpupercent) [expr 92.0 * $(-initscale)]
	set Reservations($vnode,rampercent) [expr 80.0 * $(-initscale)]
	tbx-log "  Initializing node, $vnode, to one-to-one."
    }

    # ... make the reservations.
    foreach name [array names Reservations $vnode,*] {
	# Get the type of reservation and
	set reservation_type [lindex [split $name {,}] 1]
	# ... the amount consumed.
	set raw_reservation [set Reservations($name)]

	::TBCOMPAT::feedback-bootstrap \
		$(-rclass) $reservation_type $raw_reservation

	# Then scale the reservation
	set desired_reservation [expr $raw_reservation * $(-scale)]
	# ... making sure it is still within the range of the hardware.
	if {$desired_reservation < 0.0} {
	    # XXX Not allowing negative values might be too restrictive...
	    perror "\[tb-feedback-vnode] Bad reservation value: $name = $raw_reservation"
	    return
	}
	if {([array get Alerts $vnode] != "") && [set Alerts($vnode)] > 0} {
	    # The pnode was overloaded, need to adjust the reservation in a
	    # more radical fashion.
	    tbx-log "Alert for $vnode $desired_reservation"
	    if {$desired_reservation < 0.1} {
		# No good data to work with, make an estimate.
		set desired_reservation [::TBCOMPAT::feedback-estimate \
			$vnode $reservation_type]
	    } else {
		# Some data, try applying the alert scale value.
		set desired_reservation \
			[expr $desired_reservation * $(-alertscale)]; # XXX
	    }
	}
	if {$reservation_type == "cpupercent"} {
	    if {$desired_reservation > 92.0} {
		set desired_reservation 92.0
	    }
	} else {
	    if {$desired_reservation > 80.0} {
		set desired_reservation 80.0
	    }
	}
	tbx-log "  $reservation_type: ${desired_reservation}"
	# Finally, tell assign about our desire.
	$vnode add-desire ?+${reservation_type} ${desired_reservation}
    }

    tb-set-hardware $vnode $hardware

    tbx-log "END feedback for $vnode"
}

proc tb-feedback-vlan {vnode lan args} {
    var_import ::TBCOMPAT::Reservations;   # The reservations to make for lans
    var_import ::TBCOMPAT::Alerts;         # Alert indicators

    ::GLOBALS::named-args $args {-scale 1.0 -rclass "" -alertscale 3.0}

    if {[$vnode info class] != "Node"} {
	perror "\[tb-feedback-vlan] $vnode is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[tb-feedback-vlan] $lan is not a LAN."
	return
    }
    if {$(-scale) <= 0.0} {
	perror "\[tb-feedback-vlan] Feedback scale is not greater than zero: $(-scale)"
	return
    }

    tbx-log "BEGIN feedback for node $vnode on lan $lan"

    if {[::TBCOMPAT::feedback-defaults "$vnode,$lan" $(-rclass)] == 0} {
	# No feedback exists yet, so we assume 100%.  Fortunately, everything
	# already assumes 100%, so we do not have to do anything extra.
	tbx-log "  Initializing vlan, $vnode $lan, to one-to-one."
    }

    foreach name [array names Reservations ${vnode},${lan},kbps] {
	# Get the type of reservation and
	set reservation_type [lindex [split $name {,}] 1]
	# ... its value.
	set raw_reservation [set Reservations($name)]
	tbx-log "  raw: $raw_reservation"
	# Get the maximum allowed value and
	set max_reservation 0
	foreach pair [$lan array names bandwidth "${vnode} *"] {
	    tbx-log "  pair: $pair - [$lan set bandwidth($pair)]"
	    if {[$lan set bandwidth($pair)] > $max_reservation} {
		set max_reservation [$lan set bandwidth($pair)]
	    }
	}
	tbx-log "  max: $max_reservation"
	# ... fix any measuring/shaping error.
	if {$raw_reservation > $max_reservation} {
	    tbx-log "  request > max: $raw_reservation $max_reservation"
	    set raw_reservation $max_reservation
	}

	::TBCOMPAT::feedback-bootstrap \
		$(-rclass) $reservation_type $raw_reservation

	# Then scale the reservation
	set desired_reservation \
		[expr int(sqrt($raw_reservation * $max_reservation) * $(-scale))]
	# ... making sure it is still within the range of the hardware.
	if {$desired_reservation < 0.0} {
	    # XXX Not allowing negative values might be too restrictive...
	    perror "\[tb-feedback-vlan] Bad reservation value: $name = $raw_reservation"
	} elseif {$desired_reservation < 10.0} {
	    set desired_reservation 10; # XXX see parse.tcl.in
	}
	if {([array get Alerts $lan,$vnode] != "") &&
	    [set Alerts($lan,$vnode)] > 0} {
	    # The pnode was overloaded, need to adjust the reservation in a
	    # more radical fashion.
	    tbx-log "Alert for $lan, $vnode"
	    set desired_reservation \
		    [expr $desired_reservation * $(-alertscale)]; # XXX
	}
	if {$desired_reservation > $max_reservation} {
	    set desired_reservation $max_reservation
	}

	tbx-log "  $reservation_type: ${desired_reservation}"

	# Finally, adjust the cap.
	tb-set-node-lan-est-bandwidth $vnode $lan ${desired_reservation}kb
    }
    
    tbx-log "END feedback for node $vnode on lan $lan"
}

proc tb-feedback-vlink {link args} {
    var_import ::TBCOMPAT::Reservations;   # The reservations to make for links
    var_import ::TBCOMPAT::Alerts;         # Alert indicators

    ::GLOBALS::named-args $args {-scale 1.2 -rclass "" -alertscale 3.0}

    if {[$link info class] != "Link"} {
	perror "\[tb-feedback-vlink] $link is not a link."
	return
    }
    if {$(-scale) <= 0.0} {
	perror "\[tb-feedback-vlink] Feedback scale is not greater than zero: $(-scale)"
	return
    }

    tbx-log "BEGIN feedback for link $link"

    if {[::TBCOMPAT::feedback-defaults $link $(-rclass)] == 0} {
	# No feedback exists yet, so we assume 100%.  Fortunately, not
	# specifying anything implies 100%, so we do not have to do anything
	# extra.
	tbx-log "  Initializing vlink, $link, to one-to-one."
    }

    foreach name [array names Reservations $link,kbps] {
	# Get the type of reservation and
	set reservation_type [lindex [split $name {,}] 1]
	# ... its value.
	set raw_reservation [set Reservations($name)]
	# Get the maximum allowed value and
	set max_reservation 0
	foreach pair [$link array names bandwidth] {
	    if {[$link set bandwidth($pair)] > $max_reservation} {
		set max_reservation [$link set bandwidth($pair)]
	    }
	}
	# ... fix any measuring/shaping error.
	if {$raw_reservation > $max_reservation} {
	    tbx-log "  request > max: $raw_reservation $max_reservation"
	    set raw_reservation $max_reservation
	}

	::TBCOMPAT::feedback-bootstrap \
		$(-rclass) $reservation_type $raw_reservation

	# Then scale the reservation
	set desired_reservation \
		[expr int(sqrt($raw_reservation * $max_reservation))]
	# ... making sure it is still within the range of the hardware.
	if {$desired_reservation < 0.0} {
	    # XXX Not allowing negative values might be too restrictive...
	    perror "\[tb-feedback-vlink] Bad reservation value: $name = $raw_reservation"
	    return
	} elseif {$desired_reservation < 10.0} {
	    set desired_reservation 10; # XXX see parse.tcl.in
	}
	if {([array get Alerts $link] != "") && [set Alerts($link)] > 0} {
	    tbx-log "Alert for $link"
	    set desired_reservation \
		    [expr $desired_reservation * $(-alertscale)]; # XXX
	}
	if {$desired_reservation > $max_reservation} {
	    set desired_reservation $max_reservation
	}

	tbx-log "  $reservation_type: ${desired_reservation}"

	# Finally, adjust the cap.
	tb-set-link-est-bandwidth $link ${desired_reservation}kb
    }
    
    tbx-log "END feedback for link $link"
}

##
## END Feedback
##

#
# User indicates that this is a modelnet experiment. Be default, the number
# of core and edge nodes is set to one each. The user must increase those
# if desired.
# 
proc tb-use-modelnet {onoff} {
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges

    if {$onoff} {
	set modelnet_cores 1
	set modelnet_edges 1
    } else {
	set modelnet_cores 0
	set modelnet_edges 0
    }
}
proc tb-set-modelnet-physnodes {cores edges} {
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges

    if {$cores == 0 || $edges == 0} {
	perror "\[tb-set-modelnet-physnodes] cores and edges must be > 0"
	return
    }

    set modelnet_cores $cores
    set modelnet_edges $edges
}

#
# Mark this experiment as an elab in elab.
#
proc tb-elab-in-elab {onoff} {
    var_import ::GLOBALS::elab_in_elab

    if {$onoff} {
	set elab_in_elab 1
    } else {
	set elab_in_elab 0
    }
}

#
# Mark this experiment as needing a per-experiment DB on ops.
#
proc tb-set-dpdb {onoff} {
    var_import ::GLOBALS::dpdb

    if {$onoff} {
	set dpdb 1
    } else {
	set dpdb 0
    }
}
#
# Change the default topography.
#
proc tb-elab-in-elab-topology {topo} {
    var_import ::GLOBALS::elabinelab_topo

    set elabinelab_topo $topo
}
proc tb-set-inner-elab-eid {eid} {
    var_import ::GLOBALS::elabinelab_eid

    set elabinelab_eid $eid
}
proc tb-set-elabinelab-cvstag {cvstag} {
    var_import ::GLOBALS::elabinelab_cvstag

    set elabinelab_cvstag $cvstag
}
proc tb-elabinelab-singlenet {} {
    var_import ::GLOBALS::elabinelab_singlenet

    set elabinelab_singlenet 1
}

#
# Set the inner elab role for a node.
#
proc tb-set-node-inner-elab-role {node role} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-inner-elab-role] $node is not a node."
	return
    }
    if {[lsearch -exact {boss boss+router router ops ops+fs fs node} $role] == -1} {
	perror "\[tb-set-node-inner-elab-role] type must be one of boss|boss+router|router|ops|ops+fs|fs|node"
	return
    }
    $node set inner_elab_role $role
}

#
# Set a plab role for a node.
#
proc tb-set-node-plab-role {node role} {
	#Currently this functionality is disabled since it has crashed DHCPD
	perror "\[tb-set-node-plab-role] DETER does not currently support PlanetLab."
	return
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-plab-role] $node is not a node."
	return
    }
    if {[lsearch -exact {plc node none} $role] == -1} {
	perror "\[tb-set-node-plab-role] type must be one of plc|node|none"
	return
    }
    $node set plab_role $role
}

#
# Set the default inner plab network.  Can be a linklan, "CONTROL", or
# "EXPERIMENTAL".  If user sets CONTROL
#

#
# Set the interface on which a node will be/access PLC.
# Both a plc and a normal planetlab node can call this.
#
proc tb-set-node-plab-plcnet {node lanlink} {
    if {[$node info class] != "Node"} {
        perror "\[tb-set-node-plab-plcnet] $node is not a node."
        return
    }
    if {$lanlink != "control" && $lanlink != "exp" &&
        ([$lanlink info class] != "Link" && [$lanlink info class] != "Lan")} {
	perror "\[tb-set-node-plab-plcnet] $lanlink must be a link, lan, \"control\", or \"exp\"."
	return
    }
    # don't do checking here, wait til we have Total Information Awareness.
    $node set plab_plcnet $lanlink
}

#
# Set security level.
#
proc tb-set-security-level {level} {
    var_import ::GLOBALS::security_level
    var_import ::GLOBALS::explicit_firewall

    if {$explicit_firewall} {
	perror "\[tb-set-security-level] cannot combine with explicit firewall"
    }

    switch -- $level {
	"Green" {
	    set level 0
	}
	"Blue" {
	    set level 1
	}
	"Yellow" {
	    set level 2
	}
	"Orange" {
	    set level 3
	}
	"Red" {
	    perror "\[tb-set-security-level] Red security not implemented yet"
	    return
	}
	unknown {
	    perror "\[tb-set-security-level] $level is not a valid level"
	    return
	}
    }
    set security_level $level
}

#
# Set numeric ID (this is a mote thing)
#
proc tb-set-node-id {vnode myid} {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-set-node-id] $vnode is not a node."
	return
    }
    $vnode set_numeric_id $myid
}

#
# Set testbed name for annotated federation files
#

proc tb-set-node-testbed { vnode tb } {
    var_import ::GLOBALS::testbeds
	       
    if { [info exists ::GLOBALS::testbeds($tb)] == 1} {
	incr ::GLOBALS::testbeds($tb)
    } else {
        set ::GLOBALS::testbeds($tb) 1
    }
    $vnode set testbed $tb;
}

# 
# Set the default action for nodes to take on failure.
proc tb-set-default-failure-action {type} {
    var_import ::GLOBALS::default_failureaction;

    if {[lsearch -exact {fatal nonfatal ignore} $type] == -1} {
	perror "\[tb-set-node-failure-action] type must be one of fatal|nonfatal|ignore."
	return
    }
    set ::GLOBALS::default_failureaction $type
}

#
# Fix a particular node interface to a lanlink
#
proc tb-fix-interface {vnode lanlink iface} {
    if {[$vnode info class] != "Node"} {
        perror "\[tb-fix-interface] $vnode is not a node."
        return
    }
    if {[$lanlink info class] != "Link" && [$lanlink info class] != "Lan"} {
        perror "\[tb-fix-interface] $lanlink must be a link or lan!"
        return
    }

    $lanlink set_fixed_iface $vnode $iface
}
# Flag a node as having external access.  DETER will plumb it to the outside
# world.  Args is a list of arguments to provide parameterized access.
# Currently only the first parameter - a type value - is meaningful.
proc tb-allow-external { vnode args } {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-allow-external] $vnode is not a node."
	return
    }
    if { [llength $args] > 0 } {
	if { [ expr [llength $args] % 2] != 1 } { 
	    perror "\[tb-allow-external] unmatched keyword in params."
	    return
	}
	$vnode set portalparams $args
	$vnode topdl_attr "containers:external" "true"
    } else {
	$vnode set portalparams [ list "tunnelip" ]
	$vnode topdl_attr "containers:external" "true"
    }
}

proc tb-add-node-attribute {vnode attribute value} {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-add-node-attribute] $vnode is not a node."
	return
    }
    $vnode topdl_attr $attribute $value
}

proc tb-add-network-attribute {lan attribute value} {
    if {[$lan info class] != "Lan" && [$lan info class] != "Link" } {
	perror "\[tb-add-network-attribute] $lan is not a network."
	return
    }
    $lan topdl_attr $attribute $value
}

proc tb-add-interface-attribute {node lan attribute value} {
    if {[$node info class] != "Node"} {
	perror "\[tb-add-interface-attribute] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan" && [$lan info class] != "Link" } {
	perror "\[tb-add-interface-attribute] $lan is not a network."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[tb-add-interface-attribute] $node is not in $lan."
	return
    }
    $lan topdl_interface_attr [list $node $port] $attribute $value
}

proc tb-add-node-topdl-os {vnode args } {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-add-node-topdl-os] $vnode is not a node."
	return
    }
    eval $vnode add_topdl_os $args
}

proc tb-add-node-topdl-cpu {vnode args } {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-add-node-topdl-cpu] $vnode is not a node."
	return
    }
    eval $vnode add_topdl_cpu $args
}

proc tb-add-node-topdl-storage {vnode amount persistence args } {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-add-node-topdl-storage] $vnode is not a node."
	return
    }
    if { $persistence != "true" && $persistence != "false" } {
	perror "\[tb-add-node-topdl-storage] persistence must be true or false"
	return
    }
    eval $vnode add_topdl_storage $amount $persistence $args
}

proc tb-add-node-topdl-localname {vnode name} {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-add-node-topdl-localname] $vnode is not a node."
	return
    }
    $vnode add_topdl_localname $name
}

proc tb-set-node-topdl-status {vnode status} {
    if {[$vnode info class] != "Node"} {
	perror "\[tb-set-node-topdl-status] $vnode is not a node."
	return
    }
    if { $status != "empty" && $status != "active" && $status != "inactive" && $status != "starting" && $status != "terminating" && $status != "failed" } {
	perror "\[tb-set-node-topdl-status] invalud topdl status $status"
	return
    }
    $vnode set_topdl_status $status
}

proc tb-add-network-topdl-localname {lan name} {
    if {[$lan info class] != "Lan" && [$lan info class] != "Link" } {
	perror "\[tb-add-network-topdl-localname] $lan is not a network."
	return
    }
    $lan add_topdl_localname $name
}

proc tb-set-network-topdl-status {lan status} {
    if {[$lan info class] != "Lan" && [$lan info class] != "Link" } {
	perror "\[tb-set-network-topdl-status] $lan is not a network."
	return
    }
    if { $status != "empty" && $status != "active" && $status != "inactive" && $status != "starting" && $status != "terminating" && $status != "failed" } {
	perror "\[tb-set-node-topdl-status] invalud topdl status $status"
	return
    }
    $lan set_topdl_status $status
}
