# -*- tcl -*-
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2009 University of Utah and the Flux Group.
# All rights reserved.
#

######################################################################
# node.tcl
#
# This defines the Node class.  Instances of this class are created by
# the 'node' method of Simulator.  A Node is connected to a number of
# LanLinks.  Each such connection is associated with a virtual port,
# an integer.  Each virtual port also has an IP address.  Virtual
# ports start at 0 and go up continuously.  Besides the port
# information each node also has a variety of strings.  These strings
# are set by tb-* commands and dumped to the DB but are otherwise
# uninterpreted.
######################################################################

Class Node -superclass NSObject

Node instproc init {s} {
    # The global default
    var_import ::GLOBALS::default_failureaction
    $self set sim $s

    # portlist is a list of connections for the node.  It is sorted
    # by portnumber.  I.e. the ith element of portlist is the connection
    # on port i.
    $self set portlist {}

    # A list of agents attached to this node.
    $self set agentlist {}

    # A counter for udp/tcp portnumbers. Assign them in an increasing
    # fashion as agents are assigned to the node.
    $self set next_portnumber_ 5000

    # iplist, like portlist, is supported by portnumber.  An entry of
    # {} indicates an unassigned IP address for that port.
    $self set iplist {}

    # A route list. 
    $self instvar routelist
    array set routelist {}

    # The type of the node.
    $self set type "pc" 

    # Is remote flag. Used when we do IP assignment later.
    $self set isremote 0

    # Sorta ditto for virt.
    $self set isvirt 0

    # If hosting a virtual node (or nodes).
    $self set virthost 0

    # Sorta ditto for subnode stuff.
    $self set issubnode    0
    $self set subnodehost  0
    $self set subnodechild ""

    # If osid remains blank when updatedb is called it is changed
    # to the default OS based on it's type (taken from node_types
    # table).
    $self set osid ""

    # We have this for a bad, bad reason.  We can't expose $vhost variables
    # in ns files because assign can't yet handle fixing vnodes to vhosts (or
    # any other mapping constraints, since that would imply multiple levels 
    # of mapping)... so when you set the parent_osid for vnodes, you are 
    # setting the os for the vhost itself; the osid is the os for the vnode.
    #
    # If the osid gets set to a subosid, we set parent_osid to the default
    # parent_osid for that osid in updatedb.
    $self set parent_osid ""

    # Start with an empty set of desires
    $self instvar desirelist
    array set desirelist {}

    # These are just various strings that we pass through to the DB.
    $self set cmdline ""
    $self set rpms ""
    $self set startup ""
    $self set tarfiles ""
    $self set failureaction $::GLOBALS::default_failureaction
    $self set inner_elab_role ""
    $self set plab_role "none"
    $self set plab_plcnet "none"
    $self set fixed ""
    $self set nseconfig ""
    $self set sharing_mode ""

    $self set topo ""

    $self set X_ ""
    $self set Y_ ""
    $self set Z_ 0.0
    $self set orientation_ 0.0

    set cname "${self}-console"
    Console $cname $s $self
    $s add_console $cname
    $self set console_ $cname

    if { ${::GLOBALS::simulated} == 1 } {
	$self set simulated 1
    } else {
	$self set simulated 0
    }
    $self set nsenode_vportlist {}

    # This is a mote thing.
    $self set numeric_id {}

    # Testbed ID for federated experiments
    $self set testbed {}
    # XXX startcmd hack for federation.
    $self set fed_start_cmd ""
    # if true this is a dynamic external access node (tunnel node)
    $self set portalparams {}
    # A place to put interfaces.  The links and lans will put info here
    $self set topdl_interfaces {}
    # topdl attributes attached to this node
    $self set topdl_attributes {}
    # topdl os nested classes
    $self set topdl_os {}
    # topdl CPU nested classes
    $self set topdl_cpu {}
    # topdl Storage nested classes
    $self set topdl_storage {}
    # topdl localnames
    $self set topdl_localname {}
    # topdl status
    $self set topdl_status {}

}

# The following procs support renaming (see README)
Node instproc rename {old new} {
    $self instvar portlist
    $self instvar console_

    foreach object $portlist {
	$object rename_node $old $new
    }
    [$self set sim] rename_node $old $new
    $console_ set node $new
    $console_ rename "${old}-console" "${new}-console"
    uplevel "#0" rename "${old}-console" "${new}-console"
    set console_ ${new}-console
}

Node instproc rename_lanlink {old new} {
    $self instvar portlist
    set newportlist {}
    foreach node $portlist {
	if {$node == $old} {
	    lappend newportlist $new
	} else {
	    lappend newportlist $node
	}
    }
    set portlist $newportlist
}

