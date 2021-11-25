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
package main

import (
	//"testbedGS/common/tbMessages"
	"net"
	//"testbedGS/common/tbConfiguration"
	"fmt"
	//"testbedGS/common/tbJsonUtils"
	"strconv"
	//"testbedGS/common/tbMsgUtils"
)

//============================================================================
// Locate specific master module in the slice of all masters (containing rows for
// all known masters including ourselves and the office master). The slice is
// populated from the KeepAlive msg received from officeMaster
// Return nil if row not found
//============================================================================
func LocateMaster(slice [] TBmgr, NameOfMaster string) (*TBmgr, int){
	for index := range slice {
		if  slice[index].Name.Name == NameOfMaster {
			return &slice[index], index
		}
	}
	// requested master not found in the slice of master
	return nil, -1
}
//====================================================================================
//
//====================================================================================
func LocateOfficeMaster(myName string, sliceOfMgrs [] TBmgr) (*net.UDPAddr,
						NameId, bool) {
	// NOTE that this will fail if officeMasterUdpAddress has not been initialized due
	// to Office Manager not being up . Add state and try again to Resolve before
	// doing this
	var err error
	var UdpAddress	= new(net.UDPAddr)
	var FullName      NameId

	// fmt.Println(myName, ": Locate Office Manager")
	UdpAddress, err = net.ResolveUDPAddr("udp", TBofficeMgr)
	if err != nil {
		fmt.Println(myName,": ERROR locating Office Manager, will retry", err)
		return nil, FullName, false
	}

	FullName = NameId{Name: TBofficeMgrName, OsId: 0,
		TimeCreated: "0", Address: *UdpAddress}

	// Now that we know that officeMaster is alive add entry to our slice of masters
	officeMaster := TBmgr{Name: FullName, Up:true, LastChangeTime:"0",
		MsgsSent: 0, LastSentAt: "0", MsgsRcvd:0, LastRcvdAt:"0"}

	sliceOfMgrs = append(sliceOfMgrs, officeMaster)

	theMgr, _ :=  LocateMaster(sliceOfMgrs, TBofficeMgrName)
	if theMgr != nil {
		fmt.Println(myName, ": MGR=",theMgr.Name.Name, "ADDRESS:",theMgr.Name.Address,
			"CREATED:",theMgr.Name.TimeCreated, "MSGSRCVD:",theMgr.MsgsRcvd)
	}
	return UdpAddress, FullName, true
}

//====================================================================================
//
//====================================================================================
func formatReceiver(name string, osId int, udpAddress net.UDPAddr) NameId {
	receiver := NameId{Name: name, OsId: osId,
		TimeCreated: "", Address: udpAddress}
	return receiver
}
//====================================================================================
// Save the pointer to my own row for faster handling
// Send registration msg to officeMaster containing our full row from our slice of
// masters.
//====================================================================================
func SendRegisterMsg(senderFullName NameId,
			sliceOfMasters [] TBmgr, myConnection *net.UDPConn ) {
	// Locate our own record in the slice of masters
	me, _ := LocateMaster(sliceOfMasters, senderFullName.Name)

	if me != nil {
		officeUdpAddress, rcvrFullName, result :=
			LocateOfficeMaster(senderFullName.Name, sliceOfMasters )
		if result == false {
			fmt.Println(senderFullName.Name, ": Failed to Send Register Msg")
			return
		}
		// Marshall our own row from the slice
		msgBody, _ := TBmarshal(me)
		me.LastSentAt = strconv.FormatInt(TBtimestamp(),10)
		newMsg := TBregisterMsg(senderFullName, rcvrFullName, string(msgBody))
		// fmt.Println(myName, "stateConnected REGISTER with officeMaster ")
		TBsendMsgOut(newMsg, *officeUdpAddress, myConnection)
	} else {
		fmt.Println(senderFullName.Name, ": FAILED to locate mngr record in the slice")
	}
}
