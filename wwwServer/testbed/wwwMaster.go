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
"net"
"os"
"fmt"
"strings"
"testbedGS/common/tbJsonUtils"
"testbedGS/common/tbNetUtils"
"testbedGS/common/tbConfiguration"
"testbedGS/common/tbMessages"
"testbedGS/common/tbMsgUtils"
"testbedGS/common/tbLogUtils"
"testbedGS/common/tbUtils"
"strconv"
"time"
// "os/exec"
// "log"
	"io/ioutil"
	"net/rpc"
	"github.com/spiral/goridge"
)

// WWW MASTER STATES ===============================
var myName               = tbConfig.TBwwwMasterName
var myUdpName			 = tbConfig.TBwwwMaster
var myUdpNameWWW	     = tbConfig.TBwebMaster
const ConnectionTimer    = 3*5 // =15 sec
// list of Masters we want to know about
var mysqlMasterIP = ""
var expMasterIP   = ""
//==================================================

const StateINIT       = "INIT"
const StateCONNECTING = "CONNECTING"
const StateCONNECTED  = "CONNECTED"
const StateUP         = "UP"
const StateDOWN       = "DOWN"

var myFullName             tbMessages.NameId
var myUdpAddress         = new(net.UDPAddr)
var myUdpAddressWWW      = new(net.UDPAddr)
var myIpAddress          = ""
var myConnection	       *net.UDPConn = nil
var myConnectionWWW	       *net.UDPConn = nil
var myState              = StateINIT
var myCreationTime       = strconv.FormatInt(tbMsgUtils.TBtimestamp(),10)

var myRecvChannelWWW     chan []byte = nil // rcv from web server
var myRecvChannel        chan []byte = nil // To Receive messages from other modules
var myControlChannel     chan []byte = nil // so that all local threads can talk back
// var mySendChannel        chan []byte = nil // To Send messages out to other modules
// var mySendControlChannel chan []byte = nil // to send control msgs to Send Thread
var myRecvControlChannel chan []byte = nil // to send control msgs to Recv Thread
var myReceiveCount       = 0
var myConnectionTimer    = 0
var myLastKeepAliveReceived = time.Now()

var officeMasterUdpAddress	= new(net.UDPAddr)
var officeMasterFullName      tbMessages.NameId

var Log = tbLogUtils.LogInstance{}
var sliceOfMasters [] tbMessages.TBmgr

//====================================================================================
// MAIN:
// Initialize and then wait on myRecvChannel and myControlChannel to receive messages 
// Dispatch depending on our FSM state and handle appripriatelly
//====================================================================================
func main() {

	Log.DebugLog   = true
	Log.WarningLog = true
	Log.ErrorLog   = true
	tbLogUtils.CreateLog(&Log, myName)
	Log.Warning(&Log,myName + ": this will be printed anyway")

	myInit()
	fmt.Println(myName,": MAIN: Starting a new ticker....")
	// Set 3 sec timer ticks
	ticker := time.NewTicker(3 * time.Second)
	go func(){
		for t := range ticker.C {
			//Call the periodic function here.
			periodicFunc(t)
		}
	}()

	for {
		select {
		case netMsg := <-myRecvChannel:
			// fmt.Println(myName, "MAIN: DATA MSG in state", myState, "MSG=",string(msg1))
			handleMessages(netMsg)

		case controlMsg := <-myControlChannel: // ???
			fmt.Println(myName, ": Control msg in state", myState, "MSG=",string(controlMsg))
			handleControlMessages(controlMsg)

		case netMsgWWW := <-myRecvChannelWWW:
			fmt.Println(myName, "MAIN: DATA MSG in state", myState, "MSG=",string(netMsgWWW))
			// TODO handleMessagesWWW(netMsg)

		} // EndOfSelect
	}

	os.Exit(0)
}


