//*********************************************************************************/
// Copyright 2017 www.igismo.com.  All rights reserved. See open source license
// HISTORY:
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net  by igismo
// Goran Scuric		 2.0  09012019  Adapted for bifrost project
// Goran Scuric      3.0  12012020  Adapted for AeroMesh Space Mesh Networking
//=================================================================================
// COMMAND LINE OPTIONS:
// FORMAT: sudo ./drone  [DroneName DroneId [myIP [myPort [groundIP [groundPort]]]]]
// use 0 anywhere for default
// EXAMPLE1: sudo ./aeroMeshNode DroneX 01 192.168.22.2 1201  192.168.1.1 1200
//=================================================================================
// SYNAPSE VERSION
//================================================================================
package main

import (
	"fmt"
	"github.com/spf13/viper"
	"net"
	"os"
	"os/exec"
	//"reflect"
	"runtime"
	"strconv"
	"strings"
	"synapse/common"
	"time"
	//"builtin"
)

const DEBUG_ROLE = 0
const DEBUG_DISCOVERY = 0

var Drone common.M3Info // all info about the Node
var Me *common.TerminalInfo = &Drone.Terminal[0]
var Log = common.LogInstance{} // for log file storage

// DRONE CONNECTIVITY STATES  ... for now
const StateAlone = "ALONE"
const StateConnecting = "CONNECTING"
const StateConnected = "CONNECTED"

// Command from local terminal, or rcvd from ground controller
const LOCAL_CMD = "LOCAL"
const REMOTE_CMD = "REMOTE"
const STEPMODE_DISABLED = -1

//====================================================================================
// MAIN: Need to set GROUND address correctly later, at the moment
// Terminal IP = local address, and then groundIP = drone ip
// Note that these are relevant structures:
// - structure TerminalInfo - one per this drone, top level structure
// 		- structure array NodeInfo[64] - one struct for each drone in the group
// - KnownTerminals []NodeInfo - array list of known/learned drones
//		- could be integrated with the one above
// - ME points to our own NodeInfo[my drone id]
//====================================================================================
//====================================================================================
// InitDroneFromConfig()  READ ARGUMENTS IF ANY
//====================================================================================
func InitDroneConfiguration() {

	Me.TerminalName = "Node01" // will be overwritten by config
	Me.TerminalId = 1          // will be overwritten by config
	Me.TerminalIP = ""
	Me.TerminalConnectionTimer = common.DRONE_KEEPALIVE_TIMER // 5 sec
	Me.TerminalKeepAliveRecvTime = time.Now()
	// Drone.KnownDrones 			= nil // Array of drones and ground stations learned
	Drone.Channels.CmdChannel = nil            // so that all local threads can talk back
	Drone.Channels.UnicastRcvCtrlChannel = nil // to send control msgs to Recv Thread
	Drone.Channels.BroadcastRcvCtrlChannel = nil
	Drone.Channels.MulticastRcvCtrlChannel = nil

	Me.TerminalConnection = nil
	Me.TerminalActive = true
	Me.TerminalDiscoveryTimeout = 5000 // milli sec
	Me.TerminalReceiveCount = 0
	Me.TerminalSendCount = 0
	Log.DebugLog = true
	Log.WarningLog = true
	Log.ErrorLog = true

	Drone.Channels.UnicastRcvCtrlChannel = make(chan []byte) //
	Drone.Channels.BroadcastRcvCtrlChannel = make(chan []byte)
	Drone.Channels.MulticastRcvCtrlChannel = make(chan []byte)
	Drone.Channels.CmdChannel = make(chan []byte) // receive command line cmnds

	Drone.Connectivity.BroadcastRxAddress = ":9999"
	Drone.Connectivity.BroadcastRxPort = "9999"
	Drone.Connectivity.BroadcastRxIP = ""
	Drone.Connectivity.BroadcastTxPort = "8888"
	Drone.Connectivity.BroadcastConnection = nil
	Drone.Connectivity.BroadcastTxStruct = new(net.UDPAddr)

	Drone.Connectivity.UnicastRxAddress = ":8888"
	Drone.Connectivity.UnicastRxPort = "8888"
	Drone.Connectivity.UnicastRxIP = ""
	Drone.Connectivity.UnicastTxPort = "8888"
	Drone.Connectivity.UnicastRxConnection = nil
	Drone.Connectivity.UnicastRxStruct = nil
	Drone.Connectivity.UnicastTxStruct = new(net.UDPAddr)

	Drone.GroundIsKnown = false
	Drone.GroundUdpPort = 0
	Drone.GroundIP = ""
	Me.TerminalPort = "8888"
	Drone.GroundIPandPort = "" //Drone.GroundIP + ":" + Drone.GroundUdpPort
	Me.TerminalUdpAddrStructure = new(net.UDPAddr)
	Drone.GroundUdpAddrSTR = new(net.UDPAddr)
	Me.TerminalTimeCreated = time.Now() // strconv.FormatInt(common.TBtimestampNano(), 10)
}

