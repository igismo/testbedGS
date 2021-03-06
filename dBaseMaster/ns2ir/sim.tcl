# -*- tcl -*-
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#

######################################################################
# sim.tcl
#
# Defines the Simulator class.  For our purpose a Simulator is a
# topology.  This contains a number nodes, lans, and links.  It
# provides methods for the creation of these objects as well as
# routines to locate the objects.  It also stores common state (such
# as IP subnet usage).  Finally it defines the import 'run' method
# which causes all remaining calculations to be done and updates the
# DB state.
#
# Note: Although NS distinguishs between LANs and Links, we do not.
# We merge both types of objects into a single one called LanLink.  
# See lanlink.tcl and README for more information.
######################################################################

Class Simulator
Class Program -superclass NSObject
Class EventGroup -superclass NSObject
Class Firewall -superclass NSObject

Simulator instproc init {args} {
    var_import ::GLOBALS::program_group
    
    # A counter for internal ids
    $self set id_counter 0

    # Counters for subnets. 
    $self set subnet_counter 1
    $self set wa_subnet_counter 1

    # This is the prefix used to fill any unassigned IP addresses.
    $self set subnet_base "10"

    # The following are sets.  I.e. they map to no value, all
    # we care about is membership.
    $self instvar node_list;		# Indexed by node id
    array set node_list {}
    $self instvar lanlink_list;		# Indexed by lanlink id
    array set lanlink_list {}
    $self instvar subnets;		# Indexed by IP subnet
    array set subnets {}

    # link_map is indexed by <node1>:<node2> and contains the
    # id of the lanlink connecting them.  In the case of
    # multiple links between two nodes this contains
    # the last one created.
    $self instvar link_map
    array set link_map {}

    # event list is a list of {time vnode vname otype etype args atstring}
    $self set event_list {}
    $self set event_count 0

    # global nse config file. to be split later
    $self set nseconfig ""

    # Program list.
    $self instvar prog_list;
    array set prog_list {}

    # EventGroup list.
    $self instvar eventgroup_list;
    array set eventgroup_list {}

    # Firewall.
    $self instvar firewall_list;
    array set firewall_list {}

    $self instvar timeline_list;
    array set timeline_list {}

    $self instvar sequence_list;
    array set sequence_list {}

    $self instvar console_list;
    array set console_list {}

    $self instvar tiptunnel_list;
    set tiptunnel_list {}

    $self instvar topography_list;
    array set topography_list {}

    $self instvar parameter_list;
    array set parameter_list {}
    $self instvar parameter_descriptions;
    array set parameter_descriptions {}

    var_import ::GLOBALS::last_class
    set last_class $self

    $self instvar new_node_config;
    array set new_node_config {}
    $self node-config
}

# renaming the simulator instance
# needed to find the name of the instance
# for use in NSE code
Simulator instproc rename {old new} {
}

Simulator instproc node-config {args} {
    ::GLOBALS::named-args $args {
	-topography ""
    }

    $self instvar new_node_config;
    foreach {key value} [array get ""] {
	set new_node_config($key) $value
    }
}

# node
# This method adds a new node to the topology and returns the id
# of the node.
Simulator instproc node {args} {
    var_import ::GLOBALS::last_class
    var_import ::GLOBALS::simulated
    $self instvar id_counter
    $self instvar node_list

    if {($args != {})} {
	punsup "Arguments for node: $args"
    }
    
    set curnode tbnode-n[incr id_counter]
    Node $curnode $self

    # simulated nodes have type 'sim'
    if { $simulated == 1 } {
        tb-set-hardware $curnode sim
	# This allows assign to prefer pnodes
	# that already have FBSD-NSE as the default
	# boot osid over others
	$curnode add-desire "FBSD-NSE" 0.9
    }
    set node_list($curnode) {}
    set last_class $curnode

    $self instvar new_node_config;
    $curnode topography $new_node_config(-topography)

    return $curnode
}

# duplex-link <node1> <node2> <bandwidth> <delay> <type>
# This adds a new link to the topology.  <bandwidth> can be in any
# form accepted by parse_bw and <delay> in any form accepted by
# parse_delay.  Currently only the type 'DropTail' is supported.
Simulator instproc duplex-link {n1 n2 bw delay type args} {
    var_import ::GLOBALS::last_class
    var_import ::GLOBALS::simulated
    $self instvar id_counter
    $self instvar lanlink_list
    $self instvar link_map

    if {($args != {})} {
	punsup "Arguments for duplex-link: $args"
    }
    set error 0
    if {! [$n1 info class Node]} {
	perror "\[duplex-link] $n1 is not a node."
	set error 1
    }
    if {! [$n2 info class Node]} {
	perror "\[duplex-link] $n2 is not a node."
	set error 1
    }
#    if { [$n1 set isvirt] != [$n2 set isvirt] } {
#	perror "\[duplex-link] Bad link between real and virtual node!"
#	set error 1
#    }

    if { $simulated == 1 && ( [$n1 set simulated] == 0 || [$n2 set simulated] == 0 ) } {
	set simulated 0
	perror "\[duplex-link] Please define links between real and simulated nodes outside make-simulated"
	set simulated 1
	set error 1
    }

    if {$error} {return}

    # Convert bandwidth and delay
    set rbw [parse_bw $bw]
    set rdelay [parse_delay $delay]

    set curlink tblink-l[incr id_counter]

    Link $curlink $self "$n1 $n2" $rbw $rdelay $type	
    set lanlink_list($curlink) {}
    set link_map($n1:$n2) $curlink
    set link_map($n2:$n1) $curlink

    set last_class $curlink
    return $curlink
}

# make-lan <nodelist> <bw> <delay>
# This adds a new lan to the topology. <bandwidth> can be in any
# form accepted by parse_bw and <delay> in any form accepted by
# parse_delay.
Simulator instproc make-lan {nodelist bw delay args} {
    var_import ::GLOBALS::last_class
    var_import ::GLOBALS::simulated
    $self instvar id_counter
    $self instvar lanlink_list

    if {($args != {})} {
	punsup "Arguments for make-lan: $args"
    }

    #
    # The number of virtual nodes has to be zero, or equal to the number
    # of nodes (In other word, no mixing of real and virtual nodes).
    #
#    set acount 0
#    set vcount 0
#    foreach node $nodelist {
#	if { [$node set isvirt] } {
#	    incr vcount
#	}
#	incr acount
#    }
#    if { ($vcount != 0) && ($vcount != $acount) } {
#	perror "\[duplex-link] Bad lan between real and virtual nodes!"
#	set error 1
#	return ""
#    }

    # At this point we have one of the nodes of
    # the lan to be real. We need to make sure
    # that this is not being defined in make-simulated.
    # In other words links or lans from real nodes and
    # simulated nodes should happen outside make-simulated
    if { $simulated == 1 } {

	foreach node $nodelist {
	    if { [$node set simulated] == 0 } {	
		set simulated 0
		perror "Please define lans between real and simulated nodes outside make-simulated"
		set simulated 1
		return ""
	    }
	}
    }

    set curlan tblan-lan[incr id_counter]
    
    # Convert bandwidth and delay
    set rbw [parse_bw $bw]
    set rdelay [parse_delay $delay]

    # Warn about potential rounding of delay values due to implementation
    if { ($rdelay % 2) == 1 } {
	puts stderr "*** WARNING: due to delay implementation, odd delay value $rdelay for LAN may be rounded up"
    }
    
    Lan $curlan $self $nodelist $rbw $rdelay {}
    set lanlink_list($curlan) {}
    set last_class $curlan
    
    return $curlan
}