# updatedb DB
# This adds a row to the virt_nodes table corresponding to this node.
Node instproc updatedb {DB} {
    $self instvar portlist
    $self instvar type
    $self instvar osid
    $self instvar parent_osid
    $self instvar cmdline
    $self instvar rpms
    $self instvar startup
    $self instvar iplist
    $self instvar tarfiles
    $self instvar failureaction
    $self instvar inner_elab_role
    $self instvar plab_role
    $self instvar plab_plcnet
    $self instvar routertype
    $self instvar fixed
    $self instvar agentlist
    $self instvar routelist
    $self instvar sim
    $self instvar isvirt
    $self instvar virthost
    $self instvar issubnode
    $self instvar desirelist
    $self instvar nseconfig
    $self instvar simulated
    $self instvar sharing_mode
    $self instvar topo
    $self instvar X_
    $self instvar Y_
    $self instvar orientation_
    $self instvar numeric_id
    var_import ::TBCOMPAT::default_osids
    var_import ::TBCOMPAT::subosids
    var_import ::GLOBALS::use_physnaming
    var_import ::TBCOMPAT::physnodes
    var_import ::TBCOMPAT::objtypes
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::default_ip_routing_type
    var_import ::GLOBALS::enforce_user_restrictions
    var_import ::TBCOMPAT::hwtype_class

    #
    # Reserved name; conflicts with kludgy manner in which a program
    # agent can used on ops.
    #
    if {"$self" == "ops"} {
	perror "You may not use the name for 'ops' for a node!"
	return
    }
    
    # If we haven't specified a osid so far then we should fill it
    # with the id from the node_types table now.
    if {$osid == {}} {
	if {$virthost == 0} {
	    if {[info exists default_osids($type)]} {
		set osid $default_osids($type)
	    }
	}
    } else {
	# Do not allow user to set os for virt nodes at this time.
	#if {$enforce_user_restrictions && $isvirt} {
	#    perror "You may not specify an OS for virtual nodes ($self)!"
	#    return
	#}
	# Do not allow user to set os for host running virt nodes.
	if {$enforce_user_restrictions && $virthost} {
	    perror "You may not specify an OS for hosting virtnodes ($self)!"
	    return
	}
    }

    #
    # If the osid is a subosid and there is no parent, choose the default
    # one now.
    # XXX don't do this for now -- an OS can be both a subOS and a regular OS
    # (i.e., windows), and we don't want to imply to Emulab that there should
    # be a subOS if the user doesn't force it.
    #
    #if {[info exists $subosids($osid)] && $parent_osid == ""} {
    #    set parent_osid [lindex $subosids($osid) 0]
    #}

    #
    # If the osid won't run on the specified parent, die.
    #
    if {$parent_osid != "" && $osid != "" 
	&& [lsearch -exact $subosids($osid) $parent_osid] == -1} {
	perror "subOSID $osid does not run on parent OSID $parent_osid!"
	return
    }

    #
    # If a subnode, then it must be fixed to a pnode, or we have to
    # create one on the fly and set the type properly. 
    # 
    if {$issubnode && $fixed == ""} {
        # XXX - hack for motes, to make Jay happy
        set hosttype "pc"
        if {$type == "mica2" || $type == "mica"} {
            set hosttype "mote-host"
        }
	$sim spitxml_data "virt_nodes" [list "vname" "type" "ips" "osname" "cmd_line" "rpms" "startupcmd" "tarfiles" "fixed" ] [list "host-$self" "$hosttype" "" "" "" "" "" "" "" ]
	$sim spitxml_data "virt_node_desires" [list "vname" "desire" "weight"] [list "host-$self" "hosts-$type" 1.0]
	set fixed "host-$self"
    }

    # Implicitly fix node if not already fixed.
    if { $issubnode == 0 && $use_physnaming == 1 && $fixed == "" } {
	if {[info exists physnodes($self)]} {
		set fixed $self
	}
    }

    # We need to generate the IP column from our iplist.
    set ipraw {}
    set i 0
    foreach ip $iplist {
	if { $ip == {} } {
	    # Give a dummy IP address if none has been set
	    set ip "0.0.0.0"
	}
	lappend ipraw $i:$ip
	incr i
    }

    foreach agent $agentlist {
	$agent updatedb $DB

	if {[$agent set application] != {}} {
	    $sim agentinit [$agent set application]
	}

	# The following is for NSE traffic generation
	# Simulated nodes in make-simulated should not be doing this
	if { $simulated != 1 } { 
	    append nseconfig [$agent get_nseconfig]
	}
    }

     if {$nseconfig != {}} {

       set nsecfg_script ""
       set simu [lindex [Simulator info instances] 0]
       append nsecfg_script "set $simu \[new Simulator]\n"
       append nsecfg_script "\$$simu set tbname \{$simu\}\n"
       append nsecfg_script "\$$simu use-scheduler RealTime\n\n"
       append nseconfig "set nsetrafgen_present 1\n\n"
       append nsecfg_script $nseconfig

        # update the per-node nseconfigs table in the DB
	$sim spitxml_data "nseconfigs" [list "vname" "nseconfig"] [list "$self" "$nsecfg_script"]
    }

    $self add_routes_to_DB $DB

    # Update the DB
    set fields [list "vname" "type" "ips" "osname" "cmd_line" "rpms" "startupcmd" "tarfiles" "failureaction" "routertype" "fixed" ]
    set values [list $self $type $ipraw $osid $cmdline $rpms $startup $tarfiles $failureaction $default_ip_routing_type $fixed ]

    if { $inner_elab_role != "" } {
	lappend fields "inner_elab_role"
	lappend values $inner_elab_role
    }

    if { $plab_role != "none" } {
	lappend fields "plab_role"
	lappend values $plab_role
    }

    if { $plab_plcnet != "" } {
	lappend fields "plab_plcnet"
	lappend values $plab_plcnet
    }

    if { $sharing_mode != "" } {
	lappend fields "sharing_mode"
	lappend values $sharing_mode
    }

    if { $numeric_id != {} } {
	lappend fields "numeric_id"
	lappend values $numeric_id
    }

    if { $parent_osid != {} && $parent_osid != 0} {
	lappend fields "parent_osname"
	lappend values $parent_osid
    }

    $sim spitxml_data "virt_nodes" $fields $values

    if {$topo != "" && ($type == "robot" || $hwtype_class($type) == "robot")} {
	if {$X_ == "" || $Y_ == ""} {
	    perror "node \"$self\" has no initial position"
	    return
	}

	if {! [$topo checkdest $self $X_ $Y_ -showerror 1]} {
	    return
	}

	$sim spitxml_data "virt_node_startloc" \
		[list "vname" "building" "floor" "loc_x" "loc_y" "orientation"] \
		[list $self [$topo set area_name] "" $X_ $Y_ $orientation_]
    }
    
    # Put in the desires, too
    foreach desire [lsort [array names desirelist]] {
	set weight $desirelist($desire)
	$sim spitxml_data "virt_node_desires" [list "vname" "desire" "weight"] [list $self $desire $weight]
    }

    $sim spitxml_data "virt_agents" [list "vnode" "vname" "objecttype"] [list $self $self $objtypes(NODE)]

    # If the node is a portal node (is requesting external access) create a
    # dummy node of type tunnelip that assign will use to allocate external IP
    # addresses.  Include a virt_parameter that maps from the added node
    # name to the requesting node name (portal_request_<vnode>). Other
    # parameters are treated as keyword value pairs, each resulting in a virt
    # parameter with a name of the form portal_<keyword>_<node>
    set pparams [ $self set portalparams]
    if { [llength $pparams] > 0 } {
	var_import ::GLOBALS::ipaddr_nodes

	set fields [ list "name" "value" ]
	set type [lindex $pparams 0]
	set pparams [lrange $pparams 1 end]

	if { $type ne "shared" } {
	    set ipaddr_node_name "external_ipaddr$ipaddr_nodes"
	    incr ipaddr_nodes

	    Node $ipaddr_node_name [$self set sim]
	    $ipaddr_node_name set type $type
	    set vals [ list "portal_request_$self" $ipaddr_node_name ]
	    $sim spitxml_data "virt_parameters" $fields $vals
	}

	while { [ llength $pparams ] > 0 } {
	    set name [lindex $pparams 0]
	    set value [lindex $pparams 1]
	    set pparams [lrange $pparams 2 end]
	    set vals [list "portal_${name}_$self" $value ]

	    # in lieu of tb-fix-node to specify which cookie
	    if {$name ne "ipcookie"} {
		$sim spitxml_data "virt_parameters" $fields $vals
	    } else {
		$ipaddr_node_name set_fixed $value
	    }
	}
	if { $type ne "shared" } {
	    $ipaddr_node_name updatedb $DB
	}
    }
}