//====================================================================================
// InitFromConfigFile() - Set configuration from config file
//====================================================================================
func InitFromConfigFile() {
	var fileName string
	argNum := len(os.Args) // Number of arguments supplied, including the command
	fmt.Println("Number of Arguments = ", argNum)
	if argNum > 2 && os.Args[1] != "" && os.Args[1] != "0" {
		Me.TerminalName = os.Args[1]
		Me.TerminalId, _ = strconv.Atoi(os.Args[2])
		fileName = "config" + os.Args[2] + ".yml"
		viper.SetConfigName(fileName)
	} else {
		// Set the file name of the configurations file
		fileName = "config.yml"
		viper.SetConfigName(fileName)
	}
	// Set the path to look for the configurations file
	viper.AddConfigPath(".")
	// Enable VIPER to read Environment Variables
	viper.AutomaticEnv()
	viper.SetConfigType("yml")
	// var droneStruct DroneInfo
	if err := viper.ReadInConfig(); err != nil {
		fmt.Printf("Error reading config file, %s", err)
		return
	}

	// Set undefined variables
	viper.SetDefault("DroneName", "Drone1")
	// store configuration into the drone structure
	err := viper.Unmarshal(&Drone) //&droneStruct)
	if err != nil {
		fmt.Printf("Unable to decode into struct, %v", err)
	}

	fmt.Println("Reading variables from ... config", fileName)
	fmt.Println("*************************************************************")
	fmt.Println("TerminalId                   = ", Me.TerminalId)
	fmt.Println("TerminalName                 = ", Me.TerminalName)
	fmt.Println("TerminalIP            = ", Me.TerminalIP)
	fmt.Println("TerminalLogPath              = ", Me.TerminalLogPath)
	fmt.Println("TerminalConnectionTimer            = ", Me.TerminalConnectionTimer)
	fmt.Println("TerminalPort              = ", Me.TerminalPort)

	fmt.Println("BroadcastRxIP             = ", Drone.Connectivity.BroadcastRxIP)
	fmt.Println("BroadcastRxPort           = ", Drone.Connectivity.BroadcastRxPort)
	Drone.Connectivity.BroadcastRxAddress =
		Drone.Connectivity.BroadcastRxIP + ":" + Drone.Connectivity.BroadcastRxPort
	fmt.Println("BroadcastRxAddress        = ", Drone.Connectivity.BroadcastRxAddress)

	fmt.Println("UnicastRxIP             = ", Drone.Connectivity.UnicastRxIP)
	fmt.Println("UnicastRxPort           = ", Drone.Connectivity.UnicastRxPort)
	Drone.Connectivity.UnicastRxAddress =
		Drone.Connectivity.UnicastRxIP + ":" + Drone.Connectivity.UnicastRxPort
	fmt.Println("UnicastRxAddress        = ", Drone.Connectivity.UnicastRxAddress)

	fmt.Println("BroadcastTxIP             = ", Drone.Connectivity.BroadcastTxIP)
	fmt.Println("BroadcastTxPort          = ", Drone.Connectivity.BroadcastTxPort)
	Drone.Connectivity.BroadcastTxAddress =
		Drone.Connectivity.BroadcastTxIP + ":" + Drone.Connectivity.BroadcastTxPort
	fmt.Println("BroadcastTxAddress        = ", Drone.Connectivity.BroadcastTxAddress)

	fmt.Println("GroundId                  = ", Drone.GroundIP)
	fmt.Println("GroundUdpPort             = ", Drone.GroundUdpPort)
	fmt.Println("GroundIPandPort           = ", Drone.GroundIPandPort)
}

//====================================================================================
// InitFromCommandLine()  READ ARGUMENTS IF ANY
//====================================================================================
func InitFromCommandLine() {
	// argsWithProg := os.Args; argsWithoutProg := os.Args[1:]
	// Second: check for the command line parametersz, they overwrite the config file
	for index := range os.Args {
		arg := os.Args[index]
		fmt.Println("Arg", index, "=", arg)
	}
	argNum := len(os.Args) // Number of arguments supplied, including the command
	fmt.Println("Number of Arguments = ", argNum)
	if argNum > 2 && os.Args[1] != "" && os.Args[1] != "0" {
		fmt.Println("Drone NAME = ", os.Args[1])
		Me.TerminalName = os.Args[1]
		Me.TerminalId, _ = strconv.Atoi(os.Args[2])
	}
	if argNum > 3 && os.Args[3] != "" && os.Args[3] != "0" {
		fmt.Println("Satellite IP   = ", os.Args[3])
		Me.TerminalIP = os.Args[3]
		// TODO: Set eth0 IP address to Drone.DroneIpAddress !!!
		var ifCmd = exec.Command("sudo", "ifconfig", "eth0",
			Me.TerminalIP, "up", "", "")
		output, err := ifCmd.Output()
		fmt.Println("SET MY IP=", "sudo", "ifconfig", "eth0", Me.TerminalIP, "up",
			" -OUTPUT:", string(output), " ERR:", err)
	}
	if argNum > 4 && os.Args[4] != "" && os.Args[4] != "0" {
		Me.TerminalPort = os.Args[4] // strconv.ParseInt(os.Args[3], 10, 64)
	}
	if argNum > 5 && os.Args[5] != "" && os.Args[5] != "0" && argNum > 6 && os.Args[6] != "" && os.Args[6] != "0" {
		Drone.GroundIPandPort = os.Args[5] + ":" + os.Args[6]
	}
}

//====================================================================================
//  Initialize my own info in the Drone structure
//====================================================================================
func SetFinalDroneInfo() {

	if Me.TerminalIP == "" {
		Me.TerminalIP, Me.TerminalMac = common.GetLocalIp() // get IP and MAC
	}

	// TODO memset(&distanceVector, 0, sizeof(distanceVector))
	Me.TerminalLastChangeTime = float64(common.TBtimestampNano()) // time.Now().String()
	Me.TerminalActive = true
	Me.TerminalState = StateAlone

	var bit uint64 = 0x1
	for j := 0; j < 64; j++ {
		// TODO .. from prev coded, should be just = 0xffffffffffffffff
		bit <<= 1
	}

	for m2Number := 0; m2Number < 4; m2Number++ {
		Drone.M2Terminal[m2Number].TerminalMsgLastRcvdAt = time.Now() // TBtimestampNano() //time.Now()
	}
}

