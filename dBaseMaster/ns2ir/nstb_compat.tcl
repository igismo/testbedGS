# -*- tcl -*-
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2004, 2006, 2009 University of Utah and the Flux Group.
# All rights reserved.
#

# This is a nop tb_compact.tcl file that should be used when running scripts
# under ns.

namespace eval GLOBALS {
    variable security_level 0
    variable pid {}
    variable gid {}
    variable eid {}
}

proc tb-set-ip {node ip} {}
proc tb-set-ip-interface {src dst ip} {}
proc tb-set-ip-link {src link ip} {}
proc tb-set-ip-lan {src lan ip} {}
proc tb-set-netmask {lanlink netmask} {}
proc tb-set-hardware {node type args} {}
proc tb-set-node-os {node os} {}
proc tb-set-link-loss {src args} {}
proc tb-set-lan-loss {lan rate} {}
proc tb-set-node-rpms {node args} {}
proc tb-set-node-startup {node cmd} {}
proc tb-set-node-cmdline {node cmd} {}
proc tb-set-node-tarfiles {node args} {}
proc tb-set-node-lan-delay {node lan delay} {}
proc tb-set-node-lan-bandwidth {node lan bw} {}
proc tb-set-node-lan-loss {node lan loss} {}
proc tb-set-node-lan-params {node lan delay bw loss} {}
proc tb-set-node-failure-action {node type} {}
proc tb-set-ip-routing {type} {}
proc tb-fix-node {v p} {}
proc tb-make-weighted-vtype {name weight types} {}
proc tb-make-soft-vtype {name types} {}
proc tb-make-hard-vtype {name types} {}
proc tb-set-lan-simplex-params {lan node todelay tobw toloss fromdelay frombw fromloss} {}
proc tb-set-link-simplex-params {link src delay bw loss} {}
proc tb-set-uselatestwadata {onoff} {}
proc tb-set-usewatunnels {onoff} {}
proc tb-set-wasolver-weights {delay bw plr} {}
proc tb-use-endnodeshaping {onoff} {}
proc tb-force-endnodeshaping {onoff} {}
proc tb-set-multiplexed {link onoff} {}
proc tb-set-endnodeshaping {link onoff} {}
proc tb-set-noshaping {link onoff} {}
proc tb-set-useveth {link onoff} {}
proc tb-set-link-encap {link style} {}
proc tb-set-allowcolocate {lanlink onoff} {}
proc tb-set-colocate-factor {factor} {}
proc tb-set-sync-server {node} {}
proc tb-set-node-usesharednode {node} {}
proc tb-set-mem-usage {usage} {}
proc tb-set-cpu-usage {usage} {}
proc tb-bind-parent {sub phys} {}
proc tb-fix-current-resources {onoff} {}
proc tb-set-encapsulate {onoff} {}
proc tb-set-vlink-emulation {style} {}
proc tb-set-sim-os {os} {}
proc tb-set-jail-os {os} {}
proc tb-set-delay-os {os} {}
proc tb-set-delay-capacity {cap} {}
proc tb-use-ipassign {onoff} {}
proc tb-set-ipassign-args {args} {}
proc tb-set-lan-protocol {lanlink protocol} {}
proc tb-set-link-protocol {lanlink protocol} {}
proc tb-set-lan-accesspoint {lanlink node} {}
proc tb-set-lan-setting {lanlink capkey capval} {}
proc tb-set-node-lan-setting {lanlink node capkey capval} {}
proc tb-use-physnaming {onoff} {}
proc tb-feedback-vnode {vnode hardware args} {}
proc tb-feedback-vlan {vnode lan args} {}
proc tb-feedback-vlink {link args} {}
proc tb-elab-in-elab {onoff} {}
proc tb-elab-in-elab-topology {topo} {}
proc tb-set-inner-elab-eid {eid} {}
proc tb-set-elabinelab-cvstag {cvstag} {}
proc tb-elabinelab-singlenet {} {}
proc tb-set-node-inner-elab-role {node role} {}
proc tb-set-node-id {vnode myid} {}
proc tb-set-link-est-bandwidth {srclink args} {}
proc tb-set-lan-est-bandwidth {lan bw} {}
proc tb-set-node-lan-est-bandwidth {node lan bw} {}
proc tb-set-link-backfill {srclink args} {}
proc tb-set-link-simplex-backfill {link src bw} {}
proc tb-set-lan-backfill {lan bw} {}
proc tb-set-node-lan-backfill {node lan bw} {}
proc tb-set-lan-simplex-backfill {lan node tobw frombw} {}
proc tb-set-node-plab-role {node role} {}
proc tb-set-node-plab-plcnet {node lanlink} {}
proc tb-set-dpdb {onoff} {}
proc tb-fix-interface {vnode lanlink iface} {}
proc tb-set-node-usesharednode {node weight} {}
proc tb-set-node-sharingmode {node sharemode} {}
# DETER Functions
proc tb-allow-external {vnode args} { }
proc tb-set-node-testbed { vnode tb}  { }
proc tb-set-default-failure-action {type} { }

