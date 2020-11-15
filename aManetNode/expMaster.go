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
/* UDPDaytimeClient
make function allocates and initializes an object of type slice, map, or chan only.
Like new, the first argument is a type. But, it can also take a second argument, the size.
Unlike new, make’s return type is the same as the type of its argument, not a pointer to it.
And the allocated value is initialized (not set to zero value like in new).
The reason is that slice, map and chan are data structures.
They need to be initialized, otherwise they won't be usable.
This is the reason new() and make() need to be different.
p := new(chan int)   // p has type: *chan int
c := make(chan int)  // c has type: chan int
p *[]int = new([]int) // *p = nil, which makes p useless
v []int = make([]int, 100) // creates v structure that has pointer to an array,
            length field, and capacity field. So, v is immediately usable
 */
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
	"strconv"
	"time"
	// "os/exec"
	// "log"
	"testbedGS/common/tbDbaseUtils"
	//"database/sql"
	//"testbedGS/common/tbExpUtils"
	//"database/sql"
)

// EXPERIMENT MANAGER STATES
const STATE_INIT       = "INIT"
const STATE_CONNECTING = "CONNECTING"
const STATE_CONNECTED  = "CONNECTED"
const STATE_UP         = "UP"
const STATE_DOWN       = "DOWN"

var myName               = tbConfig.TBexpMgrName
var myFullName           tbMessages.NameId
var myUdpAddress         = new(net.UDPAddr)
var myIpAddress          = ""
var myConnection	*net.UDPConn = nil
var myState              =  STATE_INIT
var myCreationTime       = strconv.FormatInt(tbMsgUtils.TBtimestamp(),10)

var myRecvChannel        chan []byte = nil // To Receive messages from other modules
var myControlChannel     chan []byte = nil // so that all local threads can talk back
var mySendChannel        chan []byte = nil // To Send messages out to other modules
var mySendControlChannel chan []byte = nil // to send control msgs to Send Thread
var myRecvControlChannel chan []byte = nil // to send control msgs to Recv Thread
var myReceiveCount     = 0
var myConnectionTimer  = 0
var myLastKeepAliveReceived = time.Now()

var offMgrUdpAddress	= new(net.UDPAddr)
var offMgrFullName      tbMessages.NameId

var Log = tbLogUtils.LogInstance{}
var sliceOfMgrs [] tbMessages.TBmgr

//====================================================================================
//
//====================================================================================
func main() {

	Log.DebugLog   = true
	Log.WarningLog = true
	Log.ErrorLog   = true
	tbLogUtils.CreateLog(&Log, myName)
	Log.Warning(&Log,"this will be printed anyway")

	myInit()
	fmt.Println(myName,"MAIN: Starting a new ticker....")

	ticker := time.NewTicker(3 * time.Second)
	go func(){
		for t := range ticker.C {
			//Call the periodic function here.
			periodicFunc(t)
		}
	}()
//var msg tbMessages.TBmessage{}
    for {
        select {
        case msg1 := <-myRecvChannel:
	    	 //fmt.Println(myName, "MAIN: DATA MSG in state", myState, "MSG=",string(msg1))

	    	handleMessages(msg1)

		case msg3 := <-myControlChannel: // ???
			fmt.Println(myName, "MAIN: Control msg in state", myState, "MSG=",string(msg3))
			handleControlMessages(msg3)
        // default:
           // fmt.Println("done and exit select")    
        } // EndOfSelect
    }

	os.Exit(0)
}

