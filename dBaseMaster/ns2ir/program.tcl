# -*- tcl -*-
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#

######################################################################
# program.tcl
#
# This defines the local program agent.
#
######################################################################

Class Program -superclass NSObject

namespace eval GLOBALS {
    set new_classes(Program) {}
    variable all_programs {}
}

Program instproc init {s} {
    global ::GLOBALS::last_class
    global ::GLOBALS::all_programs

    if {$all_programs == {}} {
	# Create a default event group to hold all program agents.
	set foo [uplevel \#0 "set __all_programs [new EventGroup $s]"]
	set all_programs $foo
    }
    $all_programs add $self

    $self set sim $s
    $self set node {}
    $self set command {}
    $self set dir {}
    $self set timeout {}
    $self set expected-exit-code {}

    # Link simulator to this new object.
    $s add_program $self

    set ::GLOBALS::last_class $self
}

Program instproc rename {old new} {
    global ::GLOBALS::all_programs
    $self instvar sim

    $sim rename_program $old $new
    $all_programs rename-agent $old $new
}

# updatedb DB
# This adds rows to the virt_trafgens table corresponding to this agent.
Program instproc updatedb {DB} {
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::TBCOMPAT::objtypes
    $self instvar node
    $self instvar command
    $self instvar dir
    $self instvar timeout
    $self instvar expected-exit-code
    $self instvar sim

    if {$node == {}} {
	perror "\[updatedb] $self has no node."
	return
    }
    if { [string first \n $command] != -1 } {
	perror "\[updatedb] $self has disallowed newline in command: $command"
	return
    }
    set progvnode $node

    #
    # if the attached node is a simulated one, we attach the
    # program to the physical node on which the simulation runs
    #
    if {$progvnode != "ops"} {
	if { [$node set simulated] == 1 } {
	    set progvnode [$node set nsenode]
	}
    }

    # Update the DB
    spitxml_data "virt_programs" [list "vnode" "vname" "command" "dir" "timeout" "expected_exit_code"] [list $progvnode $self $command $dir $timeout ${expected-exit-code} ]

    $sim spitxml_data "virt_agents" [list "vnode" "vname" "objecttype" ] [list $progvnode $self $objtypes(PROGRAM) ]
}

Program instproc tcl_out { tb ngws } {
    $self instvar node
    $self instvar command
    $self instvar dir
    $self instvar timeout
    $self instvar expected-exit-code
    $self instvar sim

    set ptb [ [$self set node] set testbed ]
    set name [ name2var $self ]
    set gw ""

    # XXX: this is not carefully thought through
    # Don't spit out startcmds.  They're handled elsewhere.
    if { [ regexp ".*startcmd.*" $name] } { return; }

    if { $tb == $ptb } {
	set ref [name2ref [$self set node]]
	set cmd "set $name \[ $ref program-agent -command \"$command\""
    } else {
	foreach g $ngws {
	    set gtb [lindex $g 0] 
	    set gname [lindex $g 1]
	    set gtype [lindex $g 3]

	    if { $gtb == $ptb && ( $gtype == "both" || $gtype == "control") } {
		set ref [name2ref $gname ]
		break;
	    }
	}
	set cmd "set $name \[ $ref program-agent -command \"DUMMY_PROGRAM\""
    }
	    
    if { $dir != "{}" && $dir != {} } { 
	set cmd "$cmd -dir \"$dir\""
    }

    if { $timeout > 0 } {
	set cmd "$cmd -timeout $timeout"
    }

    if { ${expected-exit-code} != "" } {
	set cmd "$cmd -expected-exit-code ${expected-exit-code}"
    }
    set cmd "$cmd\]"

    puts $cmd
}


