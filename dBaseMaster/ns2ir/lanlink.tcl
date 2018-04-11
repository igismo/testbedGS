# -*- tcl -*-
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006, 2008 University of Utah and the Flux Group.
# All rights reserved.
#

######################################################################
# lanlink.tcl
#
# This defines the LanLink class and its two children Lan and Link.  
# Lan and Link make no changes to the parent and exist purely to
# distinguish between the two in type checking of arguments.  A LanLink
# contains a number of node:port pairs as well as the characteristics
# bandwidth, delay, and loss rate.
######################################################################

Class LanLink -superclass NSObject
Class Link -superclass LanLink
Class Lan -superclass LanLink
Class Queue -superclass NSObject
# This class is a hack.  It's sole purpose is to associate to a Link
# and a direction for accessing the Queue class.
Class SimplexLink -superclass NSObject
# Ditto, another hack class.
Class LLink -superclass NSObject

#NewLan to generate new delay parameters.
Class NewLan -superclass Lan

SimplexLink instproc init {link dir} {
    $self set mylink $link
    $self set mydir $dir
}
SimplexLink instproc queue {} {
    $self instvar mylink
    $self instvar mydir

    set myqueue [$mylink set ${mydir}queue]
    return $myqueue
}
LLink instproc init {lan node} {
    $self set mylan  $lan
    $self set mynode $node
}
LLink instproc queue {} {
    $self instvar mylan
    $self instvar mynode

    set port [$mylan get_port $mynode]
    
    return [$mylan set linkq([list $mynode $port])]
}
# Don't need any rename procs since these never use their own name and
# can not be generated during Link creation.

Queue instproc init {link type node} {
    $self set mylink $link
    $self set mynode $node
    
    # These control whether the link was created RED or GRED. It
    # filters through the DB.
    $self set gentle_ 0
    $self set red_ 0

    $self set traced 0
    $self set trace_type "header"
    $self set trace_expr {}
    $self set trace_snaplen 0
    $self set trace_endnode 0
    $self set trace_mysql 0

    #
    # These are NS variables for queues (with NS defaults).
    #
    $self set limit_ 100
    $self set maxthresh_ 15
    $self set thresh_ 5
    $self set q_weight_ 0.002
    $self set linterm_ 10
    $self set queue-in-bytes_ 0
    $self set bytes_ 0
    $self set mean_pktsize_ 500
    $self set wait_ 1
    $self set setbit_ 0
    $self set drop-tail_ 1

    if {$type != {}} {
	$self instvar red_
	$self instvar gentle_
	
	if {$type == "RED"} {
	    set red_ 1
	    $link mustdelay
	} elseif {$type == "GRED"} {
	    set red_ 1
	    set gentle_ 1
	    $link mustdelay
	} elseif {$type != "DropTail"} {
	    punsup "Link type $type, using DropTail!"
	}
    }
}

Queue instproc rename {old new} {
    $self instvar mylink

    $mylink rename_queue $old $new
}

Queue instproc rename_lanlink {old new} {
    $self instvar mylink

    set mylink $new
}

Queue instproc get_link {} {
    $self instvar mylink

    return $mylink
}

Queue instproc agent_name {} {
    $self instvar mylink
    $self instvar mynode

    return "$mylink-$mynode"
}

# Turn on tracing.
Queue instproc trace {{ttype "header"} {texpr ""}} {
    $self instvar traced
    $self instvar trace_expr
    $self instvar trace_type
    
    if {$texpr == ""} {
	set texpr {}
    }

    set traced 1
    set trace_type $ttype
    set trace_expr $texpr
}

#
# A queue is associated with a node on a link. Return that node.
# 
Queue instproc get_node {} {
    $self instvar mynode

    return $mynode
}

Link instproc init {s nodes bw d type} {
    $self next $s $nodes $bw $d $type

    set src [lindex $nodes 0]
    set dst [lindex $nodes 1]

    $self set src_node $src
    $self set dst_node $dst

    # The default netmask, which the user may change (at his own peril).
    $self set netmask "255.255.255.0"

    var_import GLOBALS::new_counter
    set q1 q[incr new_counter]
    
    Queue to$q1 $self $type $src
    Queue from$q1 $self $type $dst

    $self set toqueue to$q1
    $self set fromqueue from$q1
}

LanLink instproc init {s nodes bw d type} {
    var_import GLOBALS::new_counter

    # This is a list of {node port} pairs.
    $self set nodelist {}

    # The simulator
    $self set sim $s

    # By default, a local link
    $self set widearea 0

    # Default type is a plain "ethernet". User can change this.
    $self set protocol "ethernet"

    # Colocation is on by default, but this only applies to emulated links
    # between virtual nodes anyway.
    $self set trivial_ok 1

    # Allow user to control whether link gets a linkdelay, if link is shaped.
    # If not shaped, and user sets this variable, a link delay is inserted
    # anyway on the assumption that user wants later control over the link.
    # Both lans and links can get linkdelays.     
    $self set uselinkdelay 0

    # Allow user to control if link is emulated.
    $self set emulated 0

    # Allow user to turn off actual bw shaping on emulated links.
    $self set nobwshaping 0

    # mustdelay; force a delay (or linkdelay) to be inserted. assign_wrapper
    # is free to override this, but not sure why it want to! When used in
    # conjunction with nobwshaping, you get a delay node, but with no ipfw
    # limits on the bw part, and assign_wrapper ignores the bw when doing
    # assignment.
    $self set mustdelay 0

    # Allow user to specify encapsulation on emulated links.
    $self set encap "default"

    # XXX Allow user to set the accesspoint.
    $self set accesspoint {}

    # A simulated lanlink unless we find otherwise
    $self set simulated 1
    # Figure out if this is a lanlink that has at least
    # 1 non-simulated node in it. 
    foreach node $nodes {
	if { [$node set simulated] == 0 } {
	    $self set simulated 0
	    break
	}
    }

    # The default netmask, which the user may change (at his own peril).
    $self set netmask "255.255.255.0"

    # Make sure BW is reasonable. 
    # XXX: Should come from DB instead of hardwired max.
    # Measured in kbps
    set maxbw 10000000

    # XXX skip this check for a simulated lanlink even if it
    # causes nse to not keep up with real time. The actual max
    # for simulated links will be added later
    if { [$self set simulated] != 1 && $bw > $maxbw } {
	perror "Bandwidth requested ($bw) exceeds maximum of $maxbw kbps!"
	return
    }

    # Virt lan settings, for the entire lan
    $self instvar settings

    # And a two-dimenional arrary for per-member settings.
    # TCL does not actually have multi-dimensional arrays though, so its faked.
    $self instvar member_settings

    # Now we need to fill out the nodelist
    $self instvar nodelist

    # r* indicates the switch->node chars, others are node->switch
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar backfill
    $self instvar rbackfill
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar linkq
    $self instvar fixed_iface

    $self instvar iscloud
    $self set iscloud 0

    # Per-substrate topdl attributes
    $self instvar topdl_attrs
    set topdl_attrs {}

    # Per-interface topdl attributes
    $self instvar topdl_interface_attrs

    # topdl localnames
    $self instvar topdl_localname
    set topdl_localname {}

    # topdl status
    $self instvar topdl_status
    set topdl_status ""
    

    foreach node $nodes {
	set nodepair [list $node [$node add_lanlink $self]]
	set bandwidth($nodepair) $bw
	set rbandwidth($nodepair) $bw
	# Note - we don't set defaults for ebandwidth and rebandwidth - lack
	# of an entry for a nodepair indicates that they should be left NULL
	# in the output.
	set backfill($nodepair) 0
	set rbackfill($nodepair) 0
	set delay($nodepair) [expr $d / 2.0]
	set rdelay($nodepair) [expr $d / 2.0]
	set loss($nodepair) 0
	set rloss($nodepair) 0
	set cost($nodepair) 1
	set fixed_iface($nodepair) 0
	set topdl_interface_attrs($nodepair) {}
	lappend nodelist $nodepair

	set lq q[incr new_counter]
	Queue lq$lq $self $type $node
	set linkq($nodepair) lq$lq
    }
}

#
# Set the mustdelay flag.
#
LanLink instproc mustdelay {} {
    $self instvar mustdelay
    set mustdelay 1
}

#
# Set up tracing.
#
Lan instproc trace {{ttype "header"} {texpr ""}} {
    $self instvar nodelist
    $self instvar linkq

    foreach nodeport $nodelist {
	set linkqueue $linkq($nodeport)
	$linkqueue trace $ttype $texpr
    }
}