# Output the node as tcl commands
Node instproc tcl_out {tb gtars grpms} {
    $self instvar portlist
    $self instvar type
    $self instvar osid
    $self instvar cmdline
    $self instvar rpms
    $self instvar startup
    $self instvar iplist
    $self instvar tarfiles
    $self instvar failureaction
    $self instvar inner_elab_role
    $self instvar plab_role
    $self instvar routertype
    $self instvar fixed
    $self instvar agentlist
    $self instvar routelist
    $self instvar sim
    $self instvar isvirt
    $self instvar virthost
    $self instvar issubnode
    $self instvar desirelist
    $self instvar nseconfig
    $self instvar simulated
    $self instvar topo
    $self instvar X_
    $self instvar Y_
    $self instvar orientation_
    $self instvar numeric_id
    $self instvar testbed
    $self instvar fed_start_cmd
    var_import ::TBCOMPAT::default_osids
    var_import ::GLOBALS::use_physnaming
    var_import ::TBCOMPAT::physnodes
    var_import ::TBCOMPAT::objtypes
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::include_fedkit
    var_import ::GLOBALS::default_ip_routing_type
    var_import ::GLOBALS::enforce_user_restrictions
    var_import ::TBCOMPAT::hwtype_class
    
    # Implicitly fix node if not already fixed.
    if { $issubnode == 0 && $use_physnaming == 1 && $fixed == "" } {
	if {[info exists physnodes($self)]} {
		set fixed $self
	}
    }

    # We need to generate the IP column from our iplist.
    set ipraw {}
    set i 0
    foreach ip $iplist {
	if { $ip == {} } {
	    # Give a dummy IP address if none has been set
	    set ip "0.0.0.0"
	}
	lappend ipraw $i:$ip
	incr i
    }

    foreach agent $agentlist {
	$agent tcl_out $tb

	if {[$agent set application] != {}} {
	    $sim agentinit [$agent set application]
	}

    }

    # XXX: check this one
    $self add_routes_to_DB $tb

    # Restore array names
    set name [ name2var $self ]
    set ref [ name2ref $self ]

    # Put out key node info
    if { $testbed == $tb } {
    	puts "set $name \[\$ns node\]";
    	if { $type != {} } { puts "tb-set-hardware $ref $type"; }
    	if { $osid != {} } { puts "tb-set-node-os $ref $osid"; }
    	if { $tarfiles != {} } { 
	    # If the global flag is set, include the federation kit as one of
	    # the tarfiles to be imported.  There's no reason to add it to the
	    # list of tarfiles as the fedd will handle that.
	    if { $::GLOBALS::include_fedkit } {
		set local_tarfiles "FEDKIT"
	    } else {
		set local_tarfiles ""
	    }
	    # Remap the tarfile sources to be federated paths and put a copy of
	    # the original name into the gtars pass-by-reference parameter
	    foreach t [split $tarfiles ";"] {
		set tdir [lindex [split $t " "] 0]
		set tfile [lindex [split $t " "] 1]
		# gtars is the name of an associative array in the callers
		# context that has a key set for every tar file that needs to
		# be shipped to the federated testbeds.  This code sets that
		# array iff $gtars contains the array's name.
		if { $gtars != "" } { 
		    upvar 1 $gtars gtarsp 
		    set gtarsp($tfile) 1
		}

		# Now remap tfile to the federated rpm repository
		set tfile [ regsub ".*/" $tfile "PROJDIR/tarfiles/EID/" ]
		if { $local_tarfiles != "" } {
		    set local_tarfiles "$local_tarfiles $tdir $tfile"
		} else {
		    set local_tarfiles "$tdir $tfile"
		}
	    }
	    puts "tb-set-node-tarfiles $ref $local_tarfiles"; 
    	} else {
	    if { $::GLOBALS::include_fedkit } {
		puts "tb-set-node-tarfiles $ref FEDKIT"; 
	    }
	}
    	if { $rpms != {} } { 
	    # Remap the rpm sources to be federated paths and put a copy of
	    # the original name into the grpms pass-by-reference parameter
	    set local_rpms ""
	    foreach t [split $rpms ";"] {
		# grpms is the name of an associative array in the callers
		# context that has a key set for every rpm file that needs to
		# be shipped to the federated testbeds.  This code sets that
		# array iff $gtars contains the array's name.
		if { $grpms != "" } { 
		    upvar 1 $grpms grpmsp 
		    set grpmsp($t) 1
		}

		# Now remap tfile to the federated rpm repository
		set t [ regsub ".*/" $t "PROJDIR/rpms/EID/" ]
		if { $local_rpms != "" } {
		    set local_rpms "$local_rpms $t"
		} else {
		    set local_rpms "$t"
		}
	    }
	    puts "tb-set-node-rpms $ref $local_rpms";
    	}
	
    	if { $startup != {} } {
	    puts "tb-set-node-startup $ref \"$startup\"";
    	}
	
    	if { $testbed != {} } {
	    puts "# tb-set-node-testbed $ref \"$testbed\"";
    	}

	if { $fed_start_cmd != "" } {
	    puts "tb-set-node-startcmd $ref \"EXPSTART \\\$USER \'$fed_start_cmd\'\""
	} else { 
	    puts "tb-set-node-startcmd $ref \"EXPSTART\""
	}

	if { $failureaction != "" } {
	    puts "tb-set-node-failure-action $ref \"$failureaction\"";
	}
    }

}