# make-new-lan <nodelist> [bw bandwidth ] [delay type mean [standard variation] ] [loss threshold dropmode rate]
# It can receives parameters in any order except nodelist should be provided first.
#Example1 make-new-lan "$nodeA $nodeB " bw 1Gb delay static 100ms loss 0.01 static 1
#Example2 make-new-lan "$nodeA $nodeB " bw 1Gb delay normal 100ms 5 loss 0.01 poisson 5
#delay type are : static, normal , poisson and exponential. Note that normal requires standard variation, which default value is zero.
#loss dropmodes are static and poisson
#Default bandwidth is 1Gb
#Simulator instproc make-new-lan {nodelist bw delay args} {
Simulator instproc make-new-lan {nodelist args} {
    var_import ::GLOBALS::last_class
    var_import ::GLOBALS::simulated
    $self instvar id_counter
    $self instvar lanlink_list

    set delay 0
    set bw "1Gb"
    set type "static"
    set stv 0 
    set rate 1
    set threshold 0
    if {($args != {})} {
	#punsup "Arguments for make-new-lan: $args"
	#since we don't want to restrict the user with argument positions
	#user can provide parameters with any position, but it has to be defined 	#by keywords so for Bandwidth it should be bw 1Gb. For delay d static 
	#100 ms or d normal 100ms 5ms where 100ms is the mean and 5ms is the 
	#standard variation, we have to search all elements in args. It's done 
	#in a way similar to parsing command-line arguments.
	#If you don't understand this code, it's getopt.
	set stat 0 
	set dropmode ""
        foreach i $args {
		#change to lowercase so user has not to remember the cases, but
		# we don't change the values because parse_bw doesn't parse gb
		# it should be Gb
	 	set temp [string tolower $i]
		set temp [string trim $temp]	
		#we use this to skip the current iteration if we assign value 
		#in switch so we will not re-assign values in the following if/else
		set flag 0
		switch $temp {
			bw { 
				set stat "bw"
				set flag 1
			 }
			delay {
				set stat "delay"
				set flag 1
			}
			loss {
				set stat "loss"
				set flag 1
			}	
		}
		if {$flag == 1 } {continue }
                if {[string equal -nocase $stat "bw"]  } { 
                        set bw $i  
		} elseif {[string compare $stat "delay"] ==0} { 
			set type $temp
			set stat "delayvalue"
		} elseif {[string compare $stat "delayvalue"]==0 } {
			set delay $temp
			set mean  $temp
			#in case of normal *Gaussian* we need the standard variation
			if {[string compare $type "normal"]==0 } { 
				set stat "stv" 
			} 
		} elseif {[string compare $stat "stv"] ==0 } { 
			set stv $temp 
		} elseif {[string compare $stat "loss"] ==0} {
			set threshold $temp
			set stat "dropmode"
		} elseif {[string compare $stat "dropmode"] == 0 } {
			set dropmode $temp
			set stat "rate"
		} elseif {[string compare $stat "rate"]==0 } { 
			set rate $temp
		} 
	}
    }

    # At this point we have one of the nodes of
    # the lan to be real. We need to make sure
    # that this is not being defined in make-simulated.
    # In other words links or lans from real nodes and
    # simulated nodes should happen outside make-simulated
    if { $simulated == 1 } {

	foreach node $nodelist {
	    if { [$node set simulated] == 0 } {	
		set simulated 0
		perror "Please define lans between real and simulated nodes outside make-simulated"
		set simulated 1
		return ""
	    }
	}
    }

    set curlan tblan-lan[incr id_counter]
    
    # Convert bandwidth and delay
    set rbw [parse_bw $bw]
    set rdelay [parse_delay $delay]
    
    NewLan $curlan $self $nodelist $rbw $rdelay $type $stv $threshold $dropmode $rate
    set lanlink_list($curlan) {}
    set last_class $curlan
    
    return $curlan
}

# make-deter-lan <nodelist> [bw <bandwidth> ] [delay <type> mean <mean> [std <standard deviation>] ] [loss <dropmode> threshold <threshold> rate <rate>]
# It can receives parameters in any order except nodelist should be provided first.
#Example1 make-deter-lan "$nodeA $nodeB " bw 1Gb delay static mean 100ms loss static threshold 0.01 rate 1
#Example2 make-deter-lan "$nodeA $nodeB " bw 1Gb delay normal mean 100ms std 5 loss poisson threshold 0.01 rate 5
#delay type are : static, normal , poisson and exponential. Note that normal requires standard variation, which default value is zero.
#loss dropmodes are static and poisson
#Default bandwidth is 1Gb
Simulator instproc make-deter-lan {nodelist args} {
    var_import ::GLOBALS::last_class
    var_import ::GLOBALS::simulated
    $self instvar id_counter
    $self instvar lanlink_list

    set delay 0
    set bw "1Gb"
    set type "static"
    set stv 0 
    set rate 0
    set threshold 0
    set dropmode ""

    if {($args != {})} {
	#punsup "Arguments for make-new-lan: $args"
	#since we don't want to restrict the user with argument positions
	#user can provide parameters with any position, but it has to be defined 	#by keywords so for Bandwidth it should be bw 1Gb. For delay d static 
	#100 ms or d normal 100ms 5ms where 100ms is the mean and 5ms is the 
	#standard variation, we have to search all elements in args. It's done 
	#in a way similar to parsing command-line arguments.
	#If you don't understand this code, it's getopt.
	set stat 0 
	set dropmode ""
        foreach i $args {
		#change to lowercase so user has not to remember the cases, but
		# we don't change the values because parse_bw doesn't parse gb
		# it should be Gb
	 	set temp [string tolower $i]
		set temp [string trim $temp]	
		#we use this to skip the current iteration if we assign value 
		#in switch so we will not re-assign values in the following if/else
		set flag 0
		switch $temp {
			bw { 
				set stat "bw"
				set flag 1
			 }
			delay {
				set stat "delay"
				set flag 1
			}
			loss {
				set stat "loss"
				set flag 1
			}	
		}
		if {$flag == 1 } {continue }
                if {[string equal -nocase $stat "bw"]  } { 
                        set bw $i  
		} elseif {[string compare $stat "delay"] ==0} { 
			if [
				regexp -nocase {^(static|poisson|normal|exponential)$} $temp matchresult
			] then {
				set type $temp
				set stat "delayparm"
			} else {
				perror "Delay type should be either static,normal,poisson or exponential"
				return ""
			}
		} elseif {[string compare $stat "delayparm"]==0 } {
			if {[string compare $temp "mean"] ==0 } { 
				if {$delay > 0 } { 
					perror "Mean has already set up."
					return ""
				}
				set stat "mean"
			} elseif {[string compare $temp "std"]==0 } { 
				if {$stv > 0 } { 
					perror "Standard deviation has already set up."
					return ""
				}
				set stat "stv" 
			} else {
				perror "Error parsing delay parameters, correct format is delay <delaytype> mean <mean> std <standard deviation>"
				return ""
			}
		} elseif {[string compare $stat "mean"] ==0 } { 
			if [
				regexp -nocase {^\d+ms$} $temp matchresult
			] then {
				set delay $matchresult
				set mean  $matchresult
				#in case of normal *Gaussian* we need the standard variation
				if {[string compare $type "normal"]==0 && $stv <= 0 } { 
					set stat "delayparm" 
				} else { set stat "" }
			} else {
				perror "Delay value should be an integer"
				return ""
			}
		} elseif {[string compare $stat "stv"] ==0 } { 
			if [
				regexp -nocase {^\d+.?\d*$} $temp matchresult
			] then {
				set stv $temp 
				if { $delay <= 0 } {
					set stat "delayparm"
				}
			} else {
				perror "Standard deviation value should be a double value :$temp type = $type mean= $delay"
				return ""
			}
		} elseif {[string compare $stat "loss"] ==0} {
			if [
				regexp -nocase {^(static|poisson|)$} $temp matchresult
			] then {
				set dropmode $temp
				set stat "lossparm"
			} else {
				perror "Loss type should be either static or poisson"
				return ""
			}
			#set threshold $temp
			#set stat "dropmode"
		} elseif {[string compare $stat "lossparm"]==0 } {
			if {[string compare $temp "threshold"]==0 } { 
				if {$threshold > 0 } { 
					perror "Loss Threshold has already set up."
					return ""
				}
				set stat "threshold"
			} elseif {[string compare $temp "rate"]==0 } { 
				if {$rate > 0 } { 
					perror "Loss rate has already set up."
					return ""
				}
				set stat "rate" 
			} else {
				perror "Error parsing loss parameters, correct format is loss <losstype> threshold <threshold> rate  <rate>"
				return ""
				}
		} elseif {[string compare $stat "threshold"] == 0 } {
				if [
				regexp -nocase {^0\.\d+$} $temp matchresult
			] then {
				set threshold $matchresult
				if { $rate <= 0 } {
					set stat "lossparm"
				}
			} else {
				perror "Threshold value should be a float less than one"
				return ""
			}
		} elseif {[string compare $stat "rate"]==0 } { 
			if [
				regexp -nocase {^\d+$} $temp matchresult
			] then {
				set rate $matchresult
				if {  $threshold == 0 } {
					set stat "lossparm"
				}
			} else {
				perror "Loss rate should be an integer"
				return ""
			}
		} else {
			if { [string compare $type "normal"] != 0 && [string compare $temp "std"] == 0  } {
                		perror "Only Normal delay requires standard deviation "
				return ""
			}
			perror "Unknouwn parameter: $i"
			return ""
		}
	}
    }

    if { [string compare $type "normal"] ==0 && $stv <= 0 }  {
		perror "Normal delay requires a standard deviation value greater than zero"
		return ""
    } elseif { [string compare $type "normal"] != 0 && $stv > 0 } { 
		perror "Only Normal delay requires standard deviation "
		return ""
    }
    if {[ string compare $dropmode "static"] ==0 || [string compare $dropmode "poisson"] ==0 } {
	if { $threshold == 0 } { 
		perror "Missing threshold loss value : it should be a double value between 0 and 1"
		return ""
	}
	if { $rate == 0 } {
		perror "Missing rate value: it should be an integer greater or equal to 1"
		return ""
	}
    } 
    # At this point we have one of the nodes of
    # the lan to be real. We need to make sure
    # that this is not being defined in make-simulated.
    # In other words links or lans from real nodes and
    # simulated nodes should happen outside make-simulated
    if { $simulated == 1 } {

	foreach node $nodelist {
	    if { [$node set simulated] == 0 } {	
		set simulated 0
		perror "Please define lans between real and simulated nodes outside make-simulated"
		set simulated 1
		return ""
	    }
	}
    }

    set curlan tblan-lan[incr id_counter]
    
    # Convert bandwidth and delay
    set rbw [parse_bw $bw]
    set rdelay [parse_delay $delay]
    
    NewLan $curlan $self $nodelist $rbw $rdelay $type $stv $threshold $dropmode $rate
    set lanlink_list($curlan) {}
    set last_class $curlan
    
    return $curlan
}