Link instproc trace {{ttype "header"} {texpr ""}} {
    $self instvar toqueue
    $self instvar fromqueue
    
    $toqueue trace $ttype $texpr
    $fromqueue trace $ttype $texpr
}

Lan instproc trace_snaplen {len} {
    $self instvar nodelist
    $self instvar linkq

    foreach nodeport $nodelist {
	set linkqueue $linkq($nodeport)
	$linkqueue set trace_snaplen $len
    }
}

Link instproc trace_snaplen {len} {
    $self instvar toqueue
    $self instvar fromqueue
    
    $toqueue set trace_snaplen $len
    $fromqueue set trace_snaplen $len
}

Lan instproc trace_mysql {onoff} {
    var_import ::GLOBALS::dpdb
    $self instvar nodelist
    $self instvar linkq

    foreach nodeport $nodelist {
	set linkqueue $linkq($nodeport)
	$linkqueue set trace_mysql $onoff
    }

    if {$onoff} {
	set dpdb 1
    }
}

Link instproc trace_mysql {onoff} {
    var_import ::GLOBALS::dpdb

    $self instvar toqueue
    $self instvar fromqueue
    
    $toqueue set trace_mysql $onoff
    $fromqueue set trace_mysql $onoff

    if {$onoff} {
	set dpdb 1
    }
}

Lan instproc trace_endnode {onoff} {
    $self instvar nodelist
    $self instvar linkq

    foreach nodeport $nodelist {
	set linkqueue $linkq($nodeport)
	$linkqueue set trace_endnode $onoff
    }
}

Link instproc trace_endnode {onoff} {
    $self instvar toqueue
    $self instvar fromqueue
    
    $toqueue set trace_endnode $onoff
    $fromqueue set trace_endnode $onoff
}


# get_port <node>
# This takes a node and returns the port that the node is connected
# to the LAN with.  If a node is in a LAN multiple times for some
# reason then this only returns the first.
LanLink instproc get_port {node} {
    $self instvar nodelist
    foreach pair $nodelist {
	set n [lindex $pair 0]
	set p [lindex $pair 1]
	if {$n == $node} {return $p}
    }
    return {}
}
# Add a topdl attribute to the list of topdl attributes for this substrate
LanLink instproc topdl_attr { name value } {
    $self instvar topdl_attrs
    lappend topdl_attrs [list $name $value ]
}

# Set a topdl interface attribute given a nodeport index and the name and value
# of the attribute
LanLink instproc topdl_interface_attr { nodeport name value } {
    $self instvar topdl_interface_attrs
    lappend topdl_interface_attrs($nodeport) [list $name $value ]
}

LanLink instproc add_topdl_localname { name } {
    $self instvar topdl_localname
    lappend topdl_localname $name
}

LanLink instproc set_topdl_status { status } {
    $self set topdl_status $status
}


#reserve_ip
#This reserve subnets if there are ips allocated staticlly to resolve ip conflict
LanLink instproc reserve_ip {} {
    $self instvar nodelist
    $self instvar sim
    $self instvar widearea
    $self instvar netmask
    set isremote 0
    set netmaskint [inet_atohl $netmask]
    set subnet {}
    foreach nodeport $nodelist {
        set node [lindex $nodeport 0]
        set port [lindex $nodeport 1]
        set ip [$node ip $port]
        set isremote [expr $isremote + [$node set isremote]]
        if {$ip != {}} {
            if !{$isremote} {
                 set ipint [inet_atohl $ip]
                 set subnet [inet_hltoa [expr $ipint & $netmaskint]]
                 set ips($ip) 1
                 $sim use_subnet $subnet $netmask
           }
        }
    }

}

# fill_ips
# This fills out the IP addresses (see README).  It determines a
# subnet, either from already assigned IPs or by asking the Simulator
# for one, and then fills out unassigned node:port's with free IP
# addresses.
LanLink instproc fill_ips {} {
    $self instvar nodelist
    $self instvar sim
    $self instvar widearea
    $self instvar netmask
    set isremote 0
    set netmaskint [inet_atohl $netmask]

    # Determine a subnet (if possible) and any used IP addresses in it.
    # ips is a set which contains all used IP addresses in this LanLink.
    set subnet {}
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set port [lindex $nodeport 1]
	set ip [$node ip $port]
	set isremote [expr $isremote + [$node set isremote]]
	if {$ip != {}} {
	    if {$isremote} {
		perror "Not allowed to specify IP subnet of a remote link!"
	    }
	    set ipint [inet_atohl $ip]
	    set subnet [inet_hltoa [expr $ipint & $netmaskint]]
	    set ips($ip) 1
	    $sim use_subnet $subnet $netmask
	}
    }
    if {$isremote && [$self info class] != "Link"} {
        puts stderr "Warning: Remote nodes used in LAN $self - no IPs assigned"
	#perror "Not allowed to use a remote node in lan $self!"
	return
    }
    if {$isremote} {
	# A boolean ... not a count.
	set widearea 1
    }

    # See parse-ns if you change this! 
    if {$isremote && ($netmask != "255.255.255.248")} {
	puts stderr "Ignoring netmask for remote link; forcing 255.255.255.248"
	set netmask "255.255.255.248"
	set netmaskint [inet_atohl $netmask]
    }

    # If we couldn't find a subnet we ask the Simulator for one.
    if {$subnet == {}} {
	if {$isremote} {
	    set subnet [$sim get_subnet_remote]
	} else {
	    set subnet [$sim get_subnet $netmask]
	}
    }

    # Now we assign IP addresses to any node:port's without them.
    set ip_counter 2
    set subnetint [inet_atohl $subnet]
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set port [lindex $nodeport 1]
	if {[$node ip $port] == {}} {
	    set ip {}
	    # the "& 0xffffffff" is necessary for 64 bit systems
	    set max [expr (~ $netmaskint) & 0xffffffff]
	    for {set i $ip_counter} {$i < $max} {incr i} {
		set nextip [inet_hltoa [expr $subnetint | $i]]
		
		if {! [info exists ips($nextip)]} {
		    set ip $nextip
		    set ips($ip) 1
		    set ip_counter [expr $i + 1]
		    break
		}
	    }
	    if {$ip == {}} {
		perror "Ran out of IP addresses in subnet $subnet."
		set ip "255.255.255.255"
	    }
	    $node ip $port $ip
	}
    }
}

#
# Return the subnet of a lan. Actually, just return one of the IPs.
#
LanLink instproc get_subnet {} {
    $self instvar nodelist

    set nodeport [lindex $nodelist 0]
    set node [lindex $nodeport 0]
    set port [lindex $nodeport 1]

    return [$node ip $port]
}

#
# XXX - Set the accesspoint for the lan to node. This is temporary.
#
LanLink instproc set_accesspoint {node} {
    $self instvar accesspoint
    $self instvar nodelist

    foreach pair $nodelist {
	set n [lindex $pair 0]
	set p [lindex $pair 1]
	if {$n == $node} {
	    set accesspoint $node
	    return {}
	}
    }
    perror "set_accesspoint: No such node $node in lan $self."
}

#
# Set a setting for the entire lan.
#
LanLink instproc set_setting {capkey capval} {
    $self instvar settings

    set settings($capkey) $capval
}

#
# Set a setting for just one member of a lan
#
LanLink instproc set_member_setting {node capkey capval} {
    $self instvar member_settings
    $self instvar nodelist

    foreach pair $nodelist {
	set n [lindex $pair 0]
	set p [lindex $pair 1]
	if {$n == $node} {
	    set member_settings($node,$capkey) $capval
	    return {}
	}
    }
    perror "set_member_setting: No such node $node in lan $self."
}

#
# Return the subnet of a lan. Actually, just return one of the IPs.
#
LanLink instproc get_netmask {} {
    $self instvar netmask

    return $netmask
}

#
# Set the routing cost for all interfaces on this LAN
#
LanLink instproc cost {c} {
    $self instvar nodelist
    $self instvar cost

    foreach nodeport $nodelist {
	set cost($nodeport) $c
    }
}

Link instproc rename {old new} {
    $self next $old $new

    $self instvar toqueue
    $self instvar fromqueue
    $toqueue rename_lanlink $old $new
    $fromqueue rename_lanlink $old $new
}

Link instproc rename_queue {old new} {
    $self next $old $new

    $self instvar toqueue
    $self instvar fromqueue

    if {$old == $toqueue} {
	set toqueue $new
    } elseif {$old == $fromqueue} {
	set fromqueue $new
    }
}