Node instproc escape_xml {value } {
    # Basic xml escapes
    regsub -all "\&" $value "\\&amp;" value
    regsub -all "\<" $value "\\&lt;" value
    regsub -all "\>" $value "\\&gt;" value
    return $value
}

Node instproc topdl_attr { name value } {
    # Put a list consisting of the attribute and value into the topdl
    # attributes list.  Escape the value for xml
    $self instvar topdl_attributes
    lappend topdl_attributes [list $name $value ]
}

Node instproc output_topdl_attr { attr } {
    set name [$self escape_xml [lindex $attr 0]]
    set value [$self escape_xml [lindex $attr 1]]

    puts "<attribute>"
    puts "<attribute>$name</attribute>"
    puts "<value>$value</value>"
    puts "</attribute>"
}

Node instproc add_topdl_os { args } {
    $self instvar topdl_os
    # We actually have to give the os a name, hence the "$self..."
    lappend topdl_os [eval OperatingSystem "$self-os-[llength $topdl_os]" $args]
}

Node instproc add_topdl_cpu { args } {
    $self instvar topdl_cpu
    # We actually have to give the cpu a name, hence the "$self..."
    lappend topdl_cpu [eval CPU "$self-cpu-[llength $topdl_cpu]" $args]
}

Node instproc add_topdl_storage { amount persistence args } {
    $self instvar topdl_storage
    # We actually have to give the storage a name, hence the "$self..."
    lappend topdl_storage [eval Storage "$self-storage-[llength $topdl_storage]" $amount $persistence $args]
}
Node instproc add_topdl_localname { name } {
    $self instvar topdl_localname
    lappend topdl_localname $name
}