//====================================================================================
// At tick intervals subtract tick interval from the connection timer.
// If connection timer expired and we are connected to the officeMaster, then
// send registration msg again to tell officeMaster we are alive. If officeMaster is
// not known renew the connection timer and try again.
// Othervise if connection timer is not active, and we did not here from officeMaster
// during the last minute, send register message again
//====================================================================================
func periodicFunc(tick time.Time){

	var result bool

	if mysqlMasterIP == "" {
		mysqlMasterIP = tbNetUtils.GetMastersIP(tbConfig.TBmysqlMasterName)
		//fmt.Println("INIT TB-MYSQLMASTER is at ", mysqlMasterIP)
		if mysqlMasterIP != "" {
			// TODO save address into file .... to be used by www server
			ipData := []byte(mysqlMasterIP)
			err := ioutil.WriteFile("/var/www/mysqlip", ipData, 0644)
			if err != nil {
				fmt.Println("TICK: FAILED TO STORE TB-MYSQLMASTER IP to /var/www/mysqlip")
			} else {
				fmt.Println("TICK: DISCOVERED TB-MYSQLMASTER is at ", mysqlMasterIP)
			}
		}
	}else {
		//fmt.Println("TIMER: DISCOVERED TB-MYSQLMASTER is at ", expMasterIP)
	}
	if expMasterIP == "" {
		expMasterIP = tbNetUtils.GetMastersIP(tbConfig.TBmysqlMasterName)
		//fmt.Println("INIT TB-MYSQLMASTER is at ", expMasterIP)
		if expMasterIP != "" {
			// TODO save address into file .... to be used by www server
			ipData := []byte(expMasterIP)
			err := ioutil.WriteFile("/var/www/expmasterip", ipData, 0644)
			if err != nil {
				fmt.Println("TICK: FAILED TO STORE TB-EXPMASTER IP to /var/www/expmasterip")
			} else {
				fmt.Println("TICK: DISCOVERED TB-EXPMASTER is at ", expMasterIP)
			}
		}
	} else {
		//fmt.Println("TIMER: DISCOVERED TB-EXPMASTER is at ", expMasterIP)
	}

	if myConnectionTimer > 0 {// Connection timer is running,
		myConnectionTimer--
		// fmt.Println(myName, ": myConnectionTimer=",myConnectionTimer)
		
		if myConnectionTimer == 0 { // if expired try connecting
			officeMasterUdpAddress, officeMasterFullName, result =
				tbUtils.LocateOfficeMaster(myName, sliceOfMasters )
				
			if result == true {
				//fmt.Println(myName, ": CONNECTED TO OFFICE MASTER, tick=", tick)
				FSMSetState(StateCONNECTED)
				tbUtils.SendRegisterMsg(myFullName, sliceOfMasters, myConnection)
				myLastKeepAliveReceived = time.Now()
			} else {
				//fmt.Println(myName, ": Set myConnectionTimer=",myConnectionTimer)
				myConnectionTimer = ConnectionTimer
			}
		}
	} else { // Connection timer is NOT running, we are already connected
		currTime := time.Now()
		elapsedTime := currTime.Sub(myLastKeepAliveReceived)
		//fmt.Println(myName, ": Elapsed time=", elapsedTime)
		// Is it a time for Keep Alive ?
		if elapsedTime > (time.Second * 15) { // Then resend keep alive
			officeMasterUdpAddress, officeMasterFullName, result =
				tbUtils.LocateOfficeMaster(myName, sliceOfMasters )
			// Is office Master still there ?
			if result == true { // Yes, we are still connected
				FSMSetState(StateCONNECTED)
				tbUtils.SendRegisterMsg(myFullName, sliceOfMasters, myConnection)
				myLastKeepAliveReceived = time.Now()
			} else { // We are disconnected, retry after myConnectionTimer expires
				FSMSetState(StateCONNECTING)
				myConnectionTimer = ConnectionTimer
			}
		}
	}
}