Simulator instproc make-cloud {nodelist bw delay args} {
    $self instvar event_list
    $self instvar event_count

    if {($args != {})} {
	punsup "Arguments for make-cloud: $args"
    }

    set retval [$self make-lan $nodelist $bw $delay]

    $retval set iscloud 1
    $retval mustdelay

    return $retval
}

Simulator instproc event-timeline {args} {
    $self instvar id_counter
    $self instvar timeline_list

    set curtl tbtl-tl[incr id_counter]

    EventTimeline $curtl $self
    set timeline_list($curtl) {}

    return $curtl
}

Simulator instproc event-sequence {{seq {}} {catch {}} {catch_seq {}}} {
    $self instvar id_counter
    $self instvar sequence_list

    if {$catch != {}} {
	set mainseq tbseq-seq[incr id_counter]
	set catchseq tbseq-seq[incr id_counter]
	set curseq tbseq-seq[incr id_counter]

	EventSequence $mainseq $self [uplevel 1 subst [list $seq]]
	EventSequence $catchseq $self [uplevel 1 subst [list $catch_seq]]
	EventSequence $curseq $self [subst {
	    $mainseq run
	    $catchseq run
	}] -errorseq 1
	set sequence_list($mainseq) {}
	set sequence_list($catchseq) {}
	set sequence_list($curseq) {}
    } else {
	set curseq tbseq-seq[incr id_counter]
	set lines {}

	foreach line [split $seq "\n"] {
	    lappend lines [uplevel 1 eval list [list $line]]
	}
	EventSequence $curseq $self $lines
	set sequence_list($curseq) {}
    }

    return $curseq
}

Simulator instproc event-group {{list {}}} {
    set curgrp [new EventGroup $self]
    if {$list != {}} {
	foreach obj $list {
	    $curgrp add $obj
	}
    }

    return $curgrp
}


Simulator instproc run {} {
    var_import ::GLOBALS::splitmode;

    if { $::GLOBALS::splitmode == 1 } {
	$self split_run
    } elseif {$GLOBALS::splitmode == 2 }  {
	$self topdl_run
    } else {
	$self old_run
    }
}


# run
# This calls the various tcl output routines to output a tcl rep of this
# topology
Simulator instproc split_run {} {
    $self instvar lanlink_list
    $self instvar node_list
    $self instvar event_list
    $self instvar prog_list
    $self instvar eventgroup_list
    $self instvar firewall_list
    $self instvar timeline_list
    $self instvar sequence_list
    $self instvar console_list
    $self instvar tiptunnel_list
    $self instvar topography_list
    $self instvar simulated
    $self instvar nseconfig
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::errors
    var_import ::GLOBALS::irfile
    var_import ::GLOBALS::ran
    var_import ::GLOBALS::impotent
    var_import ::GLOBALS::passmode
    var_import ::GLOBALS::vtypes
    var_import ::GLOBALS::uselatestwadata
    var_import ::GLOBALS::usewatunnels
    var_import ::GLOBALS::wa_delay_solverweight
    var_import ::GLOBALS::wa_bw_solverweight
    var_import ::GLOBALS::wa_plr_solverweight
    var_import ::GLOBALS::uselinkdelays
    var_import ::GLOBALS::forcelinkdelays
    var_import ::GLOBALS::multiplex_factor
    var_import ::GLOBALS::sync_server
    var_import ::GLOBALS::use_ipassign
    var_import ::GLOBALS::ipassign_args
    var_import ::GLOBALS::cpu_usage
    var_import ::GLOBALS::mem_usage
    var_import ::GLOBALS::fix_current_resources
    var_import ::GLOBALS::veth_encapsulate
    var_import ::GLOBALS::jail_osname
    var_import ::GLOBALS::delay_osname
    var_import ::GLOBALS::delay_capacity
    var_import ::TBCOMPAT::objtypes
    var_import ::TBCOMPAT::eventtypes
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges
    var_import ::GLOBALS::elab_in_elab
    var_import ::GLOBALS::elabinelab_topo
    var_import ::GLOBALS::elabinelab_eid
    var_import ::GLOBALS::elabinelab_cvstag
    var_import ::GLOBALS::security_level
    var_import ::GLOBALS::explicit_firewall
    var_import ::GLOBALS::sourcefile_list
    var_import ::GLOBALS::testbeds
    var_import ::GLOBALS::hostnames
    var_import ::GLOBALS::master_tb
    
    if {$ran == 1} {
	perror "The Simulator 'run' statement can only be run once."
	return
    }

    if {$security_level || $explicit_firewall} {
	uplevel 2 real_source "/usr/testbed/lib/ns2ir/fw.ns"
    }

    # Fill out IPs
    if {! $use_ipassign } {
	#mark already assigned ips' subnets as used!
        foreach obj [concat [array names lanlink_list]] {
            $obj reserve_ip
        }
	foreach obj [concat [array names lanlink_list]] {
	    $obj fill_ips
	}
    }

    # Go through the list of nodes, and find subnode hosts - we have to add a
    # desire to them to have the hosts-<type-of-child> feature
    foreach node [lsort [array names node_list]] {
	if { [$node set subnodehost] == 1 } {
	    set child [$node set subnodechild]
	    set childtype [$child set type]
	    $node add-desire "hosts-$childtype" 1.0
	}
    }

    # Default sync server.
    set default_sync_server {}

    # Mark that a run statement exists
    set ran 1

    # Check node names.
    foreach node [lsort [array names node_list]] {
	if {! [regexp {^[-0-9A-Za-z]+$} $node]} {
	    perror "\[run] Invalid node name $node.  Can only contain \[-0-9A-Za-z\] due to DNS limitations."
	}
    }
    foreach lan [lsort [array names lanlink_list]] {
	if {! [regexp {^[-0-9A-Za-z]+$} $lan]} {
	    perror "\[run] Invalid lan/link name $lan.  Can only contain \[-0-9A-Za-z\] for symmetry with node DNS limitations."
	}
    }

    # If any errors occur stop here.
    if {$errors == 1} {return}

    # Write out the feedback "bootstrap" file.
    var_import ::TBCOMPAT::expdir;
    var_import ::TBCOMPAT::BootstrapReservations;

    puts "# Begin Vtopo"
    puts "<experiment>"
    puts "<nodes>"
    foreach node [array names node_list ] {
	set l "<node><vname>$node</vname><ips>"
	set i 0
	foreach ip [$node set iplist] {
	    if { $i == 0 } {
		set l "$l$i:$ip"
	    } else {
		set l "$l $i:$ip"
	    }
	    incr i
	}
	puts "$l</ips></node>"
    }
    puts "</nodes>"
    puts "<lans>"
    foreach link [concat [array names lanlink_list]] {
	foreach np [ $link set nodelist] {

	    set n [lindex $np 0]
	    set p [lindex $np 1]
	    set bw [$link set bandwidth($np)]
	    set d [$link set delay($np)]

	    set l "<lan><vname>$link</vname>"
	    set l "$l<vnode>$n</vnode><ip>[$n ip $p]</ip>"
	    set l "$l<bandwidth>$bw</bandwidth><delay>$d</delay>"
	    set l "$l<member>$n:$p</member></lan>"
	    puts $l
	}

    }
    puts "</lans>"
	
    puts "</experiment>"
    puts "# End Vtopo"
    array set tarfs {}
    array set rpmfs {}
    puts "# Begin Allbeds"
    foreach tb [array names ::GLOBALS::testbeds] {
	set all_out "$tb"
	array set tb_types {}
	foreach node [ array names node_list] {
	    if { [$node set testbed] == $tb } {
		set idx "[$node set osid]:[$node set type]";
		if { [info exists tb_types($idx)] } {
		    incr tb_types($idx)
		} else {
		    set tb_types($idx) 1
		}
	    }
	}
	foreach {type count} [array get tb_types] {
	    set all_out "$all_out|$type:$count"
	    unset tb_types($type)
	}
	# Append a dummy entry for the gateways.
	puts "$all_out|GWIMAGE:GWTYPE:0"
    }
    puts "# End Allbeds"
    foreach tb [array names ::GLOBALS::testbeds] {

    set gws {}
    puts "# Begin Testbed ($tb)"
    puts "set ns \[new Simulator]"
    puts "source tb_compat.tcl"
    puts ""
    # Put out the simple tcl
    foreach node [lsort [array names node_list]] {
	$node tcl_out $tb "tarfs" "rpmfs"

	if { $default_sync_server == {} && ![$node set issubnode] } {
	    set default_sync_server $node
	}
    }
    
    foreach lan [concat [array names lanlink_list]] {
	$lan tcl_out $tb "gws"
	if {[$lan set iscloud] != 0} {
	    lappend event_list [list "0" "*" $lan LINK CLEAR "" "" "__ns_sequence"]
	    lappend event_list [list "1" "*" $lan LINK CREATE "" "" "__ns_sequence"]
	}
    }
    if { $tb == $::GLOBALS::master_tb } {
	set ngws {}
	array set dst_ctl { }
	# Set the first experimental tunnel to each slave to be a both tunnel.
	foreach g $gws {
	    set dtb [lindex $g 0]
	    set src [lindex $g 1]
	    set dest [ lindex $g 2]
	    set type [lindex $g 3]
	    
	    if { ![ info exists dst_ctl($dtb)] || ! $dst_ctl($dtb) } { 
		set type "both"
		set dst_ctl($dtb) 1
	    }
	    lappend ngws [ list $dtb $src $dest $type ]
	}

	# For each slave without a both tunnel, add a control tunnel
	foreach dtb [array names ::GLOBALS::testbeds] {
	    if { [ string compare $tb $dtb ] == 0 } { continue }
	    if { ![ info exists dst_ctl($dtb)] || !$dst_ctl($dtb) } { 
		set local_src "${dtb}tunnel"
		puts "# Control net gateway"
		puts "set $local_src \[\$ns node ]"
		puts "tb-set-hardware [name2ref $local_src] GWTYPE"
		puts "tb-set-node-os [name2ref $local_src] GWIMAGE"
		puts "tb-set-node-startcmd [name2ref $local_src] GWSTART"

		lappend ngws [ list $master_tb "${dtb}tunnel" \
		    "${tb}ctrlgw" "control" ]
	    }
	}
    } else {
	set ngws {}
	set master_gw 0

	# Make the first experimental tunnel to the master a both tunnel
	foreach g $gws {
	    set dtb [lindex $g 0]
	    set src [lindex $g 1]
	    set dest [ lindex $g 2]
	    set type [lindex $g 3]
	    
	    if { $dtb == $::GLOBALS::master_tb } {
		if { ! $master_gw } { set type "both" }
		set master_gw 1
	    }
	    lappend ngws [ list $dtb $src $dest $type ]
	}

	# No experiment gateway to the master, make a control tunnel
	if { $master_gw == 0 } {
	    set local_src "${master_tb}ctrlgw"

	    puts "# Control net gateway"
	    puts "set $local_src \[\$ns node ]"
	    puts "tb-set-hardware [name2ref $local_src] GWTYPE"
	    puts "tb-set-node-os [name2ref $local_src] GWIMAGE"
	    puts "tb-set-node-startcmd [name2ref $local_src] GWSTART"

	    lappend ngws [ list $master_tb $local_src \
		"${tb}tunnel" "control" ]
	}
    }
    foreach prog [lsort [array names prog_list]] {
	$prog tcl_out $tb $ngws
    }
    puts ""
    puts "\$ns rtproto Session"
    puts "\$ns run"
    puts "# End Testbed ($tb)"
    puts "# Begin gateways ($tb)"
    foreach g $ngws {
	puts $g
    }
    puts "# End gateways ($tb)"
    }
    # The list of tarfiles to move
    puts "# Begin tarfiles"
    foreach t [ array names tarfs ] {
	puts $t
    }
    puts "# End tarfiles"
    # The list of tarfiles to move
    puts "# Begin rpms"
    foreach t [ array names rpmfs ] {
	puts $t
    }
    puts "# End rpms"

    # Now the combined /etc/hosts file
    puts "# Begin hostnames"
    # Make a hash of ip addresses -> list of nodes with that ip addr.
    foreach { name ip } [ array get ::GLOBALS::hostnames] {
	if { [info exists hn($ip)] } {
	    lappend hn($ip) [string map { "\"" ""} $name]
        } else {
	    set hn($ip) [string map { "\"" ""} $name]
        }
    }
    # put out the table
    foreach ip [lsort -ascii [ array names hn ]] {
	puts "$ip\t[lsort -ascii -decreasing $hn($ip)]"
    }
    puts "# End hostnames"
    # XXX There's a much simpler way to do this.
}