# The following methods are for renaming objects (see README).
LanLink instproc rename {old new} {
    $self instvar nodelist
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	$node rename_lanlink $old $new
    }
    
    [$self set sim] rename_lanlink $old $new
}
LanLink instproc rename_node {old new} {
    $self instvar nodelist
    $self instvar bandwidth
    $self instvar delay
    $self instvar loss
    $self instvar rbandwidth
    $self instvar rdelay
    $self instvar rloss
    $self instvar linkq
    $self instvar accesspoint

    # XXX Temporary
    if {$accesspoint == $old} {
	set accesspoint $new
    }
    
    set newnodelist {}
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set port [lindex $nodeport 1]
	set newnodeport [list $new $port]
	if {$node == $old} {
	    lappend newnodelist $newnodeport
	} else {
	    lappend newnodelist $nodeport
	}
	set bandwidth($newnodeport) $bandwidth($nodeport)
	set delay($newnodeport) $delay($nodeport)
	set loss($newnodeport) $loss($nodeport)
	set rbandwidth($newnodeport) $rbandwidth($nodeport)
	set rdelay($newnodeport) $rdelay($nodeport)
	set rloss($newnodeport) $rloss($nodeport)
	set linkq($newnodepair) linkq($nodeport)
	
	unset bandwidth($nodeport)
	unset delay($nodeport)
	unset loss($nodeport)
	unset rbandwidth($nodeport)
	unset rdelay($nodeport)
	unset rloss($nodeport)
	unset linkq($nodeport)
    }
    set nodelist $newnodelist
}

Link instproc rename_queue {old new} {
    $self instvar nodelist
    $self instvar linkq

    foreach nodeport $nodelist {
	set foo linkq($nodeport)
	
	if {$foo == $old} {
	    set linkq($nodeport) $new
	}
    }
}

# Put out the cannonical tcl for this link.  This is a translation of the
# internal variables set into the tcl that would set them. Gws is the name of a
# list of the gateways allocated by this testbed; the list is in the callers
# stack level.  This is a hacky call-by-reference Currently this is only an
# output variable, but eventually it will be used to multiplex connections to
# the same testbed.  The contents are the destination testbed and the
# destination hostname in that testbed that will become an FQDN.  
Link instproc tcl_out {tb gws} {
    $self instvar toqueue
    $self instvar fromqueue
    $self instvar nodelist
    $self instvar src_node
    $self instvar dst_node
    $self instvar trivial_ok
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::testbeds
    var_import ::GLOBALS::hostnames
    var_import ::GLOBALS::connections
    var_import ::GLOBALS::muxmax
    var_import ::GLOBALS::include_fedkit
    var_import ::GLOBALS::include_gatewaykit
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar widearea
    $self instvar uselinkdelay
    $self instvar emulated
    $self instvar nobwshaping
    $self instvar encap
    $self instvar sim
    $self instvar netmask
    $self instvar protocol
    $self instvar mustdelay

    if {$protocol != "ethernet"} {
	perror "Link must be an ethernet only, not a $protocol"
	return
    }

    # If $gws isn't null, it's the name of a list variable in the calling
    # context and this connects gwsp (gateways pointer) to it.  If there's no
    # such variable in the calling context, create a dummy list.
    if { $gws != "" } { 
	upvar 1 $gws gwsp 
    } else {
	set gwsp {}
    }

    set myname [name2var $self]
    set myref [name2ref $self]
    set n0 [lindex [lindex $nodelist 0] 0]
    set n1 [lindex [lindex $nodelist 1] 0]
    set t0 [$n0 set testbed ]
    set t1 [$n1 set testbed ]
    set bw $bandwidth([lindex $nodelist 0])
    set del [ expr $delay([lindex $nodelist 0]) * 2]
    set rbw $rbandwidth([lindex $nodelist 0])
    set rdel [ expr $rdelay([lindex $nodelist 0]) * 2]
    set gwtars ""
    
    if { $GLOBALS::include_fedkit } {
	set gwtars "FEDKIT" 
    }

    if { $GLOBALS::include_gatewaykit } {
	set gwtars "$gwtars GATEWAYKIT" 
    }
    
    if { $t0 != $tb && $t1 != $tb } { return; }

    if { $t0 != $t1 } {

	# The number of connections between testbeds is an ordered tuple of
	# from:to testbed name.  Between each pair of testbeds the same
	# connections are created in the same order, the connection number
	# completely names them, though we generate longer gateway node names
	# below.  This establishes the index into the global connection count
	# array for this testbed pair.
	if { [ string compare $t0 $tb] == 0 } { 
	    set cidx "$t1\:$tb"
	} else {
	    set cidx "$t0\:$tb"
	}

	# MAke sure the connection counter for this pair exists.
	if { ! [info exists ::GLOBALS::connections($cidx)] } {
	    set GLOBALS::connections($cidx) 0
	}
        # This is a cross testbed link.  Create a local gateway endpoint.
    	if { $t0 == $tb } {
	    set i [ expr $GLOBALS::connections($cidx) / $GLOBALS::muxmax ]
	    set local_src $n0
	    set local_dst "${t1}tunnel$i";
	    set use_loss 0


	    # Every muxmax connections needs a new gateway.  Create one if
	    # needed, otherwise connect to the existing one.
	    if { [expr $GLOBALS::connections($cidx) % $GLOBALS::muxmax] == 0} {
		puts "# federation gateway"
		puts "set $local_dst \[\$ns node ]"
		puts "tb-set-hardware [name2ref $local_dst] GWTYPE"
		puts "tb-set-node-os [name2ref $local_dst] GWIMAGE"
		puts "# GWCMD [name2ref $local_dst] GWCMDPARAMS"
		puts "tb-set-node-startcmd [name2ref $local_dst] \"GWSTART\""
		if { [string length $gwtars ] > 0 }  {
		    puts "tb-set-node-tarfiles [name2ref $local_dst] $gwtars"; 
		}
		lappend gwsp [ list $t1 "${t1}tunnel$i" "${t0}tunnel$i" \
			   "experimental" ]
	    }
	    # Count the connection
	    incr GLOBALS::connections($cidx) 
	} else {
	    # Mirror of above.
	    set i [ expr $GLOBALS::connections($cidx) / $GLOBALS::muxmax ]
	    set local_src "${t0}tunnel$i";
	    set local_dst $n1
	    set use_loss 1

	    if { [expr $GLOBALS::connections($cidx) % $GLOBALS::muxmax] == 0} {
		puts "# federation gateway"
		puts "set $local_src \[\$ns node ]"
		puts "tb-set-hardware [name2ref $local_src] GWTYPE"
		puts "tb-set-node-os [name2ref $local_src] GWIMAGE"
		puts "# GWCMD [name2ref $local_src] GWCMDPARAMS"
		puts "tb-set-node-startcmd [name2ref $local_src] \"GWSTART\""
		if { [string length $gwtars ] > 0 }  {
		    puts "tb-set-node-tarfiles [name2ref $local_src] $gwtars"; 
		}
		lappend gwsp [ list $t0 "${t0}tunnel$i" "${t1}tunnel$i" \
			   "experimental" ]
	    }
	    incr GLOBALS::connections($cidx) 
	}
    } else {
	set local_src $n0
	set local_dst $n1
        set use_loss 1
    }
    set from_node [name2ref $local_src]
    set to_node [name2ref $local_dst]

    # If a link is split when federating, bandwidth and delay can be directly
    # munged (though delay is going to be a mess) but loss probability cannot
    # be split accross the two links without changing the observed
    # characteristics.  Because the directionality of the links is fixed, we
    # can use that to assign the loss probabilities to only one of the two
    # links.
    if { $use_loss == 1 } { 
        set los [ expr $loss([lindex $nodelist 0]) * 2]
        set rlos [ expr $rloss([lindex $nodelist 0]) * 2]
    } else {
        set los 0
        set rlos 0
    }
		
    puts "# Link establishment and parameter setting"
    puts "set $myname \[\$ns duplex-link $from_node $to_node ${bw}kb ${del}ms DropTail]"

    # Simplex no longer lets one set a 0 loss on all testbeds, so the
    # asymmetric case is not supported.
    #if { $rdel != $del || $rbw != $bw || $rlos > 0 || $los > 0 } {
	#puts "tb-set-link-simplex-params $myref $from_node ${del}ms ${bw}kb  $los"
	#puts "tb-set-link-simplex-params $myref $to_node ${rdel}ms ${rbw}kb  $rlos"
    #}
    puts ""
    # puts "# Queue parameters"
	    
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]

	# We're only worried about the params that we can set from
	# http://www.isi.deterlab.net/tutorial/docwrapper.php?docname=advanced.html
	if {$node == $src_node} {
	    set from_node [ name2ref $local_src ]
	    set to_node [ name2ref $local_dst ]
	    set linkqueue $toqueue
	} else {
	    set from_node [ name2ref $local_dst ]
	    set to_node [ name2ref $local_src ]
	    set linkqueue $fromqueue
	}
	set limit_ [$linkqueue set limit_]
	set maxthresh_ [$linkqueue set maxthresh_]
	set thresh_ [$linkqueue set thresh_]
	set q_weight_ [$linkqueue set q_weight_]
	set linterm_ [$linkqueue set linterm_]
	set queue-in-bytes_ [$linkqueue set queue-in-bytes_]
	if {${queue-in-bytes_} == "true"} {
	    set queue-in-bytes_ 1
	} elseif {${queue-in-bytes_} == "false"} {
	    set queue-in-bytes_ 0
	}
	set bytes_ [$linkqueue set bytes_]
	if {$bytes_ == "true"} {
	    set bytes_ 1
	} elseif {$bytes_ == "false"} {
	    set bytes_ 0
	}
	set red_ [$linkqueue set red_]
	if {$red_ == "true"} {
	    set red_ 1
	} elseif {$red_ == "false"} {
	    set red_ 0
	}
	set gentle_ [$linkqueue set gentle_]
	if {$gentle_ == "true"} {
	    set gentle_ 1
	} elseif {$gentle_ == "false"} {
	    set gentle_ 0
	}

	set port [lindex $nodeport 1]
	set ip [$node ip $port]

	# Let's try without these for now.  They seem to add extra delay nodes
	# and some confusion.
	# puts "\[\[\$ns link $from_node $to_node] queue] set limit_ $limit_";
	# puts "\[\[\$ns link $from_node $to_node] queue] set thresh_ $thresh_";
	# puts "\[\[\$ns link $from_node $to_node] queue] set maxthresh_ $maxthresh_";
	# puts "\[\[\$ns link $from_node $to_node] queue] set q_weight_ $q_weight_";
	# puts "\[\[\$ns link $from_node $to_node] queue] set linterm_ $linterm_";
	# puts "\[\[\$ns link $from_node $to_node] queue] set queue-in-bytes_ ${queue-in-bytes_}";
	# puts "\[\[\$ns link $from_node $to_node] queue] set red_ $red_";
	# puts "\[\[\$ns link $from_node $to_node] queue] set gentle_ $gentle_";
	puts "tb-set-ip-link $from_node $myref $ip";

	# Hostname lists.  Note that we use the original names from the
	# combined experiment description, not the anonymized names.
	set ::GLOBALS::hostnames($node) $ip
	set ::GLOBALS::hostnames("$node-$port") $ip
	set ::GLOBALS::hostnames("$node-$self") $ip

    }
}
LanLink instproc escape_xml {value } {
    # Basic xml escapes
    regsub -all "\&" $value "\\&amp;" value
    regsub -all "\<" $value "\\&lt;" value
    regsub -all "\>" $value "\\&gt;" value
    return $value
}

LanLink instproc output_topdl_attr { attr } {
    set name [$self escape_xml [lindex $attr 0]]
    set value [$self escape_xml [lindex $attr 1]]

    puts "<attribute>"
    puts "<attribute>$name</attribute>"
    puts "<value>$value</value>"
    puts "</attribute>"
}
# Put out the cannonical tcl for this link.  This is a translation of the
# internal variables set into the tcl that would set them. Gws is the name of a
# list of the gateways allocated by this testbed; the list is in the callers
# stack level.  This is a hacky call-by-reference Currently this is only an
# output variable, but eventually it will be used to multiplex connections to
# the same testbed.  The contents are the destination testbed and the
# destination hostname in that testbed that will become an FQDN.  
Link instproc topdl_out { } {
    $self instvar nodelist
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar topdl_attrs
    $self instvar topdl_interface_attrs
    $self instvar topdl_localname
    $self instvar topdl_status

    set n0 [lindex [lindex $nodelist 0] 0]
    set n1 [lindex [lindex $nodelist 1] 0]
    set t0 [$n0 set testbed ]
    set t1 [$n1 set testbed ]
    set bw $bandwidth([lindex $nodelist 0])
    set del [ expr $delay([lindex $nodelist 0])]
    set rbw $rbandwidth([lindex $nodelist 0])
    set rdel [ expr $rdelay([lindex $nodelist 0])]
    set los [ expr $loss([lindex $nodelist 0]) * 2]
    set rlos [ expr $rloss([lindex $nodelist 0]) * 2]
    set attr0 $topdl_interface_attrs([lindex $nodelist 0])
    set attr1 $topdl_interface_attrs([lindex $nodelist 1])

    set sub_bw [ expr $bw > $rbw ? $bw : $rbw ]
    set sub_del [ expr $del > $rdel ? $del : $rdel ]
    set sub_loss [ expr $los > $rlos ? $los : $rlos ]
    
    puts "<substrates>"
    puts "<name>$self</name>"
    puts "<capacity><rate>$sub_bw</rate><kind>max</kind></capacity>"
    if { $sub_del > 0 } {
	puts "<latency><time>$sub_del</time><kind>max</kind></latency>"
    }
    if { $sub_loss > 0 } {
	$self topdl_attr "loss" $sub_loss
    }
    foreach a $topdl_attrs {
	$self output_topdl_attr $a
    }
    foreach localname $topdl_localname {
	puts "<localname>$localname</localname>"
    }

    if { [string length $topdl_status] > 0 } {
	puts "<status>$topdl_status</status>"
    }

    puts "</substrates>"

    $n0 add_topdl_interface [list $self $bw $del $los [$n0 find_ip $self] $attr0 ]
    $n1 add_topdl_interface [list $self $rbw $rdel $rlos [$n1 find_ip $self] $attr1 ]
}

LanLink instproc set_fixed_iface {node iface} {
    $self instvar nodelist
    $self instvar fixed_iface

    # find this node
    set found 0
    foreach nodeport $nodelist {
	if {$node == [lindex $nodeport 0]} {
	    set fixed_iface($nodeport) $iface
	    set found 1
	    break
	}
    }

    if {!$found} {
	perror "\[set_fixed_iface] $node is not the specified link/lan!"
    }
}

# Check the IP address against its mask and ensure that the host
# portion of the IP address is not all '0's (reserved) or all '1's
# (broadcast).
LanLink instproc check-ip-mask {ip mask} {
    set ipint [inet_atohl $ip]
    set maskint [inet_atohl $mask]
    set maskinverse [expr (~ $maskint) & 0xffffffff]
    set remainder [expr ($ipint & $maskinverse)]
    if {$remainder == 0 || $remainder == $maskinverse} {
	perror "\[check-ip-mask] IP address $ip with netmask $mask has either all '0's (reserved) or all '1's (broadcast) in the host portion of the address."
    }
}


Link instproc updatedb {DB} {
    $self instvar toqueue
    $self instvar fromqueue
    $self instvar nodelist
    $self instvar src_node
    $self instvar trivial_ok
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar backfill
    $self instvar rbackfill
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar widearea
    $self instvar uselinkdelay
    $self instvar emulated
    $self instvar nobwshaping
    $self instvar encap
    $self instvar sim
    $self instvar netmask
    $self instvar protocol
    $self instvar mustdelay
    $self instvar fixed_iface

    $sim spitxml_data "virt_lan_lans" [list "vname"] [list $self]

    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	if {$node == $src_node} {
	    set linkqueue $toqueue
	} else {
	    set linkqueue $fromqueue
	}
	set limit_ [$linkqueue set limit_]
	set maxthresh_ [$linkqueue set maxthresh_]
	set thresh_ [$linkqueue set thresh_]
	set q_weight_ [$linkqueue set q_weight_]
	set linterm_ [$linkqueue set linterm_]
	set queue-in-bytes_ [$linkqueue set queue-in-bytes_]
	if {${queue-in-bytes_} == "true"} {
	    set queue-in-bytes_ 1
	} elseif {${queue-in-bytes_} == "false"} {
	    set queue-in-bytes_ 0
	}
	set bytes_ [$linkqueue set bytes_]
	if {$bytes_ == "true"} {
	    set bytes_ 1
	} elseif {$bytes_ == "false"} {
	    set bytes_ 0
	}
	set mean_pktsize_ [$linkqueue set mean_pktsize_]
	set red_ [$linkqueue set red_]
	if {$red_ == "true"} {
	    set red_ 1
	} elseif {$red_ == "false"} {
	    set red_ 0
	}
	set gentle_ [$linkqueue set gentle_]
	if {$gentle_ == "true"} {
	    set gentle_ 1
	} elseif {$gentle_ == "false"} {
	    set gentle_ 0
	}
	set wait_ [$linkqueue set wait_]
	set setbit_ [$linkqueue set setbit_]
	set droptail_ [$linkqueue set drop-tail_]

	#
	# Note; we are going to deprecate virt_lans:member and virt_nodes:ips
	# Instead, store vnode,vport,ip in the virt_lans table. To get list
	# of IPs for a node, join virt_nodes with virt_lans. port number is
	# no longer required, but we maintain it to provide a unique key that
	# does not depend on IP address.
	#
	set port [lindex $nodeport 1]
	set ip [$node ip $port]

	$self check-ip-mask $ip $netmask

	set nodeportraw [join $nodeport ":"]

	set fields [list "vname" "member" "mask" "delay" "rdelay" "bandwidth" "rbandwidth" "backfill" "rbackfill" "lossrate" "rlossrate" "cost" "widearea" "emulated" "uselinkdelay" "nobwshaping" "encap_style" "q_limit" "q_maxthresh" "q_minthresh" "q_weight" "q_linterm" "q_qinbytes" "q_bytes" "q_meanpsize" "q_wait" "q_setbit" "q_droptail" "q_red" "q_gentle" "trivial_ok" "protocol" "vnode" "vport" "ip" "mustdelay"]

	# Treat estimated bandwidths differently - leave them out of the lists
	# unless the user gave a value - this way, they get the defaults if not
	# specified
	if { [info exists ebandwidth($nodeport)] } {
	    lappend fields "est_bandwidth"
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend fields "rest_bandwidth"
	}
	
	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend fields "traced"
	    lappend fields "trace_type"
 	    lappend fields "trace_expr"
 	    lappend fields "trace_snaplen"
 	    lappend fields "trace_endnode"
 	    lappend fields "trace_db"
	}

	# fixing ifaces
	if {$fixed_iface($nodeport) != 0} {
	    lappend fields "fixed_iface"
	}

	set values [list $self $nodeportraw $netmask $delay($nodeport) $rdelay($nodeport) $bandwidth($nodeport) $rbandwidth($nodeport) $backfill($nodeport) $rbackfill($nodeport)  $loss($nodeport) $rloss($nodeport) $cost($nodeport) $widearea $emulated $uselinkdelay $nobwshaping $encap $limit_  $maxthresh_ $thresh_ $q_weight_ $linterm_ ${queue-in-bytes_}  $bytes_ $mean_pktsize_ $wait_ $setbit_ $droptail_ $red_ $gentle_ $trivial_ok $protocol $node $port $ip $mustdelay]

	if { [info exists ebandwidth($nodeport)] } {
	    lappend values $ebandwidth($nodeport)
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend values $rebandwidth($nodeport)
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend values [$linkqueue set traced]
	    lappend values [$linkqueue set trace_type]
	    lappend values [$linkqueue set trace_expr]
	    lappend values [$linkqueue set trace_snaplen]
	    lappend values [$linkqueue set trace_endnode]
	    lappend values [$linkqueue set trace_mysql]
	}

	# fixing ifaces
	if {$fixed_iface($nodeport) != 0} {
	    lappend values $fixed_iface($nodeport)
	}

	$sim spitxml_data "virt_lans" $fields $values
    }
}