Node instproc set_topdl_status { status } {
    $self set topdl_status $status
}

# Output the node as topdl 
Node instproc topdl_out {} {
    $self instvar type
    $self instvar osid
    $self instvar rpms
    $self instvar testbed
    $self instvar fed_start_cmd
    $self instvar tarfiles
    $self instvar failureaction
    $self instvar agentlist
    $self instvar sim
    $self instvar topdl_interfaces
    $self instvar topdl_attributes
    $self instvar topdl_os
    $self instvar topdl_cpu
    $self instvar topdl_storage
    $self instvar topdl_localname
    $self instvar topdl_status
    $self instvar fixed

    # XXX:
    foreach agent $agentlist {
	#$agent tcl_out $tb

	if {[$agent set application] != {}} {
	    $sim agentinit [$agent set application]
	}

    }
    # Output an empty os with the osid attribute
    if { $osid != {} } { 
	$self add_topdl_os "" "" "" "" "osid" $osid
    }

    puts "<elements>"
    puts "<computer>"
    puts "<name>$self</name>"
    foreach os $topdl_os {
	puts "<os>"
	$os topdl_out
	puts "</os>"
    }
    foreach cpu $topdl_cpu {
	puts "<cpu>"
	$cpu topdl_out
	puts "</cpu>"
    }
    foreach storage $topdl_storage {
	puts "<storage>"
	$storage topdl_out
	puts "</storage>"
    }
    foreach localname $topdl_localname {
	puts "<localname>$localname</localname>"
    }

    if { [string length $topdl_status] > 0 } {
	puts "<status>$topdl_status</status>"
    }

    if { $fixed != {} } { $self topdl_attr "fixed" $fixed } 
    if { $type != {} } { $self topdl_attr "type" $type }
    if { $tarfiles != {} } {
	foreach t [split $tarfiles ";"] {
	    set tdir [lindex [split $t " "] 0]
	    set tfile [lindex [split $t " "] 1]
	    puts "<software>"
	    puts "<install>$tdir</install><location>$tfile</location>"
	    puts "</software>"
	}
    }

    if { $rpms != {} } {
	foreach t [split $rpms ";"] {
	    puts "<software>"
	    puts "<location>$t</location>"
	    puts "</software>"
	}
    }

    if { $fed_start_cmd != {} } { $self topdl_attr "startup" $fed_start_cmd }
    if { $testbed != {} } { $self topdl_attr "testbed" $testbed }
    if { $failureaction != {} } {
	$self topdl_attr "failureaction" $failureaction
    }
    foreach a $topdl_attributes {
	$self output_topdl_attr $a
    }

    foreach i $topdl_interfaces {
	puts "<interface>"
	puts "<substrate>[lindex $i 0]</substrate>"
	puts "<capacity><rate>[lindex $i 1]</rate><kind>max</kind></capacity>"
	if { [lindex $i 2 ] > 0 } {
	    puts "<latency><time>[lindex $i 2]</time><kind>max</kind></latency>"
	}
	set attrs [lindex $i 5]

	if { [lindex $i 3] > 0 } {
	    lappend attrs [list "loss" [lindex $i 3] ]
	}
	if { [lindex $i 4] != {} } {
	    lappend attrs [list "ip4_address" [lindex $i 4] ]
	    lappend attrs [ list "ip4_netmask" [ [lindex $i 0] get_netmask] ]
	}
	foreach a $attrs {
	    $self output_topdl_attr $a
	}
	puts "</interface>"
    }
    puts "</computer>"
    puts "</elements>"
}