# run
# This calls the various tcl output routines to output a tcl rep of this
# topology
Simulator instproc topdl_run {} {
    $self instvar lanlink_list
    $self instvar node_list
    $self instvar event_list
    $self instvar prog_list
    $self instvar eventgroup_list
    var_import ::GLOBALS::security_level
    var_import ::GLOBALS::explicit_firewall
    var_import ::GLOBALS::ran
    var_import ::GLOBALS::errors
    
    if {$ran == 1} {
	perror "The Simulator 'run' statement can only be run once."
	return
    }

    if {$security_level || $explicit_firewall} {
	uplevel 2 real_source "/usr/testbed/lib/ns2ir/fw.ns"
    }

    # Mark that a run statement exists
    set ran 1

    # Check node names.
    foreach node [lsort [array names node_list]] {
	if {! [regexp {^[-0-9A-Za-z]+$} $node]} {
	    perror "\[run] Invalid node name $node.  Can only contain \[-0-9A-Za-z\] due to DNS limitations."
	}
    }
    foreach lan [lsort [array names lanlink_list]] {
	if {! [regexp {^[-0-9A-Za-z]+$} $lan]} {
	    perror "\[run] Invalid lan/link name $lan.  Can only contain \[-0-9A-Za-z\] for symmetry with node DNS limitations."
	}
    }


    # If any errors occur stop here.
    if {$errors == 1} {return}

    # Write out the topdl file (linklans first so they can set up the
    # interfaces for the nodes)
    puts "<experiment>"
    foreach link [concat [array names lanlink_list]] {
	$link topdl_out
    }
    foreach node [array names node_list ] {
	$node topdl_out
    }
    puts "</experiment>"
}
# run
# This method causes the fill_ips method to be invoked on all 
# lanlinks and then, if not running in impotent mode, calls the
# updatedb method on all nodes and lanlinks.  Invocation of this
# method casues the 'ran' variable to be set to 1.
Simulator instproc old_run {} {
    $self instvar lanlink_list
    $self instvar node_list
    $self instvar event_list
    $self instvar prog_list
    $self instvar eventgroup_list
    $self instvar firewall_list
    $self instvar timeline_list
    $self instvar sequence_list
    $self instvar console_list
    $self instvar tiptunnel_list
    $self instvar topography_list
    $self instvar parameter_list
    $self instvar parameter_descriptions
    $self instvar simulated
    $self instvar nseconfig
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::errors
    var_import ::GLOBALS::irfile
    var_import ::GLOBALS::ran
    var_import ::GLOBALS::impotent
    var_import ::GLOBALS::passmode
    var_import ::GLOBALS::vtypes
    var_import ::GLOBALS::uselatestwadata
    var_import ::GLOBALS::usewatunnels
    var_import ::GLOBALS::wa_delay_solverweight
    var_import ::GLOBALS::wa_bw_solverweight
    var_import ::GLOBALS::wa_plr_solverweight
    var_import ::GLOBALS::uselinkdelays
    var_import ::GLOBALS::forcelinkdelays
    var_import ::GLOBALS::multiplex_factor
    var_import ::GLOBALS::sync_server
    var_import ::GLOBALS::use_ipassign
    var_import ::GLOBALS::ipassign_args
    var_import ::GLOBALS::cpu_usage
    var_import ::GLOBALS::mem_usage
    var_import ::GLOBALS::fix_current_resources
    var_import ::GLOBALS::vlink_encapsulate
    var_import ::GLOBALS::jail_osname
    var_import ::GLOBALS::delay_osname
    var_import ::GLOBALS::delay_capacity
    var_import ::TBCOMPAT::objtypes
    var_import ::TBCOMPAT::eventtypes
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges
    var_import ::GLOBALS::elab_in_elab
    var_import ::GLOBALS::elabinelab_topo
    var_import ::GLOBALS::elabinelab_eid
    var_import ::GLOBALS::elabinelab_cvstag
    var_import ::GLOBALS::elabinelab_singlenet
    var_import ::GLOBALS::security_level
    var_import ::GLOBALS::explicit_firewall
    var_import ::GLOBALS::sourcefile_list
    var_import ::GLOBALS::optarray_order
    var_import ::GLOBALS::optarray_count
    var_import ::GLOBALS::dpdb
    
    if {$ran == 1} {
	perror "The Simulator 'run' statement can only be run once."
	return
    }

    if {$elab_in_elab && [llength [array names node_list]] == 0} {
	if {$elabinelab_topo == ""} {
	    set nsfilename "elabinelab.ns"
	} else {
	    set nsfilename "elabinelab-${elabinelab_topo}.ns"
	}
	uplevel 2 real_source "/usr/testbed/lib/ns2ir/${nsfilename}"
    }
    if {$security_level || $explicit_firewall} {
	uplevel 2 real_source "/usr/testbed/lib/ns2ir/fw.ns"
    }

    # Fill out IPs
    if {! $use_ipassign } {
	#mark already assigned ips' subnets as used!
        foreach obj [concat [array names lanlink_list]] {
            $obj reserve_ip
        }
	foreach obj [concat [array names lanlink_list]] {
	    $obj fill_ips
	}
    }

    # Go through the list of nodes, and find subnode hosts - we have to add a
    # desire to them to have the hosts-<type-of-child> feature
    foreach node [lsort [array names node_list]] {
	if { [$node set subnodehost] == 1 } {
	    set child [$node set subnodechild]
	    set childtype [$child set type]
	    $node add-desire "hosts-$childtype" 1.0
	}
    }

    # If the experiment is firewalled, make sure that all nodes in the
    # experiment have the "firewallable" feature.
    if {[array size firewall_list] > 0} {
	foreach node [lsort [array names node_list]] {
	    $node add-desire "firewallable" 1.0
	}
    }

    # Default sync server.
    set default_sync_server {}

    # Mark that a run statement exists
    set ran 1

    # Check node names.
    foreach node [lsort [array names node_list]] {
	if {! [regexp {^[-0-9A-Za-z]+$} $node]} {
	    perror "\[run] Invalid node name $node.  Can only contain \[-0-9A-Za-z\] due to DNS limitations."
	}
    }
    foreach lan [lsort [array names lanlink_list]] {
	if {! [regexp {^[-0-9A-Za-z]+$} $lan]} {
	    perror "\[run] Invalid lan/link name $lan.  Can only contain \[-0-9A-Za-z\] for symmetry with node DNS limitations."
	}
    }

    # If any errors occur stop here.
    if {$errors == 1} {return}

    # Write out the feedback "bootstrap" file.
    var_import ::TBCOMPAT::expdir;
    var_import ::TBCOMPAT::BootstrapReservations;

    if {! [file isdirectory $expdir]} {
	# Experiment directory does not exist, so we cannot write the file...
    } elseif {[array size BootstrapReservations] > 0} {
	set file [open "$expdir/tbdata/bootstrap_data.tcl" w]
	puts $file "# -*- TCL -*-"
	puts $file "# Automatically generated feedback bootstrap file."
	puts $file "#"
	puts $file "# Generated at: [clock format [clock seconds]]"
	puts $file "#"
	puts $file ""
	foreach res [array names BootstrapReservations] {
	    puts $file "set Reservations($res) $BootstrapReservations($res)"
	}
	close $file
    }

    # Write out the feedback "estimate" file.
    var_import ::TBCOMPAT::EstimatedReservations;

    if {! [file isdirectory $expdir]} {
	# Experiment directory does not exist, so we cannot write the file...
    } elseif {[array size EstimatedReservations] > 0} {
	set file [open "$expdir/tbdata/feedback_estimate.tcl" w]
	puts $file "# -*- TCL -*-"
	puts $file "# Automatically generated feedback estimated file."
	puts $file "#"
	puts $file "# Generated at: [clock format [clock seconds]]"
	puts $file "#"
	puts $file ""
	foreach res [array names EstimatedReservations] {
	    puts $file "set EstimatedReservations($res) $EstimatedReservations($res)"
	}
	close $file
    }

    # If we are running in impotent mode we stop here
    if {$impotent == 1 && $passmode == 0} {return}
    
    $self spitxml_init

    # update the global nseconfigs using a bogus vname
    # i.e. instead of the node on which nse is gonna run
    # which was the original vname field, we just put $ns
    # for now. Once assign runs, the correct value will be
    # entered into the database
    if { $nseconfig != {} } {
 
 	set nsecfg_script ""
 	set simu [lindex [Simulator info instances] 0]
 	append nsecfg_script "set $simu \[new Simulator]\n"
 	append nsecfg_script "\$$simu use-scheduler RealTime\n\n"
 	append nsecfg_script $nseconfig

	$self spitxml_data "nseconfigs" [list "vname" "nseconfig" ] [list fullsim $nsecfg_script ]
    }
    
    # Update the DB
    foreach node [lsort [array names node_list]] {
	$node updatedb "sql"

	if { $default_sync_server == {} && ![$node set issubnode] } {
	    set default_sync_server $node
	}
    }
    
    foreach lan [concat [array names lanlink_list]] {
	$lan updatedb "sql"
	if {[$lan set iscloud] != 0} {
	    lappend event_list [list "0" "*" $lan LINK CLEAR "" "" "__ns_sequence"]
	    lappend event_list [list "1" "*" $lan LINK CREATE "" "" "__ns_sequence"]
	}
    }
    foreach vtype [array names vtypes] {
	$vtype updatedb "sql"
    }
    foreach prog [array names prog_list] {
	$prog updatedb "sql"
    }
    foreach egroup [array names eventgroup_list] {
	$egroup updatedb "sql"
    }
    foreach fw [array names firewall_list] {
	$fw updatedb "sql"
    }
    foreach tl [array names timeline_list] {
	$tl updatedb "sql"
    }
    foreach seq [array names sequence_list] {
	$seq updatedb "sql"
    }
    foreach con [array names console_list] {
	$con updatedb "sql"
    }
    foreach tt $tiptunnel_list {
	$self spitxml_data "virt_tiptunnels" [list "host" "vnode"] $tt
    }
    foreach tg [array names topography_list] {
	$tg updatedb "sql"
    }

    set fields [list "mem_usage" "cpu_usage" "forcelinkdelays" "uselinkdelays" "usewatunnels" "uselatestwadata" "wa_delay_solverweight" "wa_bw_solverweight" "wa_plr_solverweight" "encap_style" "allowfixnode"]
    set values [list $mem_usage $cpu_usage $forcelinkdelays $uselinkdelays $usewatunnels $uselatestwadata $wa_delay_solverweight $wa_bw_solverweight $wa_plr_solverweight $vlink_encapsulate $fix_current_resources]

    if { $multiplex_factor != {} } {
	lappend fields "multiplex_factor"
	lappend values $multiplex_factor
    }
    
    if { $sync_server != {} } {
	lappend fields "sync_server"
	lappend values $sync_server
    } elseif { $default_sync_server != {} } {
	lappend fields "sync_server"
	lappend values $default_sync_server
    }

    lappend fields "use_ipassign"
    lappend values $use_ipassign

    if { $ipassign_args != {} } {
	lappend fields "ipassign_args"
	lappend values $ipassign_args
    }

    if { $jail_osname != {} } {
	lappend fields "jail_osname"
	lappend values $jail_osname
    }
    if { $delay_osname != {} } {
	lappend fields "delay_osname"
	lappend values $delay_osname
    }
    if { $delay_capacity != {} } {
	lappend fields "delay_capacity"
	lappend values $delay_capacity
    }

    if {$modelnet_cores > 0 && $modelnet_edges > 0} {
	lappend fields "usemodelnet"
	lappend values 1
	lappend fields "modelnet_cores"
	lappend values $modelnet_cores
	lappend fields "modelnet_edges"
	lappend values $modelnet_edges
    }
    
    if {$elab_in_elab} {
	lappend fields "elab_in_elab"
	lappend values 1
	lappend fields "elabinelab_singlenet"
	lappend values $elabinelab_singlenet

	if { $elabinelab_eid != {} } {
	    lappend fields "elabinelab_eid"
	    lappend values $elabinelab_eid
	}
	
	if { $elabinelab_cvstag != {} } {
	    lappend fields "elabinelab_cvstag"
	    lappend values $elabinelab_cvstag
	}
    }
    
    if {$security_level} {
	lappend fields "security_level"
	lappend values $security_level
    }

    if {$dpdb} {
	lappend fields "dpdb"
	lappend values $dpdb
    }
    
    $self spitxml_data "experiments" $fields $values

    # This could probably be elsewhere.
    $self spitxml_data "virt_agents" [list "vnode" "vname" "objecttype" ] [list "*" $self $objtypes(SIMULATOR) ]

    # This will eventually be under user control.
    $self spitxml_data "virt_agents" [list "vnode" "vname" "objecttype" ] [list "*" "linktest" $objtypes(LINKTEST) ]

    $self spitxml_data "virt_agents" [list "vnode" "vname" "objecttype" ] [list "*" "slothd" $objtypes(SLOTHD) ]
    
    if {[array exists ::opt]} {
	for {set i 0} {$i < $optarray_count} {incr i} {
	    set oname  $optarray_order($i)
	    set ovalue $::opt($oname)
	
	    $self spitxml_data "virt_user_environment" [list "name" "value" ] [list "$oname" "$ovalue" ]
	}
    }

    foreach event $event_list {
	set fields [list "time" "vnode" "vname" "objecttype" "eventtype" "arguments" "atstring" ]
	set values [list [lindex $event 0] [lindex $event 1] [lindex $event 2] $objtypes([lindex $event 3]) $eventtypes([lindex $event 4]) [lindex $event 5] [lindex $event 6]]
	if {[llength $event] > 7} {
	    lappend fields "parent"
	    lappend values [lindex $event 7]
	}
	$self spitxml_data "eventlist" $fields $values
    }

    foreach name [array names parameter_list] {
	set default_value $parameter_list($name)
	set description $parameter_descriptions($name)

	set p_fields [list "name" "value"]
	set p_values [list $name $default_value]

	if {$description != {}} {
	    lappend p_fields "description"
	    lappend p_values $description
	}

	$self spitxml_data "virt_parameters" $p_fields $p_values
    }
	
    foreach sourcefile $sourcefile_list {
	$self spitxml_data "external_sourcefiles" [list "pathname" ] [list $sourcefile ]
    }

    $self spitxml_finish
}