//====================================================================================
// sendThread() - Thread sending our messages out
// The caller supplies the control channel over which
// control messages can be received by this thread
// Parameters:	service - 10.0.0.2:1200
// 				sendControlChannel - channel
//
//====================================================================================
/*
func sendThread(conn *net.UDPConn, sendChannel, sendControlChannel chan []byte) error {
	var err error = nil
	fmt.Println(myName, "SendThread: Start SEND THRED")
	go func() {
		connection := conn
		var controlMsg tbMessages.TBmessage
		fmt.Println(myName, "SendThread: connected")

		myControlChannel <- tbMsgUtils.TBConnectedMsg(myFullName, myFullName, "")

		for {
			select {
			case msgOut := <-sendChannel: // got msg to send out
				// fmt.Println(myName, "SendThread: Sending MSG=",msgOut)
				fmt.Println(myName, "SendThread: Sending MSG out to", officeMasterUdpAddress)
				// _, err = connection.Write([]byte(msgOut))
				connection.WriteToUDP([]byte(msgOut), officeMasterUdpAddress)
				if err != nil {
					fmt.Fprintf(os.Stderr, "Error Sending %s", err.Error())
					// create more descriptive msg
					// send msg up to indicate a problem ?
				}

			case ctrlMsg := <- sendControlChannel: //
				tbJsonUtils.TBunmarshal(ctrlMsg, &controlMsg)
				fmt.Println(myName, "SendThread got control MSG=",controlMsg)

				if strings.Contains(controlMsg.MsgType, "TERMINATE") {
					fmt.Println(myName, "SendThread rcvd control MSG=", controlMsg)
					return
				}
			}
		}

	}()

	return err
}
*/
//====================================================================================
// recvThread() - Thread receiving messages from others
// Wait on the connection to receive UDP messages, then send it to myRecvChannel
//====================================================================================
func recvThread(conn *net.UDPConn, recvControlChannel <-chan []byte) error {
	var err error = nil

	//fmt.Println(myName,"RecvThread: Start RECV THRED")
	go func() {
		connection := conn

		fmt.Println(myName,": RecvThread: Start Receiving")
		var controlMsg tbMessages.TBmessage
		var oobBuffer [3000] byte
		// Tell main we are coonected all is good
		// myControlChannel <- tbMsgUtils.TBConnectedMsg(myFullName, myFullName, "")

		for {
			recvBuffer := make([]byte, 3000)
			length, oobn, flags , addr , err  := connection.ReadMsgUDP(recvBuffer[0:], oobBuffer[0:])
			myReceiveCount++
			//fmt.Println(myName,": MSG received Count=",myReceiveCount)
			fmt.Println(myName,":RCV UDP MSG from",addr, "len=",length,"oobLen=", oobn,"flags=",flags,"ERR=",err)
			//fmt.Println(myName,":RCV UDP MSG=", string(recvBuffer[0:length]))

			myRecvChannel <- recvBuffer[0:length]

			if len(recvControlChannel) != 0 {
				ctrlMsg := <- recvControlChannel
				tbJsonUtils.TBunmarshal(ctrlMsg, &controlMsg)
				fmt.Println(myName, ": RecvThread got CONTROL MSG=",controlMsg)
				if strings.Contains(controlMsg.MsgType, "TERMINATE") {
					fmt.Println(": RecvThread rcvd control MSG=", controlMsg)
					return
				}
			}
		}
	}()

	return err
}
//====================================================================================
// recvThread() - Thread receiving messages from others
// Wait on the connection to receive UDP messages, then send it to myRecvChannel
//====================================================================================
func recvThreadWWW(conn *net.UDPConn, recvControlChannel <-chan []byte) error {
	var err error = nil

	//fmt.Println(myName,"RecvThread: Start RECV THRED")
	go func() {
		connection := conn

		fmt.Println(myName,": RecvThread: Start Receiving WWW on ", conn.LocalAddr())
		// var controlMsg tbMessages.TBmessage
		var oobBuffer [3000] byte
		// Tell main we are coonected all is good
		// myControlChannel <- tbMsgUtils.TBConnectedMsg(myFullName, myFullName, "")

		for {
			recvBuffer := make([]byte, 3000)
			length, oobn, flags , addr , err  := connection.ReadMsgUDP(recvBuffer[0:], oobBuffer[0:])
			myReceiveCount++
			fmt.Println(myName,": WWW MSG =======================received Count=",myReceiveCount,
				"RecvThread UDP MSG from",addr, "len=",length,"oobLen=", oobn,"flags=",flags,"ERR=",err)
			//fmt.Println(myName,"RecvThread MSG=", string(recvBuffer[0:length]))
			fmt.Println(myName,": WWW MSG =", string(recvBuffer))
			myRecvChannelWWW <- recvBuffer[0:length]
/*
			if len(recvControlChannel) != 0 {
				ctrlMsg := <- recvControlChannel
				tbJsonUtils.TBunmarshal(ctrlMsg, &controlMsg)
				fmt.Println(myName, ": RecvThread got CONTROL MSG=",controlMsg)
				if strings.Contains(controlMsg.MsgType, "TERMINATE") {
					fmt.Println(": RecvThread rcvd control MSG=", controlMsg)
					return
				}
			}
*/
		}
	}()

	return err
}
//====================================================================================
//
//====================================================================================
func handleMessages(message []byte) {
	// Unmarshal
	msg := new(tbMessages.TBmessage)

	tbJsonUtils.TBunmarshal(message, &msg)
	// fmt.Println(myName,"HandleMessages MSG=", string(message),"Sizeof(msg)=",unsafe.Sizeof(msg))
	fmt.Println(myName,": RCV HandleMessages MSG Type:",msg.MsgType, " From:",
		        msg.MsgSender.Name," To Me:",msg.MsgReceiver.Name)

	switch myState {
	case StateINIT:
		stateInitMessages(msg)
		break
	case StateCONNECTING:
		stateConnectingMessages(msg)
		break
	case StateCONNECTED:
		stateConnectedMessages(msg)
		break
	case StateUP:
	case StateDOWN:
		stateConnectedMessages(msg)
		break
	default:
	}
}
//====================================================================================
// STATE = INIT, nothing should be really happening here
//====================================================================================
func stateInitMessages(msg *tbMessages.TBmessage) {
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_CONNECTED:
	default:

	}
}