//====================================================================================
//  Initialize IP and UDP addressing
//====================================================================================
func InitDroneConnectivity() {
	Me.TerminalIPandPort = Me.TerminalIP + ":" + Me.TerminalPort
	var err3 error
	Me.TerminalUdpAddrStructure, err3 = net.ResolveUDPAddr("udp", Me.TerminalIPandPort)
	if err3 != nil {
		fmt.Println("ERROR ResolveUDPAddr: ", err3)
	} else {
		Me.TerminalFullName = common.NameId{Name: Me.TerminalName,
			Address: *Me.TerminalUdpAddrStructure}
	}
}

//====================================================================================
// Check if any err, and exit
//====================================================================================
func checkErrorNode(err error) {
	if err != nil {
		_, _ = fmt.Fprintf(os.Stderr, "Fatal error %s", err.Error())
		os.Exit(1)
	}
}

//====================================================================================
//
//====================================================================================
func periodicFunc(tick time.Time) {
	// TODO - figure out reasonable timer period to process these two
	// Note that we may have not receive messages from some drones for some time
	// So maybe wait until the end of processing period and figure out who we
	// received discovery msgs from and based on that figure out the connectivity

	fmt.Println("TICK: UPDATE CONNECTIVITY -----  ", tick)

	currTimeMilliSec := common.TBtimestampMilli()

	for i := 1; i < 5; i++ {
		if Drone.Terminal[i].TerminalActive == true {
			elapsedTimeSinceLastSend := currTimeMilliSec - Drone.Terminal[i].TerminalLastHelloSendTime
			timeSinceLastHelloReceived := currTimeMilliSec - Drone.Terminal[i].TerminalLastHelloReceiveTime

			if timeSinceLastHelloReceived > Me.TerminalHelloTimerLength {
				Drone.Terminal[i].TerminalActive = false
			} else if elapsedTimeSinceLastSend >= Me.TerminalHelloTimerLength {
				Drone.Terminal[i].TerminalLastHelloSendTime = currTimeMilliSec
				// send hello
			}
		}
	}
}

//===============================================================================
// Drone == Satellite, really M3
//===============================================================================
func main() {
	myOS := runtime.GOOS
	fmt.Println("========= DRONE START on ", myOS, " at ", time.Now(), "==========")
	//===============================================================================
	// UPDATE RELEVANT VARIABLES and structures in proper order
	//===============================================================================
	InitDroneConfiguration()
	// Then try to read config file
	InitFromConfigFile()
	// Finally overwrite if any command arguments given
	InitFromCommandLine()

	Drone.Terminal[0].TerminalActive = true  // M3, not really neccessary
	Drone.Terminal[1].TerminalActive = false // M1
	Drone.Terminal[2].TerminalActive = false // M2 1
	Drone.Terminal[3].TerminalActive = false // M2 2
	Drone.Terminal[4].TerminalActive = false // M2 3
	Drone.Terminal[5].TerminalActive = false // M2 4

	SetFinalDroneInfo()
	// Create LOG file
	common.CreateLog(&Log, Me.TerminalName, Me.TerminalLogPath)
	Log.Warning(&Log, "Warning test:this will be printed anyway")

	InitDroneConnectivity()

	// Make this work one of these days ...
	var err error
	checkErrorNode(err)

	common.ControlPlaneInit(Drone.Connectivity, Drone.Channels)

	// START SEND AND RECEIVE THREADS:
	err2 := common.ControlPlaneRecvThread(Drone.Connectivity, Drone.Channels)
	if err2 != nil {
		fmt.Println(Me.TerminalName, "INIT: Error creating Broadcast/Unicast RX thread")
		panic(err2)
	}

	// TODO: Make this work later
	//if Drone.GroundIsKnown == true {
	//	fmt.Println(Me.Name, ":",Me.FullName, "INIT: GROUND LOCATED")
	//	changeState(StateConnected)
	//	Drone.KeepAliveRcvdTime = time.Now()
	//}

	//================================================================================
	// START TIMER : Call periodicFunc on every timerTick
	//================================================================================
	tick := 300 * time.Millisecond
	fmt.Println(Me.TerminalName, "MAIN: Starting a new Timer Ticker at ", tick, " msec")
	ticker := time.NewTicker(tick)

	go func() {
		for t := range ticker.C {
			//Call the periodic function here.
			periodicFunc(t)
		}
	}()

	//================================================================================
	// START CONSOLE:
	//================================================================================
	StartConsole(ConsoleInput)

	//================================================================================
	// RECEIVE AND PROCESS MESSAGES: Control Plane msgs, and Commands from console
	// Note that this software is implemented as FSM with run to completion
	//================================================================================
	for {
		select {
		case UnicastMsg := <-Drone.Channels.UnicastRcvCtrlChannel:
			fmt.Println(Me.TerminalName, "MAIN: Unicast MSG in state", Me.TerminalState, "MSG=", string(UnicastMsg))
			// these include text messages from the ground/controller
			ControlPlaneMessages(UnicastMsg)
		case BroadcastMsg := <-Drone.Channels.BroadcastRcvCtrlChannel:
			//fmt.Println(Drone.DroneName, "MAIN: Broadcast MSG in state", Drone.DroneState, "MSG=",string(BroadcastMsg))
			// these include text messages from the ground/controller
			ControlPlaneMessages(BroadcastMsg)
		case MulticastMsg := <-Drone.Channels.MulticastRcvCtrlChannel:
			//fmt.Println(Drone.DroneName, "MAIN: Multicast MSG in state", Drone.DroneState, "MSG=",string(MulticastMsg))
			// these include text messages from the ground/controller
			ControlPlaneMessages(MulticastMsg)
		case CmdText, ok := <-ConsoleInput: // These are messsages from local drone console
			if !ok {
				fmt.Println("ERROR Reading input from stdin:", CmdText)
				break
			} else {
				fmt.Println("Read input from stdin:", CmdText)
				LocalCommandMessages(CmdText)
				// SendTextMsg(stdin)
				// fmt.Println("Console input sent to ground");
			}
			//default:
			//	fmt.Println("done and exit select")
		} // EndOfSelect
	} // EndOfFOR
	common.ControlPlaneCloseConnections(Drone.Connectivity)
	// os.Exit(0)
}