# add_lanlink lanlink
# This creates a new virtual port and connects the specified LanLink to it.
# The port number is returned.
Node instproc add_lanlink {lanlink} {
    $self instvar portlist
    $self instvar iplist
    $self instvar simulated

    # Check if we're making too many lanlinks to this node
    # XXX Could come from db from node_types if necessary
    # For now, no more than 4 links or interfaces per node
    # XXX Ignore if the lanlink is simulated i.e. one that
    # has all simulated nodes in it. 
#    set maxlanlinks 4
#    if { [$lanlink set simulated] != 1 && $maxlanlinks == [llength $portlist] } {
#	# adding this one would put us over
#	perror "Too many links/LANs to node $self! Maximum is $maxlanlinks."
#    }

    lappend portlist $lanlink
    lappend iplist ""
    return [expr [llength $portlist] - 1]
}

#
# Find the lan that both nodes are attached to. Very bad. If more than
# comman lan, returns the first.
#
Node instproc find_commonlan {node} {
    $self instvar portlist
    set match -1

    foreach ll $portlist {
	set match [$node find_port $ll]
	if {$match != -1} {
	    return $ll
	}
    }
    return {}
}

# ip port
# ip port ip
# In the first form this returns the IP address associated with the port.
# In the second from this sets the IP address of a port.
Node instproc ip {port args} {
    $self instvar iplist
    $self instvar sim
    if {$args == {}} {
	return [lindex $iplist $port]
    } else {
	set ip [lindex $args 0]
	set iplist [lreplace $iplist $port $port $ip]
    }    
}

# find_port lanlink
# This takes a lanlink and returns the port it is connected to or 
# -1 if there is no connection.
Node instproc find_port {lanlink} {
    return [lsearch [$self set portlist] $lanlink]
}


# find_port lanlink
# This takes a lanlink and returns the port it is connected to or 
# -1 if there is no connection.
Node instproc find_ip {lanlink} {
    set idx [lsearch [$self set portlist] $lanlink]
    if { $idx == -1 } {
	return -1
    } else {
	return [lindex [$self set iplist] $idx]
    }
}

# Attach an agent to a node. This mainly a bookkeeping device so
# that the we can update the DB at the end.
Node instproc attach-agent {agent} {
    $self instvar agentlist

    lappend agentlist $agent
    $agent set_node $self
}

#
# Return and bump next agent portnumber,
Node instproc next_portnumber {} {
    $self instvar next_portnumber_
    
    set next_port [incr next_portnumber_]
    return $next_port
}

#
# Add a route.
# The nexthop to <dst> from this node is <target>.
#
Node instproc add-route {dst nexthop} {
    $self instvar routelist

    if {[info exists routelist($dst)]} {
	perror "\[add-route] route from $self to $dst already exists!"
    }
    set routelist($dst) $nexthop
}

#
# Set the type/isremote/isvirt for a node. Called from tb_compat.
#
Node instproc set_hwtype {hwtype isrem isv issub} {
    $self instvar type
    $self instvar isremote
    $self instvar isvirt
    $self instvar issubnode

    set type $hwtype
    set isremote $isrem
    set isvirt $isv
    set issubnode $issub
}

#
# Fix a node. Watch for fixing a node to another node.
#
Node instproc set_fixed {pnode} {
    var_import ::TBCOMPAT::location_info
    var_import ::TBCOMPAT::physnodes
    $self instvar type
    $self instvar topo
    $self instvar fixed
    $self instvar issubnode
    $self instvar isvirt

    if { [Node info instances $pnode] != {} } {
        # $pnode is an object instance of class Node
	if {$issubnode} {
	    $pnode set subnodehost 1
	    $pnode set subnodechild $self
	} else {
	    perror "\[set-fixed] Improper fix-node $self to $pnode!"
	    return
	}
    }
    set fixed $pnode

    if {$isvirt == 0 && [info exists physnodes($pnode)]} {
	set type $physnodes($pnode)
	
	if {$topo != ""} {
	    set building [$topo set area_name]
	    if {$building != {} && 
	    [info exists location_info($fixed,$building,x)]} {
		$self set X_ $location_info($fixed,$building,x)
		$self set Y_ $location_info($fixed,$building,y)
		$self set Z_ $location_info($fixed,$building,z)
	    }
	}
    }
}