//====================================================================================
// STATE=CONNECTING to OFFICE MANAGER
//====================================================================================
func stateConnectingMessages(msg *tbMessages.TBmessage) {
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_HELLO:
		FSMSetState(StateCONNECTED)
		receiver := msg.MsgSender

		newMsg := tbMsgUtils.TBhelloReplyMsg(myFullName, receiver, "")

		fmt.Println(myName,": stateConnectingMessages: sendMsgOut ")
		tbMsgUtils.TBsendMsgOut(newMsg, receiver.Address,myConnection)
		fmt.Println(myName,": State=",myState," Send MSG to=", receiver)

	default:
	}

}
//====================================================================================
//
//====================================================================================
func stateConnectedMessages(msg *tbMessages.TBmessage) {
	messageType := msg.MsgType
	fmt.Println(myName,": state=CONNECTED - RCVD MSG TYPE=", messageType)
	switch messageType {
	case tbMessages.MSG_TYPE_KEEPALIVE:
		// GS set last hello received msg time to check within periodic timer
		// that Office Master is alive
		//fmt.Println(myName,":CONNECTED: KEEP ALIVE MESSAGE FROM OFFICE MASTER")
		receivedKeepAliveMsg(msg)
		myLastKeepAliveReceived = time.Now()
		tbUtils.SendRegisterMsg(myFullName, sliceOfMasters, myConnection)
		break
	case tbMessages.MSG_TYPE_EXPCREATE:
		fmt.Println("EXPERIMENT CREATE ")
		var swapin tbMessages.SwapIn
		tbJsonUtils.TBunmarshal(msg.MsgBody, &swapin)
		fmt.Println("EXPERIMENT CREATE - SEND MESSAGE TO EXP MASTER ")
		expMasterRecord, _ := tbUtils.LocateMaster(sliceOfMasters, "TB-EXPMASTER")
		if expMasterRecord != nil {
			msgMarshalled := tbMsgUtils.TBmarschalMessage(myFullName,expMasterRecord.Name,
							tbMessages.MSG_TYPE_HELLO, string(msg.MsgBody))
				tbMsgUtils.TBsendMsgOut(msgMarshalled, *officeMasterUdpAddress, myConnection)
		} else {
			// TODO RETUN MESSAGE AS UNDELIVERABLE
		}
		break
	case tbMessages.MSG_TYPE_SWAPIN:
		fmt.Println("EXPERIMENT SWAPIN ")
		var swapin tbMessages.SwapIn
		tbJsonUtils.TBunmarshal(msg.MsgBody, &swapin)
		// ExperimentStart(swapin.Project,swapin.Experiment,swapin.UserName)
		break
	case tbMessages.MSG_TYPE_SWAPOUT:
		var swapout tbMessages.SwapOut
		tbJsonUtils.TBunmarshal(msg.MsgBody, &swapout)
		fmt.Println("EXPERIMENT SWAPOUT ", swapout.Project,swapout.Experiment,swapout.UserName)
		break
	default:
		fmt.Println(myName,": state=CONNECTED: UNKNOWN MSG RECEIVED")
	}
}
//=======================================================================
//
//=======================================================================
func receivedKeepAliveMsg(msg *tbMessages.TBmessage) {
	var rcvdsliceOfMasters [] tbMessages.TBmgr
	tbJsonUtils.TBunmarshal(msg.MsgBody, &rcvdsliceOfMasters)
	var names = ""
	for masterIndex := range rcvdsliceOfMasters {
		receiver := rcvdsliceOfMasters[masterIndex].Name

		ip := rcvdsliceOfMasters[masterIndex].Name.Address.IP.String()
		port := rcvdsliceOfMasters[masterIndex].Name.Address.Port

		names += " " + receiver.Name + " at " + ip + ":" + strconv.Itoa(port)

	}
	fmt.Println(myName,": LEARNED MODULES=", names)

	// add sliceOfManagers, check if already there and update, otherwise append
	//existingMgr := TBlocateMaster(sliceOfMasters, theMgr.Name.Name)
	//if existingMgr != nil { // Update existing mgr/master record
	//	*existingMgr = theMgr
	//} else { // Add a new manager/master
	//	sliceOfMasters = append(sliceOfMasters, theMgr)
	//}
	//fmt.Println("New SLICE of Managers=", sliceOfMasters)
	//fmt.Println("LENGTH of sliceOfMasters=",len(sliceOfMasters))
}
//====================================================================================
//
//====================================================================================
func handleControlMessages(message []byte) {
	// Unmarshal
	var msg tbMessages.TBmessage
	tbJsonUtils.TBunmarshal(message, &msg)
	//fmt.Println(myName, "HandleControlMessages MSG(",unsafe.Sizeof(msg),")=", msg)
	fmt.Println(myName, ": HandleControlMessages MSG Type=",msg.MsgType)
	switch myState {
	case StateINIT:
		stateInitControlMessages(msg)
		break
	case StateCONNECTING:
		stateConnectingControlMessages(msg)
		break
	case StateCONNECTED:
		stateConnectedControlMessages(msg)
		break
	case StateUP:
		break
	case StateDOWN:
		break
	default:
	}
}
//====================================================================================
//
//====================================================================================
func stateInitControlMessages(msg tbMessages.TBmessage) {
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_CONNECTED:
		fmt.Println(myName, ": HMMM ... wrong msg in state ",myState)
		// FSMSetState(StateCONNECTED)
		// send helloMsg to remote server
		// ERROR
	default:
	}
}
//====================================================================================
//
//====================================================================================
func stateConnectedControlMessages(msg tbMessages.TBmessage) {
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_CONNECTED:
		FSMSetState(StateCONNECTED)
		tbUtils.SendRegisterMsg(myFullName, sliceOfMasters, myConnection)
		break

	default:
	}
}
//=======================================================================
// MAYBE LATER ....
//=======================================================================
func stateConnectingControlMessages(msg tbMessages.TBmessage) {
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_CONNECTED:
		FSMSetState(StateCONNECTED)
		// mySendChannel <- tbMsgUtils.TBhelloMsg(myFullName, officeMasterFullName, "ABCDEFG")
		newMsg := tbMsgUtils.TBhelloMsg(myFullName, officeMasterFullName, "ABCDEFG")
		fmt.Println(myName,": stateConnectingControlMessages: sendMsgOut ")
		tbMsgUtils.TBsendMsgOut(newMsg, *officeMasterUdpAddress, myConnection)
		break

	default:
	}

}