//====================================================================================
// ControlPlaneMessages() - handle Control Plane messages
//====================================================================================
func ControlPlaneMessages(message []byte) {
	msg := new(common.Msg)
	err1 := common.TBunmarshal(message, &msg)
	if err1 != nil {
		println("Error unmarshalling message: ", msg.MsgHeader.MsgCode)
		return
	}
	msgHeader := &msg.MsgHeader
	sender := msgHeader.SrcId
	//msglen := len(message)
	fmt.Println("ControlPlaneMessages: Msg=", msg) //.MsgCode, " msglen=", msglen)

	// Was this msg originated by us ?
	if strings.Contains(msgHeader.SrcIP, Me.TerminalIP) && sender == Me.TerminalId {
		// println("My own message: MsgCode=", msgHeader.MsgCode, " Me.NodeId=", Me.NodeId)
		return
	}
	//============================================================================
	// Is the other side within the RF range ?
	// we need to do this for ethernet connectivity as we receive everything
	//============================================================================
	// First check that the senders id is in valid range
	if sender == Me.TerminalId || sender < 1 || sender > common.MAX_NODES {
		println("Sender id WRONG: ", sender, " MsgCode=", msgHeader.MsgCode)
		return
	}
	//node 	:= &Drone.NodeList[sender -1]
	fmt.Println("CHECK MESSAGE CODE ", msgHeader.MsgCode)
	switch msgHeader.MsgCode {
	case common.MSG_TYPE_DISCOVERY: // from another drone
		var discoveryMsg = new(common.MsgCodeDiscovery)
		err := common.TBunmarshal(message, discoveryMsg) //message
		if err != nil {
			println("ControlPlaneMessages: ERR=", err)
			return
		}
		ControlPlaneProcessDiscoveryMessage(msgHeader, &discoveryMsg.MsgDiscovery)
		break
	case common.MSG_TYPE_GROUND_INFO: // info from ground
		// TODO: will require some rethinking how to handle
		// TODO: may need to rebroadcast for nodes that aare out of range
		// Note that in order to cure the situation where a node might have been out of reach
		// at the time the STEP message was sent, GROUND will insert the latest value for
		//the StepMode in all GROUNDINFO messages .... but we need to process those ...
		handleGroundInfoMsg(msgHeader, message)
		break
	case common.MSG_TYPE_DRONE_MOVE: // command from ground
		break
	case common.MSG_TYPE_STATUS_REQ: // command from ground
		handleGroundStatusRequest(msgHeader, message)
		break
	case common.MSG_TYPE_STEP: // from ground
		handleGroundStepMsg(msgHeader, message)
		break
	case "UPDATE":
		break
	default:
		fmt.Println("ControlPlaneMessages:  UNKNOWN Message")
		break
	}
}

//====================================================================================
// ControlPlaneMessage STEP
//====================================================================================
func handleGroundStepMsg(msgHeader *common.MessageHeader, message []byte) {
	/*
		var stepMsg = new(MsgCodeStep)
		err := TBunmarshal(message, stepMsg) //message
		if err != nil {
			println("ControlPlaneMessages STEP: ERR=", err)
			return
		}
		println("STEP MESSAGE step=", stepMsg.MsgStep.Steps)
		Drone.StepMode = stepMsg.MsgStep.Steps
	*/
}

//====================================================================================
// ControlPlaneMessage STATUS REQ
//====================================================================================
func handleGroundStatusRequest(msgHeader *common.MessageHeader, message []byte) {
	//fmt.Println("...... STATUS REQUEST MESSAGE ................")
	fmt.Println("...... STATUS REQUEST: srcIP=", msgHeader.SrcIP, " SrcMAC=", msgHeader.SrcMAC,
		" DstID=", msgHeader.DstId, " SrcID=", msgHeader.SrcId)

	// REPLY
	sendUnicastStatusReplyPacket(msgHeader)
}