#
# Update DB with routes
#
Node instproc add_routes_to_DB {DB} {
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    $self instvar routelist
    $self instvar sim

    foreach dst [lsort [array names routelist]] {
	set hop $routelist($dst)
	set port -1

	#
	# Convert hop to IP address. Need to find the link between the
	# this node and the hop. This is easy if its a link. If its
	# a lan, then its ugly.
	#
	set hoplink [$sim find_link $self $hop]
	if {$hoplink == {}} {
	    set hoplan [$self find_commonlan $hop]
	    set port [$hop find_port $hoplan]
	    set srcip [$self ip [$self find_port $hoplan]]
	} else {
	    set port [$hop find_port $hoplink]
	    set srcip [$self ip [$self find_port $hoplink]]
	}
	if {$port == -1} {
	    perror "\[add-route] Cannot find a link from $self to $hop!"
	    return
	}
	set hopip [$hop ip $port]
	
	#
	# Convert dst to IP address.
	#
	switch -- [$dst info class] {
	    "Node" {
		if {[llength [$dst set portlist]] != 1} {
		    perror "\[add-route] $dst must have only one link."
		}
		set link  [lindex [$dst set portlist] 0]
		set mask  [$link get_netmask]
		set dstip [$dst ip 0]
		set type  "host"
	    }
	    "SimplexLink" {
		set link  [$dst set mylink]
		set mask  [$link get_netmask]
		set src   [$link set src_node]
		set dstip [$src ip [$src find_port $link]]
		set type  "net"
	    }
	    "Link" {
		set dstip [$dst get_subnet]
		set mask  [$dst get_netmask]
		set type  "net"
	    }
	    "Lan" {
		set dstip [$dst get_subnet]
		set mask  [$dst get_netmask]
		set type  "net"
	    }
	    unknown {
		perror "\[add-route] Bad argument. Must be a node or a link."
		return
	    }
	}
	$sim spitxml_data "virt_routes" [list "vname" "src" "dst" "nexthop" "dst_type" "dst_mask"] [list $self $srcip $dstip $hopip $type $mask]
    }
}

#
# Create a program object to run on the node when the experiment starts.
#
Node instproc start-command {command} {
    $self instvar sim
    $self instvar fed_start_cmd
    var_import ::GLOBALS::splitmode


    set newname "${self}_startcmd"

    set newprog [uplevel 2 "set $newname [new Program $sim]"]
    $newprog set node $self
    $newprog set command $command

    # Starts at time 0
    $sim at 0  "$newprog start"

    # XXX just capture the user command if we're splitting.  NB this depends on
    # the small change in tb_compat.tcl.in to avoid extra code being inserted
    # to return an exit code.
    if { $::GLOBALS::splitmode } {
	set fed_start_cmd $command;
    }

    return $newprog
}

#
# Add a desire to the node, with the given weight
# Fails if the desire already exists, but maybe it could just update the
# weight?
#
Node instproc add-desire {desire weight} {
    $self instvar desirelist
    if {[info exists desirelist($desire)]} {
	perror "\[add-desire] Desire $desire on $self already exists!"
    }
    set desirelist($desire) $weight
}

Node instproc program-agent {args} {
    
    ::GLOBALS::named-args $args { 
	-command {} -dir {} -timeout {} -expected-exit-code {}
    }

    set curprog [new Program [$self set sim]]
    $curprog set node $self
    $curprog set command $(-command)
    $curprog set dir "{$(-dir)}"
    $curprog set expected-exit-code $(-expected-exit-code)
    if {$(-timeout) != {}} {
	set to [::GLOBALS::reltime-to-secs $(-timeout)]
	if {$to == -1} {
	    perror "-timeout value is not a relative time: $(-timeout)"
	    return
	} else {
	    $curprog set timeout $to
	}
    }

    return $curprog
}

Node instproc topography {topo} {
    var_import ::TBCOMPAT::location_info
    $self instvar sim
    $self instvar fixed

    if {$topo == ""} {
	$self set topo ""
	return
    } elseif {$topo != "" && ! [$topo info class Topography]} {
	perror "\[topography] $topo is not a Topography."
	return
    } elseif {! [$topo initialized]} {
	perror "\[topography] $topo is not initialized."
	return
    }

    $self set topo $topo

    $topo set sim $sim; # Need to link the topography to the simulator here.
    $sim add_topography $topo

    if {$fixed != ""} {
	set building [$topo set area_name]
	if {$building != {} && 
	    [info exists location_info($fixed,$building,x)]} {
	    $self set X_ $location_info($fixed,$building,x)
	    $self set Y_ $location_info($fixed,$building,y)
	    $self set Z_ $location_info($fixed,$building,z)
	}
    } elseif {[$self set type] == "pc"} {
	$self set type "robot"
    }
}