proc tb-set-security-level {level} {

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
    set ::GLOBALS::security_level $level
}

#
# Set the startup command for a node. Replaces the tb-set-node-startup
# command above, but we have to keep that one around for a while. This
# new version dispatched to the node object, which uses a program object.
# 
proc tb-set-node-startcmd {node command} {
    if {[$node info class] != "Node"} {
	perror "\[tb-set-node-startcmd] $node is not a node."
	return
    }
    set command "($command ; /usr/local/etc/emulab/startcmddone \$?)"
    set newprog [$node start-command $command]

    return $newprog
}

#
# Create a program object to run on the node when the experiment starts.
#
Node instproc start-command {command} {
    global hosts

    $self instvar sim
    set newname "$hosts($self)_startcmd"

    set newprog [uplevel 2 "set $newname [new Program]"]
    $newprog set node $self
    $newprog set command $command

    return $newprog
}

Class Program

Program instproc init {args} {
}

Program instproc unknown {m args} {
}

Class Firewall

Firewall instproc init {sim args} {
    global last_fw
    global last_fw_node
    real_set tmp [$sim node]
    real_set last_fw $self
    real_set last_fw_node $tmp
}

Firewall instproc unknown {m args} {
}

Class EventSequence

EventSequence instproc init {args} {
}

EventSequence instproc unknown {m args} {
}

Class EventTimeline

EventTimeline instproc init {args} {
}

EventTimeline instproc unknown {m args} {
}

Class EventGroup

EventGroup instproc init {args} {
}

EventGroup instproc unknown {m args} {
}

Class Console -superclass Agent

Console instproc init {args} {
}

Console instproc unknown {m args} {
}

Topography instproc load_area {args} {
}

Topography instproc checkdest {args} {
    return 1
}

Class NSENode -superclass Node

NSENode instproc make-simulated {args} {
    uplevel 1 eval $args
}

# We are just syntax checking the NS file
Simulator instproc run {args} {
}

Simulator instproc nsenode {args} {
    return [new NSENode]
}

Simulator instproc make-simulated {args} {
    uplevel 1 eval $args
}

Simulator instproc event-sequence {args} {
    $self instvar id_counter

    incr id_counter
    return [new EventSequence]
}

Simulator instproc event-timeline {args} {
    $self instvar id_counter

    incr id_counter
    return [new EventTimeline]
}

Simulator instproc event-group {args} {
    return [new EventGroup]
}

Simulator instproc make-cloud {nodes bw delay args} {
    return [$self make-lan $nodes $bw $delay]
}

Simulator instproc make-new-lan {nodes args} { 
  	set delay 0
    set bw "1Gb"
    set type "static"
    set stv 0 
    set rate 1
    set threshold 0
    if {($args != {})} {
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
	set rt [$self make-lan $nodes $bw $delay]
	set threshold [expr 1.0 - (1.0 - $threshold) * (1.0 - $threshold)]
	set lo [tb-set-lan-loss $rt $threshold]
	return $rt
	#return [$self make-lan $nodes $bw $delay]
	#return [$self make-lan $nodes "1Gb" "30ms"]
}

Simulator instproc make-deter-lan {nodes args} { 
    set delay 0
    set bw "1Gb"
    set type "static"
    set stv 0 
    set rate 0
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
	set rt [$self make-lan $nodes $bw $delay]
	set threshold [expr 1.0 - (1.0 - $threshold) * (1.0 - $threshold)]
	set lo [tb-set-lan-loss $rt $threshold]
	return $rt
	#return [$self make-lan $nodes $bw $delay]
	#return [$self make-lan $nodes "1Gb" "30ms"]
}


Node instproc program-agent {args} {
}

Node instproc topography {args} {
}

Node instproc console {} {
    return [new Console]
}

Node instproc unknown {m args} {
}

Simulator instproc connect {src dst} {
}

Simulator instproc define-template-parameter {name args} {
    # install the name/value in the outer environment.
    set value [lindex $args 0]    
    uplevel 1 set \{$name\} \{$value\}
}

LanNode instproc trace {args} {
}

LanNode instproc trace_endnode {args} {
}

LanNode instproc unknown {m args} {
}