//====================================================================================
// ControlPlaneMessage   GROUNDINFO
//====================================================================================
func handleGroundInfoMsg(msgHeader *common.MessageHeader, message []byte) {
	var err error
	// TODO  ... add to msg the playing field size .... hmmm ?? relation to random etc
	Drone.GroundFullName.Name = msgHeader.SrcName //.DstName
	Drone.GroundIP = msgHeader.SrcIP
	Drone.GroundIPandPort = string(msgHeader.SrcIP) + ":" + msgHeader.SrcPort
	myPort, _ := strconv.Atoi(msgHeader.SrcPort)
	Drone.GroundUdpPort = myPort
	Drone.GroundIsKnown = true //msg.GroundUp

	// Drone.GroundUdpAddrSTR.IP = msg.TxIP;
	//fmt.Println(TERMCOLOR, "... GROUNDINFO: Name=", Drone.GroundFullName.Name,
	//	"  IP:Port=", Drone.GroundIPandPort, " StepMode=", msgHeader.StepMode)
	// fmt.Println("Port=", msg.TxPort, " txIP=", msg.TxIP, " " +
	//	"groundIP=",Drone.GroundIP)

	Drone.GroundUdpAddrSTR, err = net.ResolveUDPAddr("udp", Drone.GroundIPandPort)
	//Drone.GroundUdpAddrSTR.Port = msg.TxPort
	//Drone.GroundUdpAddrSTR.IP   = net.IP(msg.TxIP)
	//Drone.GroundUdpAddrSTR.IP 			 = net.IP((Drone.GroundIP))
	//Drone.GroundUdpAddrSTR.Port, _ 	 = strconv.Atoi(Drone.GroundUdpPort)
	//Drone.GroundUdpAddrSTR.IP	 = Drone.GroundIP // net.IP(Drone.GroundIP)

	//fmt.Println("1 Drone.GroundUdpAddrSTR=", Drone.GroundUdpAddrSTR)
	myPort, _ = strconv.Atoi(msgHeader.DstPort)
	Drone.GroundUdpAddrSTR = &net.UDPAddr{IP: net.ParseIP(msgHeader.DstIP), Port: myPort}
	Drone.GroundFullName = common.NameId{Name: msgHeader.DstName, Address: *Drone.GroundUdpAddrSTR}
	//fmt.Println("2 Drone.GroundUdpAddrSTR=", Drone.GroundUdpAddrSTR)

	if err != nil {
		fmt.Println("ERROR in net.ResolveUDPAddr = ", err)
		fmt.Println("ERROR locating master, will retry")
		return
	} else {
		// fmt.Println("GROUND INFO: Name=", Drone.GroundFullName.Name, "  IP:Port=", Drone.GroundIPandPort)
	}
}
func xParseIP(s string) string {
	ip, _, err := net.SplitHostPort(s)
	if err == nil {
		return ip //, nil
	}

	ip2 := net.ParseIP(s)
	if ip2 == nil {
		return "" //, errors.New("invalid IP")
	}

	return ip2.String() //, nil
}

//====================================================================================
// Handle DISCOVERY messages in all states
//====================================================================================
func ControlPlaneProcessDiscoveryMessage(msgHeader *common.MessageHeader,
	discoveryMsg *common.DiscoveryMsgBody) {
	//fmt.Println("Discovery MSG in state ", Drone.DroneState)
	switch Me.TerminalState {
	case StateAlone:
		stateConnectedDiscoveryMessage(msgHeader, discoveryMsg)
		break
	case StateConnecting:
		stateConnectedDiscoveryMessage(msgHeader, discoveryMsg)
		break
	case StateConnected:
		stateConnectedDiscoveryMessage(msgHeader, discoveryMsg)
		break
	default:
	}
}

//==========================================================================
//
//===========================================================================
func stateConnectedDiscoveryMessage(msgHeader *common.MessageHeader,
	discoveryMsg *common.DiscoveryMsgBody) {
	sender := msgHeader.SrcId
	if sender < 1 || sender > 4 || sender == Me.TerminalId {
		// fmt.Println("DISCARD MSG: invalid senderId=", sender)
		return
	}
	node := &Drone.M2Terminal[sender-1]

	// update info for the sending drone
	node.TerminalName = msgHeader.SrcName
	node.TerminalId = msgHeader.SrcId
	node.TerminalIP = msgHeader.SrcIP
	node.TerminalMac = msgHeader.SrcMAC
	node.TerminalPort = msgHeader.SrcPort
	node.TerminalNextMsgSeq = msgHeader.SrcSeq

	//node.TerminalTimeCreated			= discoveryMsg.TimeCreated // incarnation #
	node.TerminalLastChangeTime = discoveryMsg.LastChangeTime
	node.TerminalActive = discoveryMsg.NodeActive
	node.TerminalMsgsSent = discoveryMsg.MsgsSent
	node.TerminalMsgsRcvd = discoveryMsg.MsgsRcvd
	//node.TerminalMsgLastSentAt			= discoveryMsg.MsgLastSentAt

	node.TerminalMsgLastRcvdAt = time.Now() // TBtimestampNano() // time.Now()
	//Drone.LastFrameRecvd[sender -1] 	= time.Now().String()

	// TODO:
	// node.ResidualHop
	//SubscriberList  BitMask
	//BaseStationList BitMask
	//senderNode = &Drone.NodeList[nodeId]
	//senderNode.NodeMac = msgHeader.SrcMAC // eth->getSrcMac(desc);
	//senderNode.NodeIP = msgHeader.SrcIP
	// Update sender type to whatever he is telling us
	//senderNode.NodeRole = int(msgHeader.SrcRole)
	// For the Sender replace subsriber and BaseStations lists with new ones:
	//BMset(&node.SubscriberList, node.Subscribers)
	//BMset(&node.BaseStationList, node.BaseStations)
	// Remember all node we learned about:
	// TODO Hmm ... should we forget those deleted by sender TODO

	/* // GORAN Mar05 comment out
	if node.NodeRole == BASE_STATION {
		//fmt.Println("Updated BaseStationList=", Me.BaseStationList.M_Mask)
		BMaddID(&Me.BaseStationList, int(node.NodeId - 1)) //nodeId)
		//fmt.Println("Updated BaseStationList=", Me.BaseStationList.M_Mask)
	} else {
		//fmt.Println("Updated SubscriberList=", Me.SubscriberList.M_Mask)
		BMaddID(&Me.SubscriberList, int(node.NodeId -1)) //.addID(nodeId);
		//fmt.Println("Updated SubscriberList=", Me.SubscriberList.M_Mask)
	}
	*/
	node.TerminalActive = true

	if Drone.GroundIsKnown {

		// did this DISCOVERY reach the ground as well
		/*
			if node.NodeDistanceToGround > Drone.GroundRadioRange {
				fmt.Println("=== NEED TO FORWARD, OTHER NODE ", int(node.NodeDistanceToGround),
					" AWAY FROM GROUND, Ground at ", Drone.GroundRadioRange)
				theNode, distance := FindShortestConnectionToGround()
				fmt.Println("====== Me=", Me.NodeId, " MY DISTANCE=",Me.NodeDistanceToGround,
					" HIS DISTANCE=", node.NodeDistanceToGround," SHORTEST=", distance,
					" Forwarder=", theNode.NodeId)
				if Me == theNode && Me.NodeDistanceToGround <= Drone.GroundRadioRange {
					fmt.Println("====== I AM THE FORWARDER ===========================")
					// forward the DISCOVERY as UNICAST to GROUND
					forwardUnicastDiscoveryPacket(msgHeader, discoveryMsg, int(distance))
				}
			} else {
				//fmt.Println("==NODE ", node.NodeId, " CAN REACH GROUND AT",node.DistanceToGround ,
				//	" Ground at ", Drone.GroundRadioRange)
			}
		*/
	}
}