Node instproc console {} {
    $self instvar console_
    
    return $console_
}

#
# Set numeric ID (a mote thing)
#
Node instproc set_numeric_id {myid} {
    $self instvar numeric_id

    set numeric_id $myid
}

Node instproc add_topdl_interface { i } {
    $self instvar topdl_interfaces

    lappend topdl_interfaces $i
}

# Topdl classes - just add attributes to them and output them.  No effect on
# allocaton or the DETER database.
# See docs at http://fedd.deterlab.net/wiki/TopdlLibrary
#
Class TopdlClass -superclass NSObject

TopdlClass instproc escape_xml {value } {
    # Basic xml escapes
    regsub -all "\&" $value "\\&amp;" value
    regsub -all "\<" $value "\\&lt;" value
    regsub -all "\>" $value "\\&gt;" value
    return $value
}

TopdlClass instproc topdl_attr { name value } {
    # Put a list consisting of the attribute and value into the topdl
    # attributes list.  Escape the value for xml
    $self instvar topdl_attributes
    lappend topdl_attributes [list $name $value ]
}

TopdlClass instproc output_topdl_attr { attr } {
    set name [$self escape_xml [lindex $attr 0]]
    set value [$self escape_xml [lindex $attr 1]]

    puts "<attribute>"
    puts "<attribute>$name</attribute>"
    puts "<value>$value</value>"
    puts "</attribute>"
}


Class OperatingSystem -superclass TopdlClass

OperatingSystem instproc init {args} {
    $self instvar name
    $self instvar version
    $self instvar distribution
    $self instvar distributionversion
    $self instvar attribute

    set attribute {}

    set nargs [llength $args ]
    if { $nargs > 0 } {
	set name [lindex $args 0]
    } else {
	set name ""
    }
    if { $nargs > 1 } {
	set version [lindex $args 1]
    } else {
	set version ""
    }
    if { $nargs > 2 } {
	set distribution [lindex $args 2]
    } else {
	set distribution ""
    }
    if { $nargs > 3 } {
	set distributionversion [lindex $args 3]
    } else {
	set distributionversion ""
    }
    foreach { n v } [lrange $args 4 end] {
	lappend attribute [ list $n $v ]
    }
}

OperatingSystem instproc topdl_out {} {
    $self instvar name
    $self instvar version
    $self instvar distribution
    $self instvar distributionversion
    $self instvar attribute

    if { [string length $name] > 0 } {
	puts "<name>$name</name>"
    }
    if { [string length $version] > 0 } {
	puts "<version>$version</version>"
    }
    if { [string length $distribution] > 0 } {
	puts "<distribution>$distribution</distribution>"
    }
    if { [string length $distributionversion] > 0 } {
	puts "<distributionversion>$distributionversion</distributionversion>"
    }
    foreach a $attribute {
	$self output_topdl_attr $a
    }
}

Class CPU -superclass TopdlClass

CPU instproc init { args } { 
    $self instvar type
    $self instvar attribute

    set attribute {}
    set nargs [llength $args ]
    if { $nargs > 0 } {
	set type [lindex $args 0]
    } else {
	set type ""
    }
    foreach { n v } [lrange $args 1 end] {
	lappend attribute [ list $n $v ]
    }
}

CPU instproc topdl_out {} { 
    $self instvar type
    $self instvar attribute

    if { [string length $type] > 0 } {
	puts "<type>$type</type>"
    }
    foreach a $attribute {
	$self output_topdl_attr $a
    }
}

Class Storage -superclass TopdlClass

Storage instproc init { args } { 
    $self instvar amount
    $self instvar persistence
    $self instvar attribute

    set attribute {}
    set nargs [llength $args ]
    if { $nargs > 0 } {
	set amount [lindex $args 0]
    } else {
	set amount "0"
    }
    if { $nargs > 0 } {
	set persistence [lindex $args 1]
    } else {
	set persistence "false"
    }
    foreach { n v } [lrange $args 2 end] {
	lappend attribute [ list $n $v ]
    }
}

Storage instproc topdl_out {} { 
    $self instvar amount
    $self instvar persistence
    $self instvar attribute

    puts "<amount>$amount</amount>"
    if { $persistence == "true" } {
	puts "<persistence>$persistence</persistence>"
    } else {
	puts "<persistence>false</persistence>"
    }
    foreach a $attribute {
	$self output_topdl_attr $a
    }
}