# attach-agent <node> <agent>
# This creates an attachment between <node> and <agent>.
Simulator instproc attach-agent {node agent} {
    var_import ::GLOBALS::simulated

    if {! [$agent info class Agent]} {
	perror "\[attach-agent] $agent is not an Agent."
	return
    }
    if {! [$node info class Node]} {
	perror "\[attach-agent] $node is not a Node."
	return
    }

    # If the node is real and yet this code is in make-simulated
    # we don't allow it
    if { [$node set simulated] == 0 && $simulated == 1 } {
	set simulated 0
	perror "Please attach agents on to real nodes outside make-simulated"
	set simulated 1
	return ""
    }

    $node attach-agent $agent
}

Simulator instproc agentinit {agent} {
    var_import ::TBCOMPAT::objtypes
    var_import ::TBCOMPAT::eventtypes

    if {[$agent info class Application/Traffic/CBR]} {
	$self spitxml_data "eventlist" [list "time" "vnode" "vname" "objecttype" "eventtype" "arguments" "atstring" "parent" ] [list "0" [$agent get_node] $agent $objtypes(TRAFGEN) $eventtypes(MODIFY) [$agent get_params] "" "__ns_sequence"]
    }
}

# connect <src> <dst>
# Connects two agents together.
Simulator instproc connect {src dst} {
    $self instvar tiptunnel_list

    if {([$src info class Node] && [$dst info class Console]) ||
	([$src info class Console] && [$dst info class Node])} {
	if {[$src info class Node] && [$dst info class Console]} {
	    set node $src
	    set con $dst
	} else {
	    set node $dst
	    set con $src
	}
	if {[$con set connected]} {
	    perror "\[connect] $con is already connected"
	    return
	}
	$con set connected 1
	lappend tiptunnel_list [list $node [$con set node]]
	return
    }
    set error 0
    if {! [$src info class Agent]} {
	perror "\[connect] $src is not an Agent."
	set error 1
    }
    if {! [$dst info class Agent]} {
	perror "\[connect] $dst is not an Agent."
	set error 1
    }
    if {$error} {return}
    $src connect $dst
    $dst connect $src
}