/*
func FindShortestConnectionToGround() (*common.NodeInfo, float64) {
	var theNode *common.NodeInfo = nil
	var distance float64 = 1000000000
	for j:=0; j<64; j++ {
		if Drone.NodeList[j].NodeActive == true {
			if distance > Drone.NodeList[j].NodeDistanceToGround {
				distance = Drone.NodeList[j].NodeDistanceToGround
				theNode = &Drone.NodeList[j]
			}
		}
	}
	return theNode, distance
}
*/
//====================================================================================
// Handle messages received in the CONNECTED state
//====================================================================================
func RemoteCommandMessages(msg *common.Msg) {
	var cmds []common.LinuxCommand
	// _ = TBunmarshal(msg.MsgBody, &cmds)

	for cmdIndex := range cmds {
		var cmd common.LinuxCommand
		cmd = cmds[cmdIndex]
		err := RunLinuxCommand(REMOTE_CMD, cmd.Cmd, cmd.Par1, cmd.Par2, cmd.Par3, cmd.Par4, cmd.Par5, cmd.Par6)
		if err != nil {
			fmt.Printf("%T\n", err)
		}
	}
}

//=======================================================================
//
//=======================================================================
func LocalCommandMessages(cmdText string) {
	//var cmd []string
	//cmd = strings.Split(cmdText, " ")

	switch cmdText {
	case "enable":
		Me.TerminalActive = true
	case "disable":
		Me.TerminalActive = false
	default:
	}
	fmt.Println("RCVD CONSOLE INPUT =", cmdText, " DRONE ACTIVE=", Me.TerminalActive)
	// TODO figure out the bellow line
	//err := RunLinuxCommand(LOCAL_CMD, cmd[0], cmd[1], cmd[2], cmd[3], cmd[4], cmd[5], cmd[6])
	//if err != nil {fmt.Printf("%T\n", err)}

}

//=======================================================================
//
//=======================================================================
func RunLinuxCommand(origin, Cmd, Par1, Par2, Par3, Par4, Par5, Par6 string) error {
	//fmt.Println("RCVD CMD ", cmdIndex, " =",cmd)
	// fmt.Println("cmd=", cmd.Cmd, " ", cmd.Par1, " ", cmd.Par2, " ", cmd.Par3, " ", cmd.Par4, " ", cmd.Par5, " ", cmd.Par6)
	//cmd.Output() → run it, wait, get output
	//cmd.Run() → run it, wait for it to finish.
	//cmd.Start() → run it, don't wait. err = cmd.Wait() to get result.
	var thisCmd = exec.Command(Cmd, Par1, Par2, Par3, Par4, Par5, Par6)
	output, err := thisCmd.Output()
	//if err != nil && err.Error() != "exit status 1" {
	//	fmt.Println("CMDx=", cmd.Cmd, " ", cmd.Par1, " ", cmd.Par2, " ", cmd.Par3, " ", cmd.Par4,
	//		" ", cmd.Par5, " ", cmd.Par6, " :  cmd.Run() failed with ", err)
	//} else {
	//if err != nil && err.Error() != "exit status 1" {
	//	//panic(err)
	//	//fmt.Printf("ERROR=", err, "\n")
	//	fmt.Printf("%T\n", err)
	//} else {
	//	fmt.Printf("CMD OUTPUT=",string(output))
	//	// SEND REEPLY, OR MAYBE COMBINED ALL FIRST
	//}
	fmt.Println(origin, " CMD= ", Cmd, " ", Par1, Par2, " ", Par3, " ", Par4,
		" ", Par5, " ", Par6, " :  RESULT:", string(output), "  ERR:", err)
	return err
}

//====================================================================================
//  Set new state
//====================================================================================
func changeState(newState string) {
	// fmt.Println(Drone.DroneName, "OldState=", Drone.DroneState, " NewState=", newState)
	Me.TerminalState = newState
}

