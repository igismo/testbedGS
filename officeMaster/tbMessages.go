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
// FILE NAME: tbMessages.go
// DESCRIPTION:
// Contains description of all possible messages
//
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================

package main
import (
"net"

)
//      "os/user"
type TBmessage struct {
	MsgReceiver  NameId
	MsgSender    NameId
	// MsgClass  string
	MsgType      string
	TimeSent     string      // msg send time
	MsgBody      [] byte // Message type specific body
}

type TBmessageExpCreate struct {
	MsgReceiver  NameId
	MsgSender    NameId
	// MsgClass  string
	MsgType      string
	TimeSent     string      // msg send time
	MsgBody      ExpCreate // Message type specific body
}

type MsgData interface {

	area() float64
	perim() float64
}

type TBmgr struct {
	Name                    NameId
	Up                      bool
	LastChangeTime          string
	MsgsSent                int64
	LastSentAt              string
	MsgsRcvd                int64
	LastRcvdAt              string
}

type NameId struct {
	Name         string  // Name String
	OsId         int     // Task id, if known
	TimeCreated  string      // my incarnation time
	Address      net.UDPAddr
}


// Message source or destination
//----------------------------------------------------------------------------
const EXP_MGR     = "ExpMgr"
const RSRC_MGR    = "RsrcMgr"
const OFFICE_MGR  = "OfficeMgr"

// Message Classes and types within classes:
const  MSG_CLASS_CONTROL       = "MSG_CLASS_CONTROL"

// Format of message body part, if any specific data ....
//----------------------------------------------------------------------------
const  MSG_TYPE_INIT           = "MSG_INIT"
type  MsgInit struct {
	//
}
const  MSG_TYPE_REGISTER     = "REGISTER"
type  MsgRegister struct {
	Mgr TBmgr
}
const  MSG_TYPE_KEEPALIVE     = "KEEPALIVE"
type  MsgKeepAlkive struct {
	tableOfMgrs [] TBmgr
}
const  MSG_TYPE_HELLO     = "HELLO"
type  MsgHello struct {
	tableOfMgrs [] TBmgr
}
const  MSG_TYPE_HELLO_REPPLY     = "HELLO_REPLY"
type  MsgHelloReply struct {

}
const  MSG_TYPE_CONNECT              = "CONNECT"
type  MsgConnect struct {

}
const  MSG_TYPE_CONNECTING     = "CONNECTING"
type  MsgConnecting struct {

}
const  MSG_TYPE_CONNECTED      = "CONNECTED"
type  MsgConnected struct {

}
const  MSG_TYPE_DISCONNECT         = "DISCONNECT"
type  MsgDisconnect struct {

}
const  MSG_TYPE_DISCONNECTING   = "DISCONNECTING"
type  MsgDisconnecting struct {

}
const  MSG_TYPE_DISCONNECTED   = "MSG_DISCONNECTED"
type  MsgDisconnected struct {

}
const  MSG_TYPE_TERMINATE      = "MSG_TERMINATE"
type  MsgTerminate struct {

}
const  MSG_TYPE_TERMINATING    = "MSG_TERMINATING"
type  MsgTerminating struct {

}
const  MSG_TYPE_TERMINATED     = "MSG_TERMINATED"
type  MsgTerminated struct {

}

const  MSG_CLASS_RSRCMGR       = "MSG_CLASS_RSRCMGR"
//----------------------------------------------------------------------------


const  MSG_CLASS_OFFICEMGR     = "MSG_CLASS_OFFICEMGR"
//----------------------------------------------------------------------------
const  MSG_TYPE_EXPCREATE           = "EXPCREATE"
type  ExpCreate struct {
	Project         string // $exp_pid
	Experiment      string // $exp_id
	GroupId         string // $exp_gid
	LinktestArgs    string // $linktestarg
	ExtraGroups     string // $extragroups
	ExpSwappable    string // $exp_swappable
	ExpDesc         string // $exp_desc
	BatchArgs       string // $batcharg
	UserName        string // $uid
	FileName        string // $thensfile
}



const  MSG_TYPE_SWAPIN           = "SWAPIN"
type  SwapIn struct {
	Project         string
	Experiment      string
	UserName        string
	FileName        string
}
const  MSG_TYPE_SWAPOUT           = "SWAPOUT"
type  SwapOut struct {
	Project         string
	Experiment      string
	UserName        string
	FileName        string
}