Lan instproc tcl_out {tb gws} {
    $self instvar nodelist
    $self instvar linkq
    $self instvar trivial_ok
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges
    var_import ::GLOBALS::include_fedkit
    var_import ::GLOBALS::include_gatewaykit
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar widearea
    $self instvar uselinkdelay
    $self instvar emulated
    $self instvar nobwshaping
    $self instvar encap
    $self instvar sim
    $self instvar netmask
    $self instvar protocol
    $self instvar accesspoint
    $self instvar settings
    $self instvar member_settings
    $self instvar mustdelay

    if {$modelnet_cores > 0 || $modelnet_edges > 0} {
	perror "Lans are not allowed when using modelnet; just duplex links."
	return
    }

    set gwtars ""

    if { $GLOBALS::include_fedkit } {
	set gwtars "FEDKIT" 
    }

    if { $GLOBALS::include_gatewaykit } {
	set gwtars "$gwtars GATEWAYKIT" 
    }
    

    # $sim spitxml_data "virt_lan_lans" [list "vname"] [list $self]

    #
    # Upload lan settings and them per-member settings
    #
    foreach setting [array names settings] {
	set fields [list "vname" "capkey" "capval"]
	set values [list $self $setting $settings($setting)]
	
	$sim spitxml_data "virt_lan_settings" $fields $values
    }


    # If $gws isn't null, it's the name of a list variable in the calling
    # context and this connects gwsp (gateways pointer) to it.  If there's no
    # such variable in the calling context, create a dummy list.
    if { $gws != "" } { 
	upvar 1 $gws gwsp 
    } else {
	set gwsp {}
    }

    # List of nodes in this LAN on this tb, including gateways
    set nodel ""
    # The testbeds we've seen on this LAN.  This will become an array of lists
    # where each 2-item list is the name of the gateway for the testbed and its
    # IP address on the lan.
    array set tbs {}

    # Add nodes in this testbed to nodel and note the foreign testbeds we see
    # in tbs
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set tbs([$node set testbed]) [ $node ip [lindex $nodeport 1] ] 
	if { [ $node set testbed ] == $tb } { 
	    append nodel "[ name2ref $node ] "
        }
    }

    #  If there are any nodes in this testbed, walk through and create any
    #  gateways we need (this may be multiplexing onto existing gateways).  The
    #  gateway creation code is essentially the same as in Link tcl_out.
    if { [ info exists tbs($tb) ] } {
	# this testbed has a node in the lan
	foreach t [ array names tbs ] {
	    if { $t != $tb } {

		# Index into connnections is the testbed names, directionally,
		# separated by a colon.  This is always t->tb
		set cidx "$t\:$tb"

		# Create an entry in the global connections array if there
		# isn't one.
		if { ! [info exists ::GLOBALS::connections($cidx)] } {
		    set GLOBALS::connections($cidx) 0
		}

		# Mux code.
		set i [ expr $GLOBALS::connections($cidx) / $GLOBALS::muxmax ]
		set local_gw "${t}tunnel$i";
		set remote_gw "${tb}tunnel$i";

		# New gateway
		if { [expr $GLOBALS::connections($cidx) \
			% $GLOBALS::muxmax] == 0} {
		    puts "# LAN federation gateway"
		    puts "set $local_gw \[\$ns node ]"
		    puts "tb-set-hardware [name2ref $local_gw] GWTYPE"
		    puts "tb-set-node-os [name2ref $local_gw] GWIMAGE"
		    puts "# GWCMD [name2ref $local_gw] GWCMDPARAMS"
		    puts "tb-set-node-startcmd [name2ref $local_gw] \"GWSTART\""
		    if { [string length $gwtars ] > 0 }  {
			puts "tb-set-node-tarfiles [name2ref $local_gw] $gwtars"; 
		    }
		    lappend gwsp [ list $t $local_gw $remote_gw \
			       "experimental" ]
		}
		# Add the gateway to the lan and add the connection to the
		# global.
		append nodel "[ name2ref $local_gw] "
		# 
		set tbs($t) [ list $tbs($t) $local_gw ]
		incr GLOBALS::connections($cidx) 
	    }
	}
    } else {
	return;
    }

    set lan_name [name2var $self]
    set lan_ref [name2ref $self]
    puts "# Create LAN"
    puts "set $lan_name \[\$ns make-lan \"$nodel\" 100000kb 0.0ms ]";
    puts ""
    # Set the gateway IP addresses
    foreach t [ array names tbs ] {
	if { $t != $tb } {
	    # Parse out the entry to set the gateway IP address on the LAN
	    set gip [lindex $tbs($t) 0]
	    set gname [lindex $tbs($t) 1]

	    puts "tb-set-ip-lan [name2ref $gname] $lan_ref $gip"
	}
    }
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set isvirt [$node set isvirt]
	set linkqueue $linkq($nodeport)
	set limit_ [$linkqueue set limit_]
	set maxthresh_ [$linkqueue set maxthresh_]
	set thresh_ [$linkqueue set thresh_]
	set q_weight_ [$linkqueue set q_weight_]
	set linterm_ [$linkqueue set linterm_]
	set queue-in-bytes_ [$linkqueue set queue-in-bytes_]

	if { [$node set testbed ] != $tb} { continue; }
	if {${queue-in-bytes_} == "true"} {
	    set queue-in-bytes_ 1
	} elseif {${queue-in-bytes_} == "false"} {
	    set queue-in-bytes_ 0
	}
	set bytes_ [$linkqueue set bytes_]
	if {$bytes_ == "true"} {
	    set bytes_ 1
	} elseif {$bytes_ == "false"} {
	    set bytes_ 0
	}
	set red_ [$linkqueue set red_]
	if {$red_ == "true"} {
	    set red_ 1
	} elseif {$red_ == "false"} {
	    set red_ 0
	}
	set gentle_ [$linkqueue set gentle_]
	if {$gentle_ == "true"} {
	    set gentle_ 1
	} elseif {$gentle_ == "false"} {
	    set gentle_ 0
	}
	
	#
	# Note; we are going to deprecate virt_lans:member and virt_nodes:ips
	# Instead, store vnode,vport,ip in the virt_lans table. To get list
	# of IPs for a node, join virt_nodes with virt_lans. port number is
	# no longer required, but we maintain it to provide a unique key that
	# does not depend on IP address.
	#
	set port [lindex $nodeport 1]
	set ip [$node ip $port]

	set nodeportraw [join $nodeport ":"]

	set is_accesspoint 0
	if {$node == $accesspoint} {
	    set is_accesspoint 1
	}

	set fields [list "vname" "member" "mask" "delay" "rdelay" "bandwidth" "rbandwidth" "lossrate" "rlossrate" "cost" "widearea" "emulated" "uselinkdelay" "nobwshaping" "encap_style" "q_limit" "q_maxthresh" "q_minthresh" "q_weight" "q_linterm" "q_qinbytes" "q_bytes" "q_meanpsize" "q_wait" "q_setbit" "q_droptail" "q_red" "q_gentle" "trivial_ok" "protocol" "is_accesspoint" "vnode" "vport" "ip" "mustdelay"]

	# Treat estimated bandwidths differently - leave them out of the lists
	# unless the user gave a value - this way, they get the defaults if not
	# specified
	if { [info exists ebandwidth($nodeport)] } {
	    lappend fields "est_bandwidth"
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend fields "rest_bandwidth"
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend fields "traced"
	    lappend fields "trace_type"
 	    lappend fields "trace_expr"
 	    lappend fields "trace_snaplen"
 	    lappend fields "trace_endnode"
 	    lappend fields "trace_db"
	}
	
	# set values [list $self $nodeportraw $netmask $delay($nodeport) $rdelay($nodeport) $bandwidth($nodeport) $rbandwidth($nodeport) $loss($nodeport) $rloss($nodeport) $cost($nodeport) $widearea $emulated $uselinkdelay $nobwshaping $encap $limit_  $maxthresh_ $thresh_ $q_weight_ $linterm_ ${queue-in-bytes_}  $bytes_ $mean_pktsize_ $wait_ $setbit_ $droptail_ $red_ $gentle_ $trivial_ok $protocol $is_accesspoint $node $port $ip $mustdelay]

	if { [info exists ebandwidth($nodeport)] } {
	    lappend values $ebandwidth($nodeport)
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend values $rebandwidth($nodeport)
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend values [$linkqueue set traced]
	    lappend values [$linkqueue set trace_type]
	    lappend values [$linkqueue set trace_expr]
	    lappend values [$linkqueue set trace_snaplen]
	    lappend values [$linkqueue set trace_endnode]
	    lappend values [$linkqueue set trace_mysql]
	}

	# $sim spitxml_data "virt_lans" $fields $values

	foreach setting_key [array names member_settings] {
	    set foo      [split $setting_key ","]
	    set thisnode [lindex $foo 0]
	    set capkey   [lindex $foo 1]

	    if {$thisnode == $node} {
		set fields [list "vname" "member" "capkey" "capval"]
		set values [list $self $nodeportraw $capkey \
		                 $member_settings($setting_key)]
	
		$sim spitxml_data "virt_lan_member_settings" $fields $values
	    }
	}
	set name [name2var $node]
	set ref [name2ref $node]
	set bw $bandwidth($nodeport)
	set rbw $rbandwidth($nodeport)
	set del $delay($nodeport)
	set rdel $rdelay($nodeport)

	puts "# Set LAN/Node parameters"
	puts "tb-set-ip-lan $ref $lan_ref $ip";
	# XXX: tb-set-lan-simplex params stopped taking 0 loss probability on
	# Emulab as a valid parameter so this line has become invalid.  As a
	# workarounf, we set just the single direction parameters.  That's not
	# ideal, but really all we can do.
	# puts "tb-set-lan-simplex-params $lan_ref $ref ${del}ms ${bw}kb $loss($nodeport) ${rdel}ms ${rbw}kb $rloss($nodeport) ";
	if { $del > 0.0 } {
	    puts "tb-set-node-lan-delay $ref $lan_ref ${del}ms"
	}
	puts "tb-set-node-lan-bandwidth $ref $lan_ref ${bw}kb"
	if { $loss($nodeport) > 0.0 } {
	    puts "tb-set-node-lan-loss $ref $lan_ref $loss($nodeport)"
	}
	puts ""
	# Avoid these for now
	# puts "# Set Queue parameters (LANs have them)"
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set limit_ $limit_";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set thresh_ $thresh_";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set maxthresh_ $maxthresh_";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set q_weight_ $q_weight_";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set linterm_ $linterm_";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set queue-in-bytes_ ${queue-in-bytes_}";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set red_ $red_";
	# puts "\[\[\$ns lanlink $lan_ref $ref] queue] set gentle_ $gentle_";
	# Hostname lists.  Note that we use the original names from the
	# combined experiment description, not the anonymized names.
	set ::GLOBALS::hostnames($node) $ip
	set ::GLOBALS::hostnames("$node-$port") $ip
	set ::GLOBALS::hostnames("$node-$self") $ip

	puts ""
    }
}

Lan instproc topdl_out { } {
    $self instvar nodelist
    $self instvar bandwidth
    $self instvar delay
    $self instvar loss
    $self instvar topdl_localname
    $self instvar topdl_status
    $self instvar topdl_attrs
    $self instvar topdl_interface_attrs

    # Add nodes in this testbed to nodel and note the foreign testbeds we see
    # in tbs
    set maxbw 0
    set maxdel 0
    set maxloss 0
    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set bw $bandwidth($nodeport)
	set del $delay($nodeport)
	set los $loss($nodeport)
	set attrs $topdl_interface_attrs($nodeport)

	$node add_topdl_interface [list $self $bw $del $los [$node find_ip $self] $attrs]

	if { $bw > $maxbw } { set maxbw $bw }
	if { $del > $maxdel } { set maxdel $del }
	if { $los > $maxloss } { set maxloss $los }
    }
    puts "<substrates>"
    puts "<name>$self</name>"
    puts "<capacity><rate>$maxbw</rate><kind>max</kind></capacity>"
    if { $maxdel > 0 } {
	puts "<latency><time>$maxdel</time><kind>max</kind></latency>"
    }
    if { $maxloss > 0 } {
	$self topdl_attr "loss" $maxloss
    }
    foreach a $topdl_attrs {
	$self output_topdl_attr $a
    }
    foreach localname $topdl_localname {
	puts "<localname>$localname</localname>"
    }

    if { [string length $topdl_status] > 0 } {
	puts "<status>$topdl_status</status>"
    }

    puts "</substrates>"
}