//====================================================================================
//
//====================================================================================
func FSMSetState(newState string) {
	fmt.Println(myName,": FSM - OldState=",myState, " NewState=", newState)
	myState = newState
}

//====================================================================================
// INIT:
// 0. Create send and receive channels:
//		- mySendChannel to send messages out
//		- myRecvChannel to receive messages from others
//      The following two will typically be used if we have children that need to 
//      communicate, otherwise unused:
//		- mySendControlChannel
//		- myRecvControlChannel to receive our own control messages
// 1. Set our own ip and udp address
// 2. Create UDP connection
// 3. Create our row entry for slice of masters
// 4. Start our receive thread
// 6. Find office Master's IP and UDP address (configuration)
//    If office Master is alive change state to StateCONNECTED
//    else set a timer and wait for the office Master to show up
//    When timer expires we will retry to connect to office Master, see periodicTimer
// myUdpName=TB-WWWMASTER:1200
//====================================================================================
func myInit() {
	var err error
	fmt.Println(myName,": Init at ", myCreationTime, " myUdpName=",myUdpName)
	fmt.Println(myName,": Init at ", myCreationTime, " myUdpNameWWW=",myUdpNameWWW)

	mysqlMasterIP = tbNetUtils.GetMastersIP(tbConfig.TBmysqlMasterName)

	fmt.Println("INIT TB-MYSQLMASTER is at ", mysqlMasterIP)

	// mySendControlChannel = make(chan []byte) // so that we can talk to sendThread
	myRecvControlChannel = make(chan []byte) // so that we can talk to recvThread
	// mySendChannel        = make(chan []byte) // so that we can talk to sendThread
	myRecvChannel        = make(chan []byte) //
	myControlChannel     = make(chan []byte) // so that all threads can talk to us
	myRecvChannelWWW     = make(chan []byte) //

	myIpAddress = tbNetUtils.GetLocalIp()
	myUdpAddress, err = net.ResolveUDPAddr("udp", myUdpName)
	if err != nil {
		fmt.Println(myName,": INIT - ResolveUDPAddr ERROR=",err)
	}

	fmt.Println(myName,": INIT - My Local IP=", myIpAddress, " My UDP address=", myUdpAddress)

	//myUdpAddress1 := new(net.UDPAddr)
	//myUdpAddress1 = &net.UDPAddr{Port:1200, IP:myIpAddress}
	//myUdpAddress = myUdpAddress1

	myFullName = tbMessages.NameId{Name: myName, OsId: os.Getpid(),
		TimeCreated: myCreationTime, Address: *myUdpAddress}

	fmt.Println(myName,": INIT - myFullName=", myFullName)
	// 2.
	myConnection, err = net.ListenUDP("udp", myUdpAddress) // from officeMgr
	if err != nil {
		myConnectionTimer = 5 // 3*5=15 sec, check periodic timer above
		return
	}
	
	// 3. Create our row entry for slice of masters
	myEntry := tbMessages.TBmgr{Name: myFullName, Up:true, LastChangeTime:myCreationTime,
		MsgsSent: 0, LastSentAt: "0", MsgsRcvd:0, LastRcvdAt:"0"}
	sliceOfMasters = append(sliceOfMasters, myEntry)
	// Optional: check that the record is there
	myRecord, _ :=  tbUtils.LocateMaster(sliceOfMasters, myName)
	if myRecord != nil {
		fmt.Println(myName,": ",myRecord.Name.Name, " ADDRESS:",myRecord.Name.Address,
			"CREATED:",myRecord.Name.TimeCreated, " MSGS_RCVD:",myRecord.MsgsRcvd)
	}
	
	// 4. Start our receive thread for Control Messages
	err2 := recvThread(myConnection, myRecvControlChannel)
	if err2 != nil {
		fmt.Println(myName, "INIT: Error creating Receive thread:", err2)
	}
	// 4. Start our WWW receive thread for Control Messages
	myUdpAddressWWW, err = net.ResolveUDPAddr("udp", myUdpNameWWW)
	myConnectionWWW, err = net.ListenUDP("udp", myUdpAddressWWW) // from officeMgr
	fmt.Println(myName,": INIT WWW - Local IP=", myIpAddress, " WWW UDP address=", myUdpAddressWWW)
	if err != nil {
		myConnectionTimer = 5 // 3*5=15 sec, check periodic timer above
		return
	}
	if err != nil {
		fmt.Println(myName,": INIT - ResolveUDPAddrWWW ERROR=",err)
	}
	err3 := recvThreadWWW(myConnectionWWW, myRecvControlChannel)
	if err3 != nil {
		fmt.Println(myName, "INIT: Error creating WWW Receive thread:", err3)
	}
	// 5. Set our state to StateCONNECTING
	FSMSetState(StateCONNECTING)

	// 6. Find office Master's IP and UDP address (configuration)
	officeMasterUdpAddress, officeMasterFullName, result :=
					tbUtils.LocateOfficeMaster(myName, sliceOfMasters )
	if result != true {
		fmt.Println(myName,": INIT - officeMaster Name=", officeMasterFullName,
												" Address=", officeMasterUdpAddress)
		FSMSetState(StateCONNECTED)
		tbUtils.SendRegisterMsg(myFullName, sliceOfMasters, myConnection)
		myLastKeepAliveReceived = time.Now()
	} else {
		myConnectionTimer = ConnectionTimer
	}
}


type App struct{}

func (s *App) Hi(name string, r *string) error {
	*r = fmt.Sprintf("Hello, %s!", name)
	return nil
}

func main1() {
	ln, err := net.Listen("tcp", ":6001")
	if err != nil {
		panic(err)
	}

	rpc.Register(new(App))

	for {
		conn, err := ln.Accept()
		if err != nil {
			continue
		}
		go rpc.ServeCodec(goridge.NewCodec(conn))
	}
}