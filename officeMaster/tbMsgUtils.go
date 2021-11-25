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
// FILE NAME: tbMsgUtils.go
// DESCRIPTION:
// Utilities for creating and handling messages used by the
// Experiment Master and Experiment Controllers.
// Contains description of all possible messages related to Experiments
//
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================

package main

import (
	"time"
	"fmt"
	//"testbedGS/common/tbMessages"
	//"testbedGS/common/tbJsonUtils"
	"strconv"
	"net"
)
//============================================================================
// Create a Hello message (to send to office mgr)
//============================================================================
func TBtimestamp() int64 {
	return time.Now().UnixNano() / 1000 // / (int64(time.Millisecond)/int64(time.Nanosecond))
}
//====================================================================================
//
//====================================================================================
func TBsendMsgOut(msgOut []byte, udpAddress net.UDPAddr, udpConnection *net.UDPConn) {

	if udpConnection != nil { // returns numBytes, err
		udpConnection.WriteToUDP(msgOut, &udpAddress)
	} else {
		fmt.Println("ERROR Sending Out to",udpAddress,": udpConnection = nil")
	}
}
//=============================================================================
// Function:    expMessage
// Description:
//         Create marshaled message ready to be sent out.
// Input:  sender,receiver    = name of the sender and receiver
//         mclass,mtype,mbody = message class and type, message body string
// Output: msg = marshalled message
// Error Conditions:
//      None [or state condition for each possible error]
//=============================================================================
func TBmarschalMessage(sender, receiver NameId,mtype,mbody string) ([] byte){
	// length := len(mbody)
	currentTime         := strconv.FormatInt(TBtimestamp(),10)
	msgBytes :=  []byte(mbody)
	myMsg := TBmessage {
		MsgSender:    sender,
		MsgReceiver:  receiver,
		MsgType:      mtype,
		TimeSent:     currentTime,
		MsgBody:      msgBytes,
	}

	msg,_ := TBmarshal(myMsg)
	return msg
}
//============================================================================
// Create a Hello message (to send to office mgr)
//============================================================================
func TBConnectedMsg(sender, receiver NameId, mBody string) []byte {
	//fmt.Println("MsgUtils: create CONNECTED msg")
	msg := TBmarschalMessage(sender,receiver, MSG_TYPE_CONNECTED, mBody)
	return msg
}
//============================================================================
// Create a Hello message (to send to office mgr)
//============================================================================
func TBkeepAliveMsg(sender, receiver NameId, mBody string) []byte {
	fmt.Println("MsgUtils: create KEEP ALIVE msg, mBody=",mBody)
	msg := TBmarschalMessage(sender,receiver,MSG_TYPE_KEEPALIVE, mBody)
	return msg
}
//============================================================================
// Create a Hello message (to send to office mgr)
//============================================================================

// INSTEAD OF  receiver provide *msg.MsgReceiver
// AND extract all of receivers fields

func TBhelloMsg(sender, receiver NameId, mBody string) []byte {
	fmt.Println("MsgUtils: create HELLO msg, mBody=",mBody)
	msg := TBmarschalMessage(sender,receiver,MSG_TYPE_HELLO, mBody)
	return msg
}
//============================================================================
// Create a REGISTER message (to send to office mgr)
//============================================================================
func TBregisterMsg(sender, receiver NameId, mBody string) []byte {
	//fmt.Println("MsgUtils: create REGISTER msg, mBody=",mBody)
	msg := TBmarschalMessage(sender,receiver,MSG_TYPE_REGISTER, mBody)
	return msg
}
//============================================================================
// Create a Hello message (to send to office mgr)
//============================================================================
func TBhelloReplyMsg(sender, receiver NameId, mBody string) []byte {
	fmt.Println("MsgUtils: create HELLO REPPLY msg, mBody=",mBody)
	msg := TBmarschalMessage(sender,receiver, MSG_TYPE_HELLO_REPPLY, mBody)
	return msg

}
//====================================================================================
//
//====================================================================================
//func sendHelloReplyMsg(senderName NameId, msg *TBmessage) {
//	receiver := msg.MsgSender
//	newMsg := TBhelloMsg(senderName, receiver, senderName.Name + " is alive")
//	TBsendMsgOut(newMsg, receiver.Address, myConnection)
//}
//============================================================================
// Create a SwapIn message (to send to experiment master)
//============================================================================
func TBswapinMsg(sender, receiver NameId, mBody string) []byte {
	fmt.Println("MsgUtils: create SwapIn msg, mBody=",mBody)
	msg := TBmarschalMessage(sender,receiver,MSG_TYPE_SWAPIN, mBody)
	return msg
}
//============================================================================
// Create a SwapIn message (to send to experiment master)
//============================================================================
func TBswapoutMsg(sender, receiver NameId, mBody string) []byte {
	fmt.Println("MsgUtils: create Swapout msg, mBody=",mBody)
	msg := TBmarschalMessage(sender,receiver,MSG_TYPE_SWAPOUT, mBody)
	return msg
}
//============================================================================
// Create a Hello message (to send to office mgr)
//============================================================================
func TBmsgSample(myName, receiver NameId, mBody string) ([] byte){

	msgBytes   :=  []byte(mBody)

	myMsg := TBmessage {
		MsgSender:    myName,
		MsgReceiver:  receiver,
		// MsgClass:    MSG_CLASS_CONTROL,
		MsgType:      MSG_TYPE_INIT,
		MsgBody:      msgBytes,
	}

	msg,_ := TBmarshal(myMsg)
	fmt.Println("JSON=", string(msg))
	return msg
}