//====================================================================================
//
//====================================================================================
func periodicFunc(tick time.Time){
	//fmt.Println("---->> EXP MASTER Tick")
	//fmt.Println("TICK: myConnectionTimer=",myConnectionTimer)
	if myConnectionTimer > 0 {
		myConnectionTimer--
		//fmt.Println("TEST: myConnectionTimer=",myConnectionTimer)
		if myConnectionTimer == 0 {
			if locateOfficeMgr() == true {
				fmt.Println(myName, "CONNECTED TO OFFICE MASTER")
				expSetState(STATE_CONNECTED)
				sendRegisterMsg()
				myLastKeepAliveReceived = time.Now()
			} else {
				fmt.Println("SET: myConnectionTimer=",myConnectionTimer)
				myConnectionTimer = 5 // 3*5=15 sec, check periodic timer above
			}
		}
	} else {
		currTime := time.Now()
		elapsedTime := currTime.Sub(myLastKeepAliveReceived)
		//fmt.Println("Elapsed time=", elapsedTime)
		if elapsedTime > (time.Second * 30) {
			if locateOfficeMgr() == true {
				expSetState(STATE_CONNECTED)
				sendRegisterMsg()
				myLastKeepAliveReceived = time.Now()
			} else {
				expSetState(STATE_CONNECTING)
				myConnectionTimer = 5 // 3*5=15 sec, check periodic timer above
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
				fmt.Println(myName, "SendThread: Sending MSG out to", offMgrUdpAddress)
                // _, err = connection.Write([]byte(msgOut))
				connection.WriteToUDP([]byte(msgOut), offMgrUdpAddress)
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

//====================================================================================
// recvThread() - Thread receiving messages from others
//====================================================================================
func recvThread(conn *net.UDPConn, recvChannel, recvControlChannel <-chan []byte) error {
	var err error = nil

	//fmt.Println(myName,"RecvThread: Start RECV THRED")
	go func() {
		connection := conn

		fmt.Println(myName,"RecvThread: Start Receiving")
		var controlMsg tbMessages.TBmessage
		var oobBuffer [3000] byte
		// Tell main we are coonected all is good
		// myControlChannel <- tbMsgUtils.TBConnectedMsg(myFullName, myFullName, "")

		for {
			recvBuffer := make([]byte, 3000)
			length, oobn, flags , addr , err  := connection.ReadMsgUDP(recvBuffer[0:], oobBuffer[0:])
			myReceiveCount++
			fmt.Println(myName,"\n============== Receive Count=",myReceiveCount,
				"\nRecvThread UDP MSG from",addr, "len=",length,"oobLen=", oobn,"flags=",flags,"ERR=",err)
			// fmt.Println(myName,"RecvThread MSG=", string(recvBuffer[0:length]))

			myRecvChannel <- recvBuffer[0:length]

			if len(recvControlChannel) != 0 {
				ctrlMsg := <- recvControlChannel
				tbJsonUtils.TBunmarshal(ctrlMsg, &controlMsg)
				fmt.Println("RecvThread got CONTROL MSG=",controlMsg)
				if strings.Contains(controlMsg.MsgType, "TERMINATE") {
					fmt.Println("RecvThread rcvd control MSG=", controlMsg)
					return
				}
			}
		}
	}()

	return err
}

//====================================================================================
//
//====================================================================================
func handleMessages(message []byte) {
	// Unmarshal
	//msg := new(tbMessages.TBmessage)

	//tbJsonUtils.TBunmarshal(message, &msg)
	// fmt.Println(myName,"HandleMessages MSG=", string(message),"Sizeof(msg)=",unsafe.Sizeof(msg))
	//fmt.Println(myName,":HandleMessages MSG Type:",msg.MsgType, " From:",msg.MsgSender.Name," To Me:",msg.MsgReceiver.Name)
	//fmt.Println(myName, ":HandleMessages MAIN: BODY=", msg.MsgBody)
	//fmt.Println(myName, ":HandleMessages MAIN: TIMESENT=", msg.TimeSent)

	switch myState {
	case STATE_INIT:
		stateInitMessages(message)
		break
	case STATE_CONNECTING:
		stateConnectingMessages(message)
		break
	case STATE_CONNECTED:
		stateConnectedMessages(message )
		break
	case STATE_UP:
	case STATE_DOWN:
		stateConnectedMessages(message)
		break
	default:
	}
}
//====================================================================================
// STATE = INIT, nothing should be really happening here
//====================================================================================
func stateInitMessages(message []byte) {
	msg := new(tbMessages.TBmessage)

	tbJsonUtils.TBunmarshal(message, &msg)
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_CONNECTED:
	default:

	}
}

//====================================================================================
// STATE=CONNECTING to OFFICE MANAGER
//====================================================================================
func stateConnectingMessages(message []byte) {
	msg := new(tbMessages.TBmessage)

	tbJsonUtils.TBunmarshal(message, &msg)
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_HELLO:
		expSetState(STATE_CONNECTED)
		receiver := msg.MsgSender
		// GS
		newMsg := tbMsgUtils.TBhelloReplyMsg(myFullName, receiver, "")

		// mySendChannel <- mymsg
		fmt.Println(myName,"stateConnectingMessages: sendMsgOut ")
		tbMsgUtils.TBsendMsgOut(newMsg, receiver.Address,myConnection)
		fmt.Println(myName,"State=",myState," Send MSG to=", receiver)

	default:
	}

}
//====================================================================================
//
//====================================================================================
func stateConnectedMessages(message []byte) {
	msg := new(tbMessages.TBmessage)
	tbJsonUtils.TBunmarshal(message, &msg)

	messageType := msg.MsgType
	// fmt.Println("RCVD MSG TYPE=", messageType)
	switch messageType {
	case tbMessages.MSG_TYPE_KEEPALIVE:
		// GS set last hello received msg time to check within periodic timer
		// that Office Master is alive
		fmt.Println("..... KEEP ALIVE MESSAGE FROM OFFICE MASTER")
		receivedKeepAliveMsg(msg)
		myLastKeepAliveReceived = time.Now()
		sendRegisterMsg()
		break
	case tbMessages.MSG_TYPE_SWAPIN:
		fmt.Println("EXPERIMENT SWAPIN ")
		var swapin tbMessages.SwapIn
		tbJsonUtils.TBunmarshal(msg.MsgBody, &swapin)
		ExperimentStart(swapin.Project,swapin.Experiment,swapin.UserName)
		break
	case tbMessages.MSG_TYPE_SWAPOUT:
		var swapout tbMessages.SwapOut
		tbJsonUtils.TBunmarshal(msg.MsgBody, &swapout)
		fmt.Println("EXPERIMENT SWAPOUT ", swapout.Project,swapout.Experiment,swapout.UserName)
		break
	case tbMessages.MSG_TYPE_EXPCREATE:
		msgS := new(tbMessages.TBmessageExpCreate)
		tbJsonUtils.TBunmarshal(message, &msgS)
		fmt.Println("EXPERIMENT CREATE MESSAGE FROM WEB MASTER =================================================")
		var expCreate tbMessages.ExpCreate
		//fmt.Println("EXPCREATE:", msgS )

		expCreate = msgS.MsgBody
		//fmt.Println("EXP CREATE expCreateStruct=", expCreate)
		fmt.Println("EXPERIMENT CREATE:User=", expCreate.UserName," PROJECT=", expCreate.Project,
							" EXP=",expCreate.Experiment, " FILE=", expCreate.FileName)
		// fmt.Println("EXPERIMENT CALL  func handleCreate() ================")
		handleCreate(expCreate.UserName,  expCreate.Project, expCreate.Experiment, "/root/" +expCreate.FileName)
		fmt.Println("EXPERIMENT CREATION COMPLETED")
		// TODO CALL EXPERIMENT CREATE
	default:
	}
}
//====================================================================================
// 
//====================================================================================
func handleControlMessages(message []byte) {
	// Unmarshal
	var msg tbMessages.TBmessage
	tbJsonUtils.TBunmarshal(message, &msg)
	//fmt.Println(myName, "HandleControlMessages MSG(",unsafe.Sizeof(msg),")=", msg)
	fmt.Println(myName, "HandleControlMessages MSG Type=",msg.MsgType)
	switch myState {
	case STATE_INIT:
		stateInitControlMessages(msg)
		break
	case STATE_CONNECTING:
		break
	case STATE_CONNECTED:
		stateConnectedControlMessages(msg)
		break
	case STATE_UP:
		break
	case STATE_DOWN:
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
		fmt.Println(myName, "HMMM ... wrong msg in state",myState)
		// expSetState(STATE_CONNECTED)
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
		expSetState(STATE_CONNECTED)
		sendRegisterMsg()
		break

	default:
	}
}
//=======================================================================
//
//=======================================================================
func receivedKeepAliveMsg(msg *tbMessages.TBmessage) {
	var rcvdSliceOfMgrs [] tbMessages.TBmgr
	tbJsonUtils.TBunmarshal(msg.MsgBody, &rcvdSliceOfMgrs)
	var names = ""
	for mgrIndex := range rcvdSliceOfMgrs {
		receiver := rcvdSliceOfMgrs[mgrIndex].Name

		ip := rcvdSliceOfMgrs[mgrIndex].Name.Address.IP.String()
		port := rcvdSliceOfMgrs[mgrIndex].Name.Address.Port

		names += " " + receiver.Name + " at " + ip + ":" + strconv.Itoa(port)

	}
	fmt.Println(myName,": LEARNED MODULES=", names)

	// add sliceOfManagers, check if already there and update, otherwise append
	//existingMgr := TBlocateMngr(sliceOfMgrs, theMgr.Name.Name)
	//if existingMgr != nil { // Update existing mgr/master record
	//	*existingMgr = theMgr
	//} else { // Add a new manager/master
	//	sliceOfMgrs = append(sliceOfMgrs, theMgr)
	//}
	//fmt.Println("New SLICE of Managers=", sliceOfMgrs)
	//fmt.Println("LENGTH of sliceOfMgrs=",len(sliceOfMgrs))
}
//=======================================================================
//
//=======================================================================
func stateConnectingControlMessages(msg tbMessages.TBmessage) {
	messageType := msg.MsgType
	switch messageType {
	case tbMessages.MSG_TYPE_CONNECTED:
		expSetState(STATE_CONNECTED)
		// mySendChannel <- tbMsgUtils.TBhelloMsg(myFullName, offMgrFullName, "ABCDEFG")
		newMsg := tbMsgUtils.TBhelloMsg(myFullName, offMgrFullName, "ABCDEFG")
		fmt.Println(myName,"stateConnectingControlMessages: sendMsgOut ")
		tbMsgUtils.TBsendMsgOut(newMsg, *offMgrUdpAddress, myConnection)
		break

	default:
	}

}

//====================================================================================
//
//====================================================================================
func expSetState(newState string) {
	fmt.Println(myName,"OldState=",myState, " NewState=", newState)
	myState = newState
}

//====================================================================================
//
//====================================================================================
func myInit() {
	var err error
	fmt.Println(myName,"INIT: expMgr Init at ", myCreationTime)

	mySendControlChannel = make(chan []byte) // so that we can talk to sendThread
	myRecvControlChannel = make(chan []byte) // so that we can talk to recvThread
	mySendChannel        = make(chan []byte) // so that we can talk to sendThread
	myRecvChannel        = make(chan []byte) //
	myControlChannel     = make(chan []byte) // so that all threads can talk to us

	myUdpAddress, _ = net.ResolveUDPAddr("udp", tbConfig.TBexpMgr)
	myIpAddress = tbNetUtils.GetLocalIp()
	fmt.Println(myName,"INIT: My Local IP=", myIpAddress, " My UDP address=", myUdpAddress)

	myFullName = tbMessages.NameId{Name: tbConfig.TBexpMgrName, OsId: os.Getpid(),
		TimeCreated: myCreationTime, Address: *myUdpAddress}
	fmt.Println(myName,"INIT: myFullName=", myFullName)

	myConnection, err = net.ListenUDP("udp", myUdpAddress) // from officeMgr
	checkError(err)

	myEntry := tbMessages.TBmgr{Name: myFullName, Up:true, LastChangeTime:myCreationTime,
		MsgsSent: 0, LastSentAt: "0", MsgsRcvd:0, LastRcvdAt:"0"}
	sliceOfMgrs = append(sliceOfMgrs, myEntry)
	// Optional: check that the record is there
	theMgr :=  locateMngr(sliceOfMgrs, tbConfig.TBexpMgrName)
	if theMgr != nil {
		fmt.Println("MGR:",theMgr.Name.Name, "ADDRESS:",theMgr.Name.Address,
			"CREATED:",theMgr.Name.TimeCreated, "MSGSRCVD:",theMgr.MsgsRcvd)
	}
	//err1 := sendThread(myConnection, mySendChannel, mySendControlChannel)
	//if err1 != nil {
	//	fmt.Println(myName,"INIT: Error creating send thread")
	//}
	err2 := recvThread(myConnection, myRecvChannel, myRecvControlChannel)
	if err2 != nil {
		fmt.Println(myName, "INIT: Error creating Receive thread")
	}

	expSetState(STATE_CONNECTING)

	if locateOfficeMgr() == true {
		expSetState(STATE_CONNECTED)
		sendRegisterMsg()
		myLastKeepAliveReceived = time.Now()
	} else {
		myConnectionTimer = 5 // 3*5=15 sec, check periodic timer above
	}
	//fmt.Println(myName, "++++++++++++++++++++++++++++++++++")
	//fmt.Println(myName, "++++++++++++++++++++++++++++++++++")
	//fmt.Println(myName, "START HANDLE CREATE")

	// TODO ENABLE thru regular path handleCreate()
	//handleCreate("scuric", "DeterTest", "test3", "/tmp/test1.xml")
}

func handleCreate(thisuser, thispid, thiseid, thisfile string) {
	fmt.Println("handleCreate", "START handleCreate ===  connect to: ", tbConfig.TBmysqlServer)//, args)
	myDb, err := tbDbaseUtils.DBopenMysql("handleCreate","root",
		"", "tbdb" ,tbConfig.TBmysqlServer)
	if err != nil {
		fmt.Println("handleCreate", "ERROR Opening DB:", err)
	} else {
		fmt.Println("handleCreate", "Opening mysql database OK")
	}

	// defer myDb.DbaseConnection.Close()
	// tbDbaseUtils.DbuseDB ("handleCreate", myDb.DbaseConnection, "tbdb")
	// tbDbaseUtils.TbDbPing("handleCreate", myDb.DbaseConnection)
	// tbDbaseUtils.DBshowDatabases("handleCreate", myDb.DbaseConnection)

	var args   *map[string]string
	var expEnv *map[string]string
	args, expEnv = expEnvExpArgsMap(myDb.DbaseConnection, thisuser, thispid,
		thiseid, thispid, thispid + "/" + thiseid + "  Experiment Description")

	fmt.Println(myName, "START expBAtchExp")//, args)

	exp, myError := expBatchExp(myDb.DbaseConnection, "scuric", "DeterTest",
		thiseid, "tmp/" + thisfile, *args, *expEnv)
	if myError != "" {
		fmt.Println(handleCreate, "ERROR PROCESSING expBatchExp: ", myError)
	} else {
		fmt.Println(handleCreate, "Successfully finished ============================= ", exp)
	}
	myDb.DbaseConnection.Close()
}

//====================================================================================
//
//====================================================================================
func locateOfficeMgr() bool {
	// NOTE that this will fail if offMgrUdpAddress has not been initialized due
	// to Office Manager not being up . Add state and try again to Resolve before
	// doing this
	var err error
	//fmt.Println("Locate Office Manager")
	offMgrUdpAddress, err = net.ResolveUDPAddr("udp", tbConfig.TBofficeMgr)
	if err != nil {
		fmt.Println("ERROR in net.ResolveUDPAddr = ", err)
		fmt.Println("ERROR locating Office Manager, will retry")
		return false
	}

	offMgrFullName = tbMessages.NameId{Name: tbConfig.TBofficeMgrName, OsId: 0,
		TimeCreated: "0", Address: *offMgrUdpAddress}
	fmt.Println(myName,"INIT: offMgrFullName=", offMgrFullName)
	entry2 := tbMessages.TBmgr{Name: offMgrFullName, Up:true, LastChangeTime:"0",
		MsgsSent: 0, LastSentAt: "0", MsgsRcvd:0, LastRcvdAt:"0"}
	sliceOfMgrs = append(sliceOfMgrs, entry2)
	// fmt.Println("New SLICE of Managers=", sliceOfMgrs)

	theMgr:=  locateMngr(sliceOfMgrs, tbConfig.TBofficeMgrName)
	if theMgr != nil {
		fmt.Println("MGR:",theMgr.Name.Name, "ADDRESS:",theMgr.Name.Address,
			"CREATED:",theMgr.Name.TimeCreated, "MSGSRCVD:",theMgr.MsgsRcvd)
	}
	return true
}
//====================================================================================
//
//====================================================================================
func formatReceiver(name string, osId int, udpAddress net.UDPAddr) tbMessages.NameId {
	receiver := tbMessages.NameId{Name: name, OsId: osId,
		TimeCreated: "", Address: udpAddress}
	return receiver
}

//====================================================================================
// Save the pointer to my own row for faster handling
//====================================================================================
func sendRegisterMsg() {
	theMgr := locateMngr(sliceOfMgrs, tbConfig.TBexpMgrName)
	msgBody, _ := tbJsonUtils.TBmarshal(theMgr)
	if theMgr != nil {
		theMgr.LastSentAt = strconv.FormatInt(tbMsgUtils.TBtimestamp(),10)
		newMsg := tbMsgUtils.TBregisterMsg(myFullName, offMgrFullName, string(msgBody))
		// fmt.Println(myName, "stateConnected REGISTER with offMgr ")
		tbMsgUtils.TBsendMsgOut(newMsg, *offMgrUdpAddress, myConnection)
	} else {
		fmt.Println("FAILED to locate mngr record in the slice")
	}
}
//====================================================================================
//
//====================================================================================
func sendHelloReplyMsg(msg *tbMessages.TBmessage) {
	receiver := msg.MsgSender
	newMsg := tbMsgUtils.TBhelloMsg(myFullName, receiver, "ABCDEFG")
	tbMsgUtils.TBsendMsgOut(newMsg, receiver.Address, myConnection)
}
//====================================================================================
//
//====================================================================================
func checkError(err error) {
    if err != nil {
        fmt.Fprintf(os.Stderr, "Fatal error %s", err.Error())
        os.Exit(1)
    }
	//time.Sleep(time.Millisecond * 3000)
}
//============================================================================
// Locate specific row in the slice of all managers, containing rows for
// all known managers including ourselves and the office manager
// Return nil if row not found
//============================================================================
func locateMngr(slice [] tbMessages.TBmgr, mngr string) *tbMessages.TBmgr{
	for index := range slice {
		if  slice[index].Name.Name == mngr {
			return &slice[index]
		}
	}

	return nil
}