# at <time> <event>
# Known events:
#   <traffic> start
#   <traffic> stop
#   <link> up
#   <link> down
#   ...
Simulator instproc at {time eventstring} {
    var_import ::GLOBALS::simulated
    var_import ::TBCOMPAT::hwtype_class

    # ignore at statement for simulated case
    if { $simulated == 1 } {
	return
    }

    set ptime [::GLOBALS::reltime-to-secs $time]
    if {$ptime == -1} {
	perror "Invalid time spec: $time"
	return
    }
    set time $ptime

    $self instvar event_list
    $self instvar event_count

    if {$event_count > 14000} {
	perror "Too many events in your NS file!"
	exit 1
    }
    set eventlist [split $eventstring ";"]
    
    foreach event $eventlist {
	set rc [$self make_event "sim" $event]

	if {$rc != {}} {
	    set event_count [expr $event_count + 1]
	    lappend event_list [linsert $rc 0 $time]
	}
    }
}

#
# Routing control.
#
Simulator instproc rtproto {type args} {
    var_import ::GLOBALS::default_ip_routing_type
    var_import ::GLOBALS::simulated

    # ignore at statement for simulated case
    if { $simulated == 1 } {
	return
    }

    if {$args != {}} {
	punsup "rtproto: arguments ignored: $args"
    }

    if {($type == "Session") ||	($type == "ospf")} {
	set default_ip_routing_type "ospf"
    } elseif {($type == "Manual")} {
	set default_ip_routing_type "manual"
    } elseif {($type == "Static")} {
	set default_ip_routing_type "static"
    } elseif {($type == "Static-ddijk")} {
	set default_ip_routing_type "static-ddijk"
    } elseif {($type == "Static-old")} {
	set default_ip_routing_type "static-old"
    } else {
	punsup "rtproto: unsupported routing protocol ignored: $type"
	return
    }
}

# unknown 
# This is invoked whenever any method is called on the simulator
# object that is not defined.  We interpret such a call to be a
# request to create an object of that type.  We create display an
# unsupported message and create a NullClass to fulfill the request.
Simulator instproc unknown {m args} {
    $self instvar id_counter
    punsup "Object $m"
    NullClass tbnull-null[incr id_counter] $m
}

# rename_* <old> <new>
# The following two procedures handle when an object is being renamed.
# They update the internal datastructures to reflect the new name.
Simulator instproc rename_lanlink {old new} {
    $self instvar lanlink_list
    $self instvar link_map

    unset lanlink_list($old)
    set lanlink_list($new) {}

    # In the case of a link we need to update the link_map as well.
    if {[$new info class] == "Link"} {
	$new instvar nodelist
	set src [lindex [lindex $nodelist 0] 0]
	set dst [lindex [lindex $nodelist 1] 0]
	set link_map($src:$dst) $new
	set link_map($dst:$src) $new
    }
}
Simulator instproc rename_node {old new} {
    $self instvar node_list

    # simulated nodes won't exist in the node_list
    if { [info exists node_list($old)] } {
	unset node_list($old)
	set node_list($new) {}
    }
}

Simulator instproc rename_program {old new} {
    $self instvar prog_list
    unset prog_list($old)
    set prog_list($new) {}
}

Simulator instproc rename_eventgroup {old new} {
    $self instvar eventgroup_list
    unset eventgroup_list($old)
    set eventgroup_list($new) {}
}

Simulator instproc rename_firewall {old new} {
    $self instvar firewall_list
    unset firewall_list($old)
    set firewall_list($new) {}
}

Simulator instproc rename_timeline {old new} {
    $self instvar timeline_list
    unset timeline_list($old)
    set timeline_list($new) {}
}

Simulator instproc rename_sequence {old new} {
    $self instvar sequence_list
    unset sequence_list($old)
    set sequence_list($new) {}
}

Simulator instproc rename_console {old new} {
    $self instvar console_list
    unset console_list($old)
    set console_list($new) {}
}

Simulator instproc rename_topography {old new} {
    $self instvar topography_list
    unset topography_list($old)
    set topography_list($new) {}
}

# find_link <node1> <node2>
# This is just an accesor to the link_map datastructure.  If no
# link is known between <node1> and <node2> the empty list is returned.
Simulator instproc find_link {src dst} {
    $self instvar link_map
    if {[info exists link_map($src:$dst)]} {
	return $link_map($src:$dst)
    } else {
	return ""
    }
}

Simulator instproc link {src dst} {
    set reallink [$self find_link $src $dst]
	
    if {$src == [$reallink set src_node]} {
	set dir "to"
    } else {
	set dir "from"
    }
    
    var_import GLOBALS::new_counter
    set name sl[incr new_counter]
    
    return [SimplexLink $name $reallink $dir]
}

Simulator instproc lanlink {lan node} {
    if {[$node info class] != "Node"} {
	perror "\[lanlink] $node is not a node."
	return
    }
    if {[$lan info class] != "Lan"} {
	perror "\[lanlink] $lan is not a lan."
	return
    }
    set port [$lan get_port $node]
    if {$port == {}} {
	perror "\[lanlink] $node is not in $lan."
	return
    }
    var_import GLOBALS::new_counter
    set name ll[incr new_counter]
    
    return [LLink $name $lan $node]
}

# get_subnet
# This is called by lanlinks.  When called get_subnet will find an available
# IP subnet, mark it as used, and return it to the caller.
Simulator instproc get_subnet {netmask} {
    $self instvar subnet_base
    $self instvar subnets

    set netmaskint [inet_atohl $netmask]

    set A $subnet_base
    set C [expr ($netmaskint >> 8) & 0xff]
    set D [expr $netmaskint & 0xff]
    set minB 1
    set maxB 254
    set minC 0
    set maxC 1
    set incC 1
    set minD 0
    set maxD 1
    set incD 1

    # allow for 10. or 192.168. I know, could be more general.
    if {[expr [llength [split $subnet_base .]]] == 2} {
	# 192.168.
	set A    [expr [lindex [split $subnet_base .] 0]]
	set minB [expr [lindex [split $subnet_base .] 1]]
	set maxB [expr $minB + 1]
    }
    if {$C != 0} {	
	set minC [expr 256 - $C]
	set maxC 255
	set incC $minC
    } 
    if {$D != 0} {	
	set minD [expr 256 - $D]
	set maxD 255
	set incD $minD
    }

    # We never let the user change the second octet. See tb-set-netmask.
    for {set i $minB} {$i < $maxB} {incr i} {
	for {set j $minC} {$j < $maxC} {set j [expr $j + $incC]} {
	    for {set k $minD} {$k < $maxD} {set k [expr $k + $incD]} {
		set subnet "$A.$i.$j.$k"
		set subnetint [inet_atohl $subnet]

		# No such subnet exists?
		if {! [info exists subnets($subnetint)]} {
		    set okay 1

		    #
		    # See if this subnet violates any existing subnet masks
		    # Is this overly restrictive? Totally wrong?
		    #
		    foreach osubnetint [concat [array names subnets]] {
			set onetmaskint $subnets($osubnetint)

			if {[expr $subnetint & $onetmaskint] == $osubnetint ||
 			    [expr $osubnetint & $netmaskint] == $subnetint} {
			    set okay 0
			    break
			}
		    }
		    if {$okay} {
			$self use_subnet $subnet $netmask
			return $subnet
		    }
		}
	    }
	}
    }
    perror "Ran out of subnets."
}

# get_subnet_remote
# This is called by lanlinks.  When called get_subnet will find an available
# IP subnet, mark it as used, and return it to the caller.
Simulator instproc get_subnet_remote {} {
    $self instvar wa_subnet_counter

    if {$wa_subnet_counter > 255} {
	perror "Ran out of widearea subnets."
	return 0
    }
    set subnet $wa_subnet_counter
    incr wa_subnet_counter
    return "69.69.$subnet.0"
}

# use_subnet
# This is called by the ip method of nodes.  It marks the passed subnet
# as used and thus should never be returned by get_subnet.
Simulator instproc use_subnet {subnet netmask} {
    $self instvar subnets

    set subnetint [inet_atohl $subnet]
    set netmaskint [inet_atohl $netmask]
    
    set subnets($subnetint) $netmaskint
}

# add_program
# Link to a new program object.
Simulator instproc add_program {prog} {
    $self instvar prog_list
    set prog_list($prog) {}
}

# add_eventgroup
# Link to a EventGroup object.
Simulator instproc add_eventgroup {group} {
    $self instvar eventgroup_list
    set eventgroup_list($group) {}
}

# add_console
# Link to a Console object.
Simulator instproc add_console {console} {
    $self instvar console_list
    set console_list($console) {}
}

# add_firewall
# Link to a Firewall object.
Simulator instproc add_firewall {fw} {
    $self instvar firewall_list

    if {[array size firewall_list] > 0} {
	perror "\[add_firewall]: only one firewall per experiment right now"
	return -1
    }

    set firewall_list($fw) {}
    return 0
}

Simulator instproc add_topography {tg} {
    $self instvar topography_list

    set topography_list($tg) {}

    return 0
}

Simulator instproc define-template-parameter {name args} {
    $self instvar parameter_list
    $self instvar parameter_descriptions
    var_import ::TBCOMPAT::parameter_list_defaults

    if {$args == {}} {
	perror "\[define-template-parameter] not enough arguments!"
	return
    }
    if {[llength $args] > 2} {
	perror "\[define-template-parameter] too many arguments!"
	return
    }
    set value [lindex $args 0]
    set description {}
    
    if {[llength $args] == 2} {
	set description [lindex $args 1]
    }

    if {[info exists parameter_list_defaults($name)]} {
	set value $parameter_list_defaults($name)
    }
    set parameter_list($name) $value
    set parameter_descriptions($name) $description
    
    # And install the name/value in the outer environment.
    uplevel 1 real_set \{$name\} \{$value\}
    
    return 0
}

Simulator instproc make_event {outer event} {
    var_import ::GLOBALS::simulated
    var_import ::TBCOMPAT::osids
    var_import ::TBCOMPAT::hwtype_class

    set obj [lindex $event 0]
    set cmd [lindex $event 1]
    set evargs [lrange $event 2 end]
    set vnode "*"
    set vname ""
    set otype {}
    set etype {}
    set args {}
    set atstring ""

    if {[string index $obj 0] == "#"} {
	return {}
    }

    if {$cmd == {}} {
	perror "Missing event type for $obj"
	return
    }

    if {[$obj info class] == "EventGroup"} {
	set cl [$obj set mytype]
    } else {
	set cl [$obj info class]
    }

    switch -- $cl {
	"Application/Traffic/CBR" {
	    set otype TRAFGEN
	    switch -- $cmd {
		"start" {
		    set etype START
		}
		"stop" {
		    set etype STOP
		}
		"reset" {
		    set etype RESET
		}
		"set" {
		    if {[llength $event] < 4} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set etype MODIFY
		    set arg [lindex $event 3]
		    switch -- [lindex $event 2] {
			"packetSize_" {
			    set args  "PACKETSIZE=$arg"
			}
			"rate_" {
			    set bw [parse_bw $arg]
			    set args  "RATE=$bw"
			}
			"interval_" {
			    set args  "INTERVAL=$arg"
			}
			"iptos_" {
			    set args  "IPTOS=$arg"
			}
			unknown {
			    punsup "at $time $event"
			    return
			}
		    }
		}
		unknown {
		    punsup "at $time $event"
		    return
		}
	    }
	    set vnode [$obj get_node]
	    set vname $obj
	}
	"Agent/TCP/FullTcp" -
	"Agent/TCP/FullTcp/Reno" -
	"Agent/TCP/FullTcp/Newreno" -
	"Agent/TCP/FullTcp/Tahoe" -
	"Agent/TCP/FullTcp/Sack" - 
	"Application/FTP" -
	"Application/Telnet" {
	    # For events sent to NSE, we don't distinguish
	    # between START, STOP and MODIFY coz the entire
	    # string passed to '$ns at' is sent for evaluation to the node
	    # on which NSE is running: fix needed for the
	    # case when the above string has syntax errors. Maybe
	    # just have a way reporting errors back to the
	    # the user from the NSE that finds the syntax errors
	    set otype NSE
	    set etype NSEEVENT
	    set args "\$$obj $cmd [lrange $event 2 end]"
	    set vnode [$obj get_node]
	    set vname $obj
	}
	"EventSequence" {
	    set otype SEQUENCE
	    switch -- $cmd {
		"start" {
		    set etype START
		}
		"run" {
		    set etype RUN
		}
		"reset" {
		    set etype RESET
		}
		unknown {
		    punsup "$obj $cmd $evargs"
		    return
		}
	    }
	    set vnode {}
	    set vname $obj
	}
	"EventTimeline" {
	    set otype TIMELINE
	    switch -- $cmd {
		"start" {
		    set etype START
		}
		"run" {
		    set etype RUN
		}
		"reset" {
		    set etype RESET
		}
		unknown {
		    punsup "$obj $cmd $evargs"
		    return
		}
	    }
	    set vnode {}
	    set vname $obj
	}
	"Link" -
	"Lan" {
	    set otype LINK
	    set vnode {}
	    set vname $obj
	    
	    switch -- $cmd {
		"create"    {set etype CREATE}
		"clear"     {set etype CLEAR}
		"reset"     {set etype RESET}
		"up"	    {set etype UP}
		"down"	    {set etype DOWN}
		"bandwidth" {
		    if {[llength $event] < 4} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set arg   [lindex $event 2]
		    set bw [parse_bw $arg]
		    set args  "BANDWIDTH=$bw"
		    set etype MODIFY
		}
		"delay" {
		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set arg   [lindex $event 2]
		    set args  "DELAY=$arg"
		    set etype MODIFY
		}
		"plr" {
		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    if {[scan [lindex $event 2] "%f" plr] != 1 ||
		    $plr < 0 || $plr > 1} {
			perror "Improper argument: at $time $event"
			return
		    }
		    set args  "PLR=$plr"
		    set etype MODIFY
		}
		"trace" {
		    set otype LINKTRACE
		    set vname "${obj}-tracemon"

		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set action [lindex $event 2]

		    switch -- $action {
			"stop"	    {set etype STOP}
			"start"     {set etype START}
			"kill"	    {set etype KILL}
			"snapshot"  {set etype SNAPSHOT}
			unknown {
			    punsup "at $time $event"
			    return
			}
		    }
		}
		unknown {
		    punsup "at $time $event"
		    return
		}
	    }
	    $obj mustdelay
	}
	"Node" {
	    set otype NODE
	    switch -- $cmd {
		"reboot" {
		    set etype REBOOT
		}
		"snapshot-to" {
		    set etype SNAPSHOT
		    if {[llength $evargs] < 1} {
			perror "Wrong number of arguments: $obj $cmd $evargs"
			return
		    }
		    set image [lindex $evargs 0]
		    if {! ${GLOBALS::anonymous} && ! ${GLOBALS::passmode}} {
			if {![info exists osids($image)]} {
			    perror "Unknown image in snapshot-to event: $image"
			    return
			}
		    }
		    set args "IMAGE=${image}"
		}
		"reload" {
		    set etype RELOAD
		    ::GLOBALS::named-args $evargs {
			-image {}
		    }
		    if {$(-image) != {}} {
			if {! ${GLOBALS::anonymous} && 
			    ! ${GLOBALS::passmode} &&
			    ! [info exists osids($(-image))]} {
			    perror "Unknown image in reload event: $(-image)"
			    return
			}
			set args "IMAGE=$(-image)"
		    }
		}
		"setdest" {
		    set etype SETDEST
		    set topo [$obj set topo]
		    if {$topo == ""} {
			perror "$obj is not located on a topography"
			return
		    }
		    if {[llength $evargs] < 3} {
			perror "Wrong number of arguments: $obj $cmd $evargs; expecting - <obj> setdest <x> <y> <speed>"
			return
		    }
		    set x [lindex $evargs 0]
		    set y [lindex $evargs 1]
		    if {! [$topo checkdest $self $x $y -showerror 1]} {
			return
		    }
		    set speed [lindex $evargs 2]
		    if {$speed != 0.0 && ($speed < 0.1) && ($speed > 0.4)} {
			perror "Speed is currently locked at 0.0 or 0.1-0.4"
			return
		    }
		    ::GLOBALS::named-args [lrange $evargs 3 end] {
			-orientation 0
		    }
		    set args "X=$x Y=$y SPEED=$speed ORIENTATION=$(-orientation)"
		}
		unknown {
		    punsup "$obj $cmd $evargs"
		    return
		}
	    }
	    set vnode {}
	    set vname $obj
	}
	"Queue" {
	    set otype LINK
	    set node [$obj get_node]
	    set lanlink [$obj get_link]
	    set vnode {}
	    set vname "$lanlink-$node"
	    $lanlink mustdelay
	    switch -- $cmd {
		"set" {
		    if {[llength $event] < 4} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set etype MODIFY
		    set arg [lindex $event 3]
		    switch -- [lindex $event 2] {
			"queue-in-bytes_" {
			    set args  "QUEUE-IN-BYTES=$arg"
			}
			"limit_" {
			    set args  "LIMIT=$arg"
			}
			"maxthresh_" {
			    set args  "MAXTHRESH=$arg"
			}
			"thresh_" {
			    set args  "THRESH=$arg"
			}
			"linterm_" {
			    set args  "LINTERM=$arg"
			}
			"q_weight_" {
			    if {[scan $arg "%f" w] != 1} {
				perror "Improper argument: at $time $event"
				return
			    }
			    set args  "Q_WEIGHT=$w"
			}
			unknown {
			    punsup "at $time $event"
			    return
			}
		    }
		}
		"trace" {
		    set otype LINKTRACE
		    set vname "${vname}-tracemon"

		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set action [lindex $event 2]

		    switch -- $action {
			"stop"	    {set etype STOP}
			"start"     {set etype START}
			"kill"	    {set etype KILL}
			"snapshot"  {set etype SNAPSHOT}
			unknown {
			    punsup "at $time $event"
			    return
			}
		    }
		}
		unknown {
		    punsup "at $time $event"
		    return
		}
	    }
	}
	"Program" {
	    set otype PROGRAM
	    set vname $obj
	    if {[$obj info class] == "EventGroup"} {
		set vnode "*"
	    } else {
		set vnode [$obj set node]
	    }
	    
	    switch -- $cmd {
		"set" -
		"run" -
		"start" {
		    switch -- $cmd {
			"set" {
			    set etype MODIFY
			}
			"run" {
			    set etype RUN
			}
			"start" {
			    set etype START
			}
		    }
		    if {[$obj info class] == "EventGroup"} {
			set default_command {}
		    } else {
			set default_command [$obj set command]
		    }
		    ::GLOBALS::named-args $evargs [list \
			-command $default_command \
			-dir {} \
			-timeout {} \
			-expected-exit-code {} \
			-tag {} \
		    ]
		    if {$(-dir) != {}} {
			set args "DIR={$(-dir)} "
		    }
		    if {$(-expected-exit-code) != {}} {
			set args "${args}EXPECTED_EXIT_CODE=$(-expected-exit-code) "
		    }
		    if {$(-tag) != {}} {
			set args "${args}TAG=$(-tag) "
		    }
		    if {$(-timeout) != {}} {
			set to [::GLOBALS::reltime-to-secs $(-timeout)]
			if {$to == -1} {
			    perror "-timeout value is not a relative time: $(-timeout)"
			    return
			} else {
			    set args "${args}TIMEOUT={$to} "
			}
		    }
		    # Put the command last so the program-agent can assume everything
		    # up to the end of the string is part of the command and we don't
		    # have to deal with quoting...  XXX
		    if {$(-command) != {}} {
			set args "${args}COMMAND=$(-command)"
		    }
		}
		"stop" {
		    set etype STOP
		}
		"kill" {
		    set etype KILL
		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: at $time $event"
			return
		    }
		    set arg [lindex $event 2]
		    set args "SIGNAL=$arg"
		}
		unknown {
		    punsup "$obj $cmd $args"
		    return
		}
	    }
	}
	"Console" {
	    set otype CONSOLE
	    set vname $obj

	    switch -- $cmd {
		"start" {
		    set etype START
		}
		"stop" {
		    set etype STOP
		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: $obj $cmd $evargs"
			return
		    }
		    set arg [lindex $event 2]
		    set args "FILE=$arg"
		}
	    }
	}
	"Simulator" {
	    set vnode "*"
	    set vname $self
	    
	    switch -- $cmd {
		"bandwidth" {
		    set otype LINK
		    set etype MODIFY
		    set vnode {}
		    set vname {}
		}
		"halt" {
		    set otype SIMULATOR
		    set etype HALT
		}
		"terminate" {
		    set otype SIMULATOR
		    set etype HALT
		}
		"swapout" {
		    set otype SIMULATOR
		    set etype SWAPOUT
		}
		"stoprun" {
		    set otype SIMULATOR
		    set etype STOPRUN
		}
		"trace-for" {
		    set vname "slothd"
		    set otype SLOTHD
		    set etype START
		    if {[llength $event] < 3} {
			perror "Wrong number of arguments: $obj $cmd $evargs"
			return
		    }
		    set arg [lindex $event 2]
		    set args "DURATION=$arg"
		}
		"stabilize" {
		    set otype SIMULATOR
		    set etype MODIFY
		    set args "mode=stabilize"
		}
		"msg" -
		"message" {
		    set otype SIMULATOR
		    set etype MESSAGE
		    set args "[join $evargs]"
		}
		"log" {
		    set otype SIMULATOR
		    set etype LOG
		    set args "[join $evargs]"
		}
		"report" {
		    set otype SIMULATOR
		    set etype REPORT
		    ::GLOBALS::named-args $evargs {
			-digester {}
			-archive {}
		    }
		    if {$(-digester) != {}} {
			set args "DIGESTER={$(-digester)}"
		    }
		    if {$(-archive) != {}} {
			set args "ARCHIVE={$(-archive)} ${args}"
		    }
		    
		}
		"snapshot" {
		    set otype SIMULATOR
		    set etype SNAPSHOT
		    set args "LOGHOLE_ARGS='-s'"
		}		
		"cleanlogs" {
		    set otype SIMULATOR
		    set etype RESET
		    set args "ASPECT=LOGHOLE"
		}
		"linktest" {
		    set otype LINKTEST
		    set etype START
		    set vname "linktest"
		    ::GLOBALS::named-args $evargs {
			-bw 0
			-stopat 3
		    }
		    if {$(-bw) != 0} {
			set stopat 4
		    }
		    set args "STARTAT=1 STOPAT=$(-stopat)"
		}
		"reset-lans" {
		    set otype LINK
		    set vname "__all_lans"
		    set etype RESET
		}
		unknown {
		    punsup "$obj $cmd $evargs"
		    return
		}
	    }
	}
	unknown {
	    punsup "Unknown object type: $obj $cmd $evargs"
	    return
	}
    }

    if { $otype == "" } {
	perror "\[make_event] otype was empty; event $event, class $cl"
    }

    return [list $vnode $vname $otype $etype $args $atstring]
}

# cost
# Set the cost for a link
Simulator instproc cost {src dst c} {
    set reallink [$self find_link $src $dst]
    $reallink set cost([list $src [$reallink get_port $src]]) $c
}

# Now we have an experiment wide 
# simulation specification. Virtual to physical
# mapping will be done in later stages
Simulator instproc make-simulated {args} {

    var_import ::GLOBALS::simulated
    $self instvar nseconfig
    $self instvar simcode_present

    set simulated 1
    global script
    set script [string trim $args "\{\}"]

    if { $script == {} } {
        set simulated 0
        return
    }

    set simcode_present 1

    # we ignore any type of errors coz they have
    # been caught when we ran the script through NSE
    uplevel 1 $script

    append nseconfig $script
    append nseconfig \n

    set simulated 0
}

#
# Spit out XML
#
Simulator instproc spitxml_init {} {
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid

    # Add a marker so xmlconvert can tell where user output stops and
    puts "#### BEGIN XML ####"
    # ... XML starts.
    puts "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>"
    puts "<virtual_experiment pid='$pid' eid='$eid'>"
}

Simulator instproc spitxml_finish {} {
    puts "</virtual_experiment>"
}

Simulator instproc spitxml_data {tag fields values} {
    ::spitxml_data $tag $fields $values
}

#
# Global function, cause some objects do not hold a sim pointer.
# Should fix.
# 
proc spitxml_data {tag fields values} {
    puts "  <$tag>"
    puts "    <row>"
    foreach field $fields {
	set value  [lindex $values 0]
	set values [lrange $values 1 end]
	set value_esc [xmlencode $value]

	puts "      <$field>$value_esc</$field>"
    }
    puts "    </row>"
    puts "  </$tag>"
}

proc xmlencode {args} {
    set retval [eval append retval $args]
    regsub -all "&" $retval "\\&amp;" retval
    regsub -all "<" $retval "\\&lt;" retval
    regsub -all ">" $retval "\\&gt;" retval
    regsub -all "\"" $retval "\\&\#34;" retval
    regsub -all "]" $retval "\\&\#93;" retval
    
    return $retval
}