//====================================================================================
//  Format and send DISCOVERY msg
//====================================================================================
func sendBroadcastDiscoveryPacket() {
	// ... done in updateConnectivity ...MoveNode(Me)
	//Me.TerminalMsgLastSentAt = float64(common.TBtimestampNano()) // time.Now() //TBtimestampNano()
	//strconv.FormatInt(TBtimestampNano(), 10)

	dstPort := Drone.Connectivity.BroadcastTxPort
	// TODO add heightFromEarthCenter as configuration ?? what if it wobbles?
	// timeNow := time.Now()
	msgHdr := common.MessageHeader{
		MsgCode:  "DISCOVERY",
		Ttl:      3,
		TimeSent: float64(common.TBtimestampNano()), // timeNow, //Me.MsgLastSentAt, // time.Now().String()
		SrcSeq:   Me.TerminalNextMsgSeq,
		SrcMAC:   Me.TerminalMac,
		SrcName:  Me.TerminalName,
		SrcId:    Me.TerminalId, // node ids are 1 based
		SrcIP:    Me.TerminalIP,
		SrcPort:  Me.TerminalPort,
		DstName:  "BROADCAST",
		DstId:    0,
		DstIP:    Drone.Connectivity.BroadcastTxIP,
		DstPort:  dstPort,
		Hash:     0,
	}
	discBody := common.DiscoveryMsgBody{
		NodeActive: Me.TerminalActive,
		MsgsSent:   Me.TerminalMsgsSent,
		MsgsRcvd:   Me.TerminalMsgsRcvd,
	}
	myMsg := common.MsgCodeDiscovery{
		MsgHeader:    msgHdr,
		MsgDiscovery: discBody,
	}
	Me.TerminalNextMsgSeq++
	msg, _ := common.TBmarshal(myMsg)

	// fmt.Println(" BROADCAST DISCOVERY", Drone.BroadcastTxStruct)
	common.ControlPlaneBroadcastSend(Drone.Connectivity, msg, Drone.Connectivity.BroadcastTxStruct)

}

//=================================================================================
//=================================================================================
func forwardUnicastDiscoveryPacket(msgHeader *common.MessageHeader,
	discoveryMsg *common.DiscoveryMsgBody, groundDistance int) {
	// groundDistance - from this sender to ground, not from origin
	// use the header fields from original msg, except distance
	port := Drone.Connectivity.UnicastTxPort
	// latitude,longitude := ConvertXYZtoLatLong(Me.MyX, Me.MyY, Me.MyZ, orbitHeightFromEartCenter)
	msgHdr := common.MessageHeader{
		MsgCode:     "DISCOVERY",
		Ttl:         1,
		TimeSent:    msgHeader.TimeSent,
		SrcSeq:      msgHeader.SrcSeq,
		SrcRole:     msgHeader.SrcRole,
		SrcMAC:      msgHeader.SrcMAC,
		SrcName:     msgHeader.SrcName,
		SrcId:       msgHeader.SrcId, // node ids are 1 based
		SrcIP:       msgHeader.SrcIP,
		SrcPort:     msgHeader.SrcPort,
		DstName:     "UNICAST",
		DstId:       0,
		DstIP:       Drone.GroundIP,
		DstPort:     port,
		GroundRange: groundDistance, // use my distance
		Hash:        0,
	}

	discBody := common.DiscoveryMsgBody{
		TimeCreated:    discoveryMsg.TimeCreated,
		LastChangeTime: discoveryMsg.LastChangeTime,
		NodeActive:     discoveryMsg.NodeActive,
		MsgsSent:       discoveryMsg.MsgsSent,
		MsgsRcvd:       discoveryMsg.MsgsRcvd,
		MsgLastSentAt:  discoveryMsg.MsgLastSentAt,
		MsgLastRcvdAt:  discoveryMsg.MsgLastRcvdAt,
		Gateways:       discoveryMsg.Gateways,     // Me.GatewayList.M_Mask,
		Subscribers:    discoveryMsg.Subscribers,  // Me.SubscriberList.M_Mask,
		BaseStations:   discoveryMsg.BaseStations, // Me.BaseStationList.M_Mask,

	}
	myMsg := common.MsgCodeDiscovery{
		MsgHeader:    msgHdr,
		MsgDiscovery: discBody,
	}
	Me.TerminalNextMsgSeq++
	msg, _ := common.TBmarshal(myMsg)

	common.ControlPlaneUnicastSend(Drone.Connectivity, msg,
		Drone.GroundIP+":"+Drone.Connectivity.UnicastTxPort)
}

//=================================================================================
//=================================================================================
func sendUnicastStatusReplyPacket(msgHeader *common.MessageHeader) {
	fmt.Println("...... STATUS REPLY: srcIP=", msgHeader.SrcIP, " SrcMAC=", msgHeader.SrcMAC,
		" DstID=", msgHeader.DstId, " SrcID=", msgHeader.SrcId)

	// port, _ :=strconv.Atoi(Drone.UnicastTxPort)
	// latitude,longitude := ConvertXYZtoLatLong(Me.MyX, Me.MyY, Me.MyZ, orbitHeightFromEartCenter)
	msgHdr := common.MessageHeader{
		MsgCode:  "STATUS_REPLY",
		Ttl:      1,
		TimeSent: float64(common.TBtimestampNano()),
		SrcSeq:   Me.TerminalNextMsgSeq,
		SrcMAC:   Me.TerminalMac,
		SrcName:  Me.TerminalName,
		SrcId:    Me.TerminalId, // node ids are 1 based
		SrcIP:    Me.TerminalIP,
		SrcPort:  Me.TerminalPort,
		DstName:  "UNICAST",
		DstId:    msgHeader.SrcId,
		DstIP:    msgHeader.SrcIP,
		DstPort:  msgHeader.SrcPort,
		Hash:     0,
	}

	statusReplyBody := common.StatusReplyMsgBody{
		//TimeCreated:	Me.TerminalTimeCreated,
		LastChangeTime: Me.TerminalLastChangeTime,
		NodeActive:     Me.TerminalActive,
		MsgsSent:       Me.TerminalMsgsSent,
		MsgsRcvd:       Me.TerminalMsgsRcvd,
		//MsgLastSentAt:	 Me.TerminalMsgLastSentAt,
	}

	myMsg := common.MsgCodeStatusReply{
		MsgHeader:      msgHdr,
		MsgStatusReply: statusReplyBody,
	}
	Me.TerminalNextMsgSeq++
	msg, _ := common.TBmarshal(myMsg)

	common.ControlPlaneUnicastSend(Drone.Connectivity, msg, Drone.GroundIP+":"+Drone.Connectivity.UnicastTxPort)
}