Lan instproc updatedb {DB} {
    $self instvar nodelist
    $self instvar linkq
    $self instvar trivial_ok
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar backfill
    $self instvar rbackfill
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar widearea
    $self instvar uselinkdelay
    $self instvar emulated
    $self instvar nobwshaping
    $self instvar encap
    $self instvar sim
    $self instvar netmask
    $self instvar protocol
    $self instvar accesspoint
    $self instvar settings
    $self instvar member_settings
    $self instvar mustdelay
    $self instvar fixed_iface

    if {$modelnet_cores > 0 || $modelnet_edges > 0} {
	perror "Lans are not allowed when using modelnet; just duplex links."
	return
    }

    $sim spitxml_data "virt_lan_lans" [list "vname"] [list $self]

    #
    # Upload lan settings and them per-member settings
    #
    foreach setting [array names settings] {
	set fields [list "vname" "capkey" "capval"]
	set values [list $self $setting $settings($setting)]
	
	$sim spitxml_data "virt_lan_settings" $fields $values
    }

    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set isvirt [$node set isvirt]
	set linkqueue $linkq($nodeport)
	set limit_ [$linkqueue set limit_]
	set maxthresh_ [$linkqueue set maxthresh_]
	set thresh_ [$linkqueue set thresh_]
	set q_weight_ [$linkqueue set q_weight_]
	set linterm_ [$linkqueue set linterm_]
	set queue-in-bytes_ [$linkqueue set queue-in-bytes_]
	if {${queue-in-bytes_} == "true"} {
	    set queue-in-bytes_ 1
	} elseif {${queue-in-bytes_} == "false"} {
	    set queue-in-bytes_ 0
	}
	set bytes_ [$linkqueue set bytes_]
	if {$bytes_ == "true"} {
	    set bytes_ 1
	} elseif {$bytes_ == "false"} {
	    set bytes_ 0
	}
	set mean_pktsize_ [$linkqueue set mean_pktsize_]
	set red_ [$linkqueue set red_]
	if {$red_ == "true"} {
	    set red_ 1
	} elseif {$red_ == "false"} {
	    set red_ 0
	}
	set gentle_ [$linkqueue set gentle_]
	if {$gentle_ == "true"} {
	    set gentle_ 1
	} elseif {$gentle_ == "false"} {
	    set gentle_ 0
	}
	set wait_ [$linkqueue set wait_]
	set setbit_ [$linkqueue set setbit_]
	set droptail_ [$linkqueue set drop-tail_]
	
	#
	# Note; we are going to deprecate virt_lans:member and virt_nodes:ips
	# Instead, store vnode,vport,ip in the virt_lans table. To get list
	# of IPs for a node, join virt_nodes with virt_lans. port number is
	# no longer required, but we maintain it to provide a unique key that
	# does not depend on IP address.
	#
	set port [lindex $nodeport 1]
	set ip [$node ip $port]

	$self check-ip-mask $ip $netmask

	set nodeportraw [join $nodeport ":"]

	set is_accesspoint 0
	if {$node == $accesspoint} {
	    set is_accesspoint 1
	}

	set fields [list "vname" "member" "mask" "delay" "rdelay" "bandwidth" "rbandwidth" "backfill" "rbackfill" "lossrate" "rlossrate" "cost" "widearea" "emulated" "uselinkdelay" "nobwshaping" "encap_style" "q_limit" "q_maxthresh" "q_minthresh" "q_weight" "q_linterm" "q_qinbytes" "q_bytes" "q_meanpsize" "q_wait" "q_setbit" "q_droptail" "q_red" "q_gentle" "trivial_ok" "protocol" "is_accesspoint" "vnode" "vport" "ip" "mustdelay"]

	# Treat estimated bandwidths differently - leave them out of the lists
	# unless the user gave a value - this way, they get the defaults if not
	# specified
	if { [info exists ebandwidth($nodeport)] } {
	    lappend fields "est_bandwidth"
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend fields "rest_bandwidth"
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend fields "traced"
	    lappend fields "trace_type"
 	    lappend fields "trace_expr"
 	    lappend fields "trace_snaplen"
 	    lappend fields "trace_endnode"
 	    lappend fields "trace_db"
	}

	# fixing ifaces
        if {$fixed_iface($nodeport) != 0} {
            lappend fields "fixed_iface"
        }

	set values [list $self $nodeportraw $netmask $delay($nodeport) $rdelay($nodeport) $bandwidth($nodeport) $rbandwidth($nodeport) $backfill($nodeport) $rbackfill($nodeport) $loss($nodeport) $rloss($nodeport) $cost($nodeport) $widearea $emulated $uselinkdelay $nobwshaping $encap $limit_  $maxthresh_ $thresh_ $q_weight_ $linterm_ ${queue-in-bytes_}  $bytes_ $mean_pktsize_ $wait_ $setbit_ $droptail_ $red_ $gentle_ $trivial_ok $protocol $is_accesspoint $node $port $ip $mustdelay]

	if { [info exists ebandwidth($nodeport)] } {
	    lappend values $ebandwidth($nodeport)
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend values $rebandwidth($nodeport)
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend values [$linkqueue set traced]
	    lappend values [$linkqueue set trace_type]
	    lappend values [$linkqueue set trace_expr]
	    lappend values [$linkqueue set trace_snaplen]
	    lappend values [$linkqueue set trace_endnode]
	    lappend values [$linkqueue set trace_mysql]
	}

	# fixing ifaces
        if {$fixed_iface($nodeport) != 0} {
            lappend values $fixed_iface($nodeport)
        }

	$sim spitxml_data "virt_lans" $fields $values

	foreach setting_key [array names member_settings] {
	    set foo      [split $setting_key ","]
	    set thisnode [lindex $foo 0]
	    set capkey   [lindex $foo 1]

	    if {$thisnode == $node} {
		set fields [list "vname" "member" "capkey" "capval"]
		set values [list $self $nodeportraw $capkey \
		                 $member_settings($setting_key)]
	
		$sim spitxml_data "virt_lan_member_settings" $fields $values
	    }
	}
    }
}

#
# Convert IP/Mask to an integer (host order)
#
proc inet_atohl {ip} {
    if {[scan $ip "%d.%d.%d.%d" a b c d] != 4} {
	perror "\[inet_atohl] Invalid ip $ip; cannot be converted!"
	return 0
    }
    return [expr ($a << 24) | ($b << 16) | ($c << 8) | $d]
}
proc inet_hltoa {ip} {
    set a [expr ($ip >> 24) & 0xff]
    set b [expr ($ip >> 16) & 0xff]
    set c [expr ($ip >> 8)  & 0xff]
    set d [expr ($ip >> 0)  & 0xff]

    return "$a.$b.$c.$d"
}


#
#New constructor for Click parameters
#

NewLan instproc init {s nodes bw d {dist ""} { stv 0 } {l  0} {dropmode ""} {rate 0} } {
    var_import GLOBALS::new_counter

    # This is a list of {node port} pairs.
    $self set nodelist {}

    # The simulator
    $self set sim $s

    # By default, a local link
    $self set widearea 0

    # Default type is a plain "ethernet". User can change this.
    $self set protocol "ethernet"

    # Colocation is on by default, but this only applies to emulated links
    # between virtual nodes anyway.
    $self set trivial_ok 1

    # Allow user to control whether link gets a linkdelay, if link is shaped.
    # If not shaped, and user sets this variable, a link delay is inserted
    # anyway on the assumption that user wants later control over the link.
    # Both lans and links can get linkdelays.     
    $self set uselinkdelay 0

    # Allow user to control if link is emulated.
    $self set emulated 0

    # Allow user to turn off actual bw shaping on emulated links.
    $self set nobwshaping 0

    # mustdelay; force a delay (or linkdelay) to be inserted. assign_wrapper
    # is free to override this, but not sure why it want to! When used in
    # conjunction with nobwshaping, you get a delay node, but with no ipfw
    # limits on the bw part, and assign_wrapper ignores the bw when doing
    # assignment.
    $self set mustdelay 0

    # Allow user to specify encapsulation on emulated links.
    $self set encap "default"

    # XXX Allow user to set the accesspoint.
    $self set accesspoint {}

    # A simulated lanlink unless we find otherwise
    $self set simulated 1
    # Figure out if this is a lanlink that has at least
    # 1 non-simulated node in it. 
    foreach node $nodes {
	if { [$node set simulated] == 0 } {
	    $self set simulated 0
	    break
	}
    }

    # The default netmask, which the user may change (at his own peril).
    $self set netmask "255.255.255.0"

    # Make sure BW is reasonable. 
    # XXX: Should come from DB instead of hardwired max.
    # Measured in kbps
    set maxbw 1000000

    # XXX skip this check for a simulated lanlink even if it
    # causes nse to not keep up with real time. The actual max
    # for simulated links will be added later
    if { [$self set simulated] != 1 && $bw > $maxbw } {
	perror "Bandwidth requested ($bw) exceeds maximum of $maxbw kbps!"
	return
    }

    # Virt lan settings, for the entire lan
    $self instvar settings

    # And a two-dimenional arrary for per-member settings.
    # TCL does not actually have multi-dimensional arrays though, so its faked.
    $self instvar member_settings

    # Now we need to fill out the nodelist
    $self instvar nodelist

    # r* indicates the switch->node chars, others are node->switch
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar backfill
    $self instvar rbackfill
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar linkq
    $self instvar fixed_iface

    $self instvar iscloud
    $self set iscloud 0

    #added for new loss and delay
    $self instvar mean
    $self instvar standardvariation
    $self instvar delaytype
    $self instvar lossthreshold
    $self instvar lossdropmode
    $self instvar lossratenum 
    foreach node $nodes {
	set nodepair [list $node [$node add_lanlink $self]]
	set bandwidth($nodepair) $bw
	set rbandwidth($nodepair) $bw
	# Note - we don't set defaults for ebandwidth and rebandwidth - lack
	# of an entry for a nodepair indicates that they should be left NULL
	# in the output.
	set backfill($nodepair) 0
	set rbackfill($nodepair) 0
	set delay($nodepair) [expr $d / 2.0]
	set rdelay($nodepair) [expr $d / 2.0]
	set loss($nodepair) 0
	set rloss($nodepair) 0
	set cost($nodepair) 1
	set fixed_iface($nodepair) 0

	set mean($nodepair) $d
    	set standardvariation($nodepair) $stv
    	set delaytype($nodepair) $dist
    	set lossthreshold($nodepair) $l
    	set lossdropmode($nodepair) $dropmode
    	set lossratenum($nodepair) $rate

	lappend nodelist $nodepair

	set lq q[incr new_counter]
	Queue lq$lq $self {} $node
	set linkq($nodepair) lq$lq
    }
}

#Generate xml that includes new delay parameters
NewLan instproc updatedb {DB} { 
    $self instvar nodelist
    $self instvar linkq
    $self instvar trivial_ok
    var_import ::GLOBALS::pid
    var_import ::GLOBALS::eid
    var_import ::GLOBALS::modelnet_cores
    var_import ::GLOBALS::modelnet_edges
    $self instvar bandwidth
    $self instvar rbandwidth
    $self instvar ebandwidth
    $self instvar rebandwidth
    $self instvar backfill
    $self instvar rbackfill
    $self instvar delay
    $self instvar rdelay
    $self instvar loss
    $self instvar rloss
    $self instvar cost
    $self instvar widearea
    $self instvar uselinkdelay
    $self instvar emulated
    $self instvar nobwshaping
    $self instvar encap
    $self instvar sim
    $self instvar netmask
    $self instvar protocol
    $self instvar accesspoint
    $self instvar settings
    $self instvar member_settings
    $self instvar mustdelay
    $self instvar fixed_iface

    #new variable for new delay and loss
    $self instvar mean
    $self instvar standardvariation
    $self instvar delaytype
    $self instvar lossthreshold
    $self instvar lossdropmode
    $self instvar lossratenum
    $self instvar new
    if {$modelnet_cores > 0 || $modelnet_edges > 0} {
	perror "Lans are not allowed when using modelnet; just duplex links."
	return
    }

    $sim spitxml_data "virt_lan_lans" [list "vname"] [list $self]

    #
    # Upload lan settings and them per-member settings
    #
    foreach setting [array names settings] {
	set fields [list "vname" "capkey" "capval"]
	set values [list $self $setting $settings($setting)]
	
	$sim spitxml_data "virt_lan_settings" $fields $values
    }

    foreach nodeport $nodelist {
	set node [lindex $nodeport 0]
	set isvirt [$node set isvirt]
	set linkqueue $linkq($nodeport)
	set limit_ [$linkqueue set limit_]
	set maxthresh_ [$linkqueue set maxthresh_]
	set thresh_ [$linkqueue set thresh_]
	set q_weight_ [$linkqueue set q_weight_]
	set linterm_ [$linkqueue set linterm_]
	set queue-in-bytes_ [$linkqueue set queue-in-bytes_]
	if {${queue-in-bytes_} == "true"} {
	    set queue-in-bytes_ 1
	} elseif {${queue-in-bytes_} == "false"} {
	    set queue-in-bytes_ 0
	}
	set bytes_ [$linkqueue set bytes_]
	if {$bytes_ == "true"} {
	    set bytes_ 1
	} elseif {$bytes_ == "false"} {
	    set bytes_ 0
	}
	set mean_pktsize_ [$linkqueue set mean_pktsize_]
	set red_ [$linkqueue set red_]
	if {$red_ == "true"} {
	    set red_ 1
	} elseif {$red_ == "false"} {
	    set red_ 0
	}
	set gentle_ [$linkqueue set gentle_]
	if {$gentle_ == "true"} {
	    set gentle_ 1
	} elseif {$gentle_ == "false"} {
	    set gentle_ 0
	}
	set wait_ [$linkqueue set wait_]
	set setbit_ [$linkqueue set setbit_]
	set droptail_ [$linkqueue set drop-tail_]
	
	#
	# Note; we are going to deprecate virt_lans:member and virt_nodes:ips
	# Instead, store vnode,vport,ip in the virt_lans table. To get list
	# of IPs for a node, join virt_nodes with virt_lans. port number is
	# no longer required, but we maintain it to provide a unique key that
	# does not depend on IP address.
	#
	set port [lindex $nodeport 1]
	set ip [$node ip $port]

	$self check-ip-mask $ip $netmask

	set nodeportraw [join $nodeport ":"]

	set is_accesspoint 0
	if {$node == $accesspoint} {
	    set is_accesspoint 1
	}

	set fields [list "vname" "member" "mask" "delay" "rdelay" "bandwidth" "rbandwidth" "backfill" "rbackfill" "lossrate" "rlossrate" "cost" "widearea" "emulated" "uselinkdelay" "nobwshaping" "encap_style" "q_limit" "q_maxthresh" "q_minthresh" "q_weight" "q_linterm" "q_qinbytes" "q_bytes" "q_meanpsize" "q_wait" "q_setbit" "q_droptail" "q_red" "q_gentle" "trivial_ok" "protocol" "is_accesspoint" "vnode" "vport" "ip" "mustdelay" "mean" "standardvariation" "delaytype" "lossthreshold" "lossdropmode" "lossratenum" "new"]

	# Treat estimated bandwidths differently - leave them out of the lists
	# unless the user gave a value - this way, they get the defaults if not
	# specified
	if { [info exists ebandwidth($nodeport)] } {
	    lappend fields "est_bandwidth"
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend fields "rest_bandwidth"
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend fields "traced"
	    lappend fields "trace_type"
 	    lappend fields "trace_expr"
 	    lappend fields "trace_snaplen"
 	    lappend fields "trace_endnode"
 	    lappend fields "trace_db"
	}

	# fixing ifaces
        if {$fixed_iface($nodeport) != 0} {
            lappend fields "fixed_iface"
        }

	set values [list $self $nodeportraw $netmask $delay($nodeport) $rdelay($nodeport) $bandwidth($nodeport) $rbandwidth($nodeport) $backfill($nodeport) $rbackfill($nodeport) $lossthreshold($nodeport) $lossthreshold($nodeport) $cost($nodeport) $widearea $emulated $uselinkdelay $nobwshaping $encap $limit_  $maxthresh_ $thresh_ $q_weight_ $linterm_ ${queue-in-bytes_}  $bytes_ $mean_pktsize_ $wait_ $setbit_ $droptail_ $red_ $gentle_ $trivial_ok $protocol $is_accesspoint $node $port $ip $mustdelay $mean($nodeport) $standardvariation($nodeport) $delaytype($nodeport) $lossthreshold($nodeport) $lossdropmode($nodeport) $lossratenum($nodeport) "YES"]

	if { [info exists ebandwidth($nodeport)] } {
	    lappend values $ebandwidth($nodeport)
	}

	if { [info exists rebandwidth($nodeport)] } {
	    lappend values $rebandwidth($nodeport)
	}

	# Tracing.
	if {[$linkqueue set traced] == 1} {
	    lappend values [$linkqueue set traced]
	    lappend values [$linkqueue set trace_type]
	    lappend values [$linkqueue set trace_expr]
	    lappend values [$linkqueue set trace_snaplen]
	    lappend values [$linkqueue set trace_endnode]
	    lappend values [$linkqueue set trace_mysql]
	}

	# fixing ifaces
        if {$fixed_iface($nodeport) != 0} {
            lappend values $fixed_iface($nodeport)
        }

	$sim spitxml_data "virt_lans" $fields $values

	foreach setting_key [array names member_settings] {
	    set foo      [split $setting_key ","]
	    set thisnode [lindex $foo 0]
	    set capkey   [lindex $foo 1]

	    if {$thisnode == $node} {
		set fields [list "vname" "member" "capkey" "capval"]
		set values [list $self $nodeportraw $capkey \
		                 $member_settings($setting_key)]
	
		$sim spitxml_data "virt_lan_member_settings" $fields $values
	    }
	}
    }
}