//=================================================================================
//=================================================================================
func sendUnicastDiscoveryPacket(unicastIP string, distance int) {
	//Me.TerminalMsgLastSentAt = float64(common.TBtimestampNano()) //time.Now() //strconv.FormatInt(TBtimestampNano(), 10)

	port := Drone.Connectivity.UnicastTxPort //   strconv.Atoi(Drone.Connectivity.UnicastTxPort)
	// latitude,longitude := ConvertXYZtoLatLong(Me.MyX, Me.MyY, Me.MyZ, orbitHeightFromEartCenter)
	msgHdr := common.MessageHeader{
		MsgCode:  "DISCOVERY",
		Ttl:      3,
		TimeSent: float64(common.TBtimestampNano()), // timeNow, //Me.MsgLastSentAt, // time.Now().String()
		SrcSeq:   Me.TerminalNextMsgSeq,
		SrcMAC:   Me.TerminalMac,
		SrcName:  Me.TerminalName,
		SrcId:    Me.TerminalId, // node ids are 1 based
		SrcIP:    Me.TerminalIP,
		SrcPort:  Me.TerminalPort,
		DstName:  "UNICAST",
		DstId:    0,
		DstIP:    Drone.Connectivity.BroadcastTxIP,
		DstPort:  port,
		Hash:     0,
	}

	discBody := common.DiscoveryMsgBody{
		NodeActive: Me.TerminalActive,
		MsgsSent:   Me.TerminalMsgsSent,
		MsgsRcvd:   Me.TerminalMsgsRcvd,
	}
	myMsg := common.MsgCodeDiscovery{
		MsgHeader:    msgHdr,
		MsgDiscovery: discBody,
	}
	Me.TerminalNextMsgSeq++
	msg, _ := common.TBmarshal(myMsg)

	//fmt.Println( "SEND UNICAST DISCOVERY to ", unicastIP)
	common.ControlPlaneUnicastSend(Drone.Connectivity, msg, unicastIP)
}

//====================================================================================
// periodicFunc(tick) - executed every tick - originaly every 3 sec
// TODO add command to change time periods
//====================================================================================////
//func round(val float64) int {
//	if val < 0 { return int(val-0.5) }
//	return int(val+0.5)
//}

//====================================================================================
//
//====================================================================================
/*
   func getSize(v interface{}) int {
	size := int(reflect.TypeOf(v).Size())
	switch reflect.TypeOf(v).Kind() {
	case reflect.Slice:
		s := reflect.ValueOf(v)
		for i := 0; i < s.Len(); i++ {
			size += getSize(s.Index(i).Interface())
		}
	case reflect.Map:
		s := reflect.ValueOf(v)
		keys := s.MapKeys()
		size += int(float64(len(keys)) * 10.79) // approximation from https://golang.org/src/runtime/hashmap.go
		for i := range(keys) {
			size += getSize(keys[i].Interface()) + getSize(s.MapIndex(keys[i]).Interface())
		}
	case reflect.String:
		size += reflect.ValueOf(v).Len()
	case reflect.Struct:
		s := reflect.ValueOf(v)
		for i := 0; i < s.NumField(); i++ {
			if s.Field(i).CanInterface() {
				size += getSize(s.Field(i).Interface())
			}
		}
	}
	return size
   }
*/

//====================================================================================
// Check if we are talking to the ground/controller station
//====================================================================================
/*
   func isGroundKnown() bool {

	return Drone.GroundIsKnown
	// NOTE that this will fail if Drone.GroundUdpAddress has not been initialized due
	// to master not being up . Add state and try again to Resolve before doing this
	//var err error
	//fmt.Println("Locate master control, not ground")
	//Drone.GroundUdpAddrSTR, err = net.ResolveUDPAddr("udp", Drone.GroundIPandPort)
	//if err != nil {
	//	fmt.Println("ERROR in net.ResolveUDPAddr = ", err)
	//	fmt.Println("ERROR locating master, will retry")
	//	return false
	//}
	// TODO check if this is needed
	//Drone.GroundFullName = tbMessages.NameId{Name:Drone.GroundIPandPort,OsId:0,TimeCreated:"0",Address:*Drone.GroundUdpAddress}
	//fmt.Println(Drone.DroneName, "INIT: masterFullName=", Drone.GroundFullName)
	//mastersEntry := tbMessages.TBmgr{Name:Drone.GroundFullName,Up:true,LastChangeTime:"0",MsgsSent:0,LastSentAt:"0",MsgsRcvd:0,LastRcvdAt:"0"}
	//Drone.KnownDrones = append(Drone.KnownDrones, mastersEntry)
	// fmt.Println("Records of Known Satellites=", Drone.KnownDrones)
	// check the master is there:
	//theGround := locateOtherDrone(Drone.KnownDrones, Drone.GroundIPandPort)
	//if theGround != nil {
	//	fmt.Println("Ground at:", theGround.NodeName, "ADDRESS:", theGround.NodeIP, "Port:", theGround.NodePort,
	//		"MSGSRCVD:", theGround.MsgsRcvd)
	//} else {
	//	fmt.Println("GROUND Station Not Detected YET at ", Drone.GroundIPandPort)
	//}

	//return true
   }
*/
