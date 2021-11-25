/********************************************************************************

    <"testbedGS" - Runtime structures and modular distributed component
      architecture providing infrastructure and platform to build testbeds>

    Copyright (C) <2018>  <Goran Scuric, goran@usa.net, igismo.com>

    GNU GENERAL PUBLIC LICENSE ... Version 3, 29 June 2007

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*********************************************************************************/
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================
package main

import (
    "fmt"
    "net"
    "os"
    "strconv"
    "time"
    "strings"
    //"tbConfig"
    //"tbMsgUtils"
    //"tbMessages"
    //"tbJsonUtils"
    //"tbNetUtils"
    //"tbLogUtils"
    //"tbUtils"
    "io/ioutil"
)

// OFFICE MANAGER STATES
const STATE_INIT       = "STATE_INIT"
const STATE_CONNECTING = "STATE_CONNECTING"
const STATE_CONNECTED  = "STATE_CONNECTED"
const STATE_UP         = "STATE_UP"
const STATE_DOWN       = "STATE_DOWN"

var myState                    = STATE_INIT
var myName                     = TBofficeMgrName
var myFullName                  NameId // struct - needs init
var myCreationTime             = strconv.FormatInt(TBtimestamp(),10)
var myUdpAddress  *net.UDPAddr = nil
var myIpAddress                = ""
var myConnection  *net.UDPConn = nil

var mySendChannel        chan []byte = nil // To Send messages out to other modules
var myRecvChannel        chan []byte = nil // To Receive messages from other modules
var myControlChannel     chan []byte = nil // so that all local threads can talk back
var mySendControlChannel chan []byte = nil // to send control msgs to Send Thread
var myRecvControlChannel chan []byte = nil // to send control msgs to Recv Thread
var myTimerChannel       chan string = nil // timer ticks
var myReceiveCount   = 0

var Log = LogInstance{}
var myTicker      *time.Ticker = nil
var sliceOfMgrs [] TBmgr

var mysqlMasterIP = ""

//=======================================================================
// Enry point for the office Master
// Note that the log is created, but logging is stil outstanding work
//=======================================================================
func main() {
    LogLicenseNotice()

    fmt.Println(myName,"========= START =========================")

    Log.DebugLog   = true
    Log.WarningLog = true
    Log.ErrorLog   = true
    CreateLog(&Log, myName)
    Log.Warning(&Log,"this will be printed anyway")

    officeMgrSetState(STATE_INIT)

    officeMasterInit()

    myTicker := time.NewTicker(15 * time.Second)

    go func(){
        // fmt.Println(myName,"MAIN: Starting a new ticker....")
        for t := range myTicker.C {
            periodicFunc(t)
        }
    }()

    consoleInput := make(chan string)
    startConsole(consoleInput)

    fmt.Println(myName, "MAIN: Initialized, start select loop")

    for {
        select {
        case msg1 := <-myRecvChannel:
            //fmt.Println(myName, "MAIN: RecvChannel msg")
            handleMessages(msg1)

        case msg2 := <-myTimerChannel:
            //fmt.Println(myName, "MAIN: TimerChannel msg=", msg2)
            handleTimerMessages(msg2)
            // default:
            // fmt.Println("done and exit select")

        case stdin, ok := <-consoleInput:
            if !ok {
                fmt.Println("ERROR Reading input from stdin:", stdin)
                break
            } else {
                 fmt.Println("Read input from stdin:", stdin)
            }
        } // EndOfSelect
    }
}
//=======================================================================
// Some notes for later:
// Just create /etc/resolv.conf and append nameserver 8.8.8.8 then this
// problem will be resolved. According to src/net/dnsclient_unix.go,
// if /etc/resolv.conf is absent, localhost:53 is chosen as a name server.
// Since the Linux in Android is not so "standard". /etc/resolv.conf is not available.
// The app then just keep looking up host in localhost:53.
//=======================================================================
func officeMasterInit() {
    var err error = nil

    mysqlMasterIP = GetMastersIP(TBmysqlMasterName)
    if mysqlMasterIP != "" {
       fmt.Println("INIT TB-MYSQLMASTER is at ", mysqlMasterIP)
        Ping(mysqlMasterIP, 3)
    }
    fmt.Println(myName, "INIT: create channels")
    myTimerChannel       = make(chan string) // Timer ticks
    mySendControlChannel = make(chan []byte) // so that we can talk to sendThread
    myRecvControlChannel = make(chan []byte) // so that we can talk to recvThread
    mySendChannel        = make(chan []byte) // so that we can talk to sendThread
    myRecvChannel        = make(chan []byte) // rcv messages from the universe
    myControlChannel     = make(chan []byte) // so that all threads can talk to us

    // conn, err := net.ListenUDP("udp", udpAddr)
    //
    // n, addr, err := conn.ReadFromUDP(buf[0:])
    // conn.WriteToUDP([]byte(daytime), addr)

    if myConnection == nil {
        myUdpAddress, err = net.ResolveUDPAddr("udp", TBofficeMgr)
        if err != nil {
            fmt.Println("INIT: ERROR in net.ResolveUDPAddr = ", err)
            fmt.Println("INIT: ERROR locating Office Manager, will retry")
            //return false
        }
        fmt.Println(myName, "INIT: myUdpAddress=", myUdpAddress)

        myIpAddress = GetLocalIp()
        fmt.Println(myName,"INIT: My Local IP=", myIpAddress, " My UDP address=", myUdpAddress)

        // conn, err := net.DialUDP("udp", nil, myUdpAddress)
        myConnection, err = net.ListenUDP("udp", myUdpAddress)
        checkError(err)
        fmt.Println(myName, "INIT: myConnection=", myConnection)

        err1 := sendThread(myConnection, mySendChannel, mySendControlChannel)
        if err1 != nil {fmt.Println("INIT: Error creating send thread")}

        err2 := recvThread(myConnection, myRecvChannel, myRecvControlChannel)
        if err2 != nil {fmt.Println("INIT: Error creating Receive thread")}

        if err1 != nil || err2 != nil {
            return
        }

        myFullName = NameId{Name: myName, OsId: os.Getpid(),
            TimeCreated: myCreationTime, Address: *myUdpAddress}
        fmt.Println(myName,"INIT: myFullName=", myFullName)

    }

    officeMgrSetState(STATE_CONNECTED)

    fmt.Println(myName,"INIT: Office Master Start Receive at", myCreationTime)
}
//=======================================================================
//
//=======================================================================
func periodicFunc(tick time.Time) {
    // GS send keepAlive messages at whatever interval
    //fmt.Println(myName, "Tick at: ", tick)
    sendKeepAliveMsg()
    // TODO remove later ... just for test
    if mysqlMasterIP == "" {
        mysqlMasterIP = GetMastersIP(TBmysqlMasterName)
        fmt.Println("INIT TB-MYSQLMASTER is at ", mysqlMasterIP)

    }
}

//=================================================
// sendThread() - Thread sending our messages out
// The caller supplies the control channel over which
// control messages can be received by this thread
// Parameters:	service - 10.0.0.2:1200
// 				sendControlChannel - channel
//=======================================================================
//
//=======================================================================
func sendThread(conn *net.UDPConn, sendChannel, sendControlChannel chan []byte) error {
    var err error = nil
    fmt.Println(myName, "SendThread: Start SEND THRED")
    go func() {
        connection := conn
        var controlMsg TBmessage
        fmt.Println(myName, "SendThread: Ready for Sending")
        //myControlChannel <- TBConnectedMsg(myCreationTime)

        for {
            select {
            case msgOut := <-sendChannel: // got msg to send out
                fmt.Println(myName, "SendThread: Sending MSG out")
                _, err = connection.Write([]byte(msgOut))
                if err != nil {
                    fmt.Fprintf(os.Stderr, "Error Sending %s", err.Error())
                    // create more descriptive msg
                    // send msg up to indicate a problem ?
                }

            case ctrlMsg := <- sendControlChannel: //
                TBunmarshal(ctrlMsg, &controlMsg)
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
//=======================================================================
// recvThread() - Thread receiving messages from others
//=======================================================================
func recvThread(conn *net.UDPConn, recvChannel, recvControlChannel <-chan []byte) error {
    var err error = nil
    fmt.Println(myName,"RecvThread: Start RECV THRED")
    go func() {
        connection := conn

        fmt.Println(myName,"RecvThread: Start Receiving")
        var controlMsg TBmessage
        var oobBuffer [3000] byte

        for {
            recvBuffer := make([]byte, 3000)
            length,oobn,flags,addr,err := connection.ReadMsgUDP(recvBuffer[0:], oobBuffer[0:])

            myReceiveCount++

            fmt.Println(myName,"\n=============== Count=",myReceiveCount,
                   "\nRecv from",addr, "len=",length,"oobLen=", oobn,"flags=",flags,"ERR=",err)

            myRecvChannel <- recvBuffer[0:length]

            if len(recvControlChannel) != 0 {
                ctrlMsg := <- recvControlChannel
                TBunmarshal(ctrlMsg, &controlMsg)
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
//=======================================================================
//
//=======================================================================
func handleMessages(message [] byte) {
    //fmt.Println(myName, "HandleMessages: Recv Message in State", myState, "Message=",string(message))
    msg := new(TBmessage)
    TBunmarshal(message, &msg)
    //fmt.Println(myName, "HandleMessages: Recv Message in State", myState, "Type:",msg.MsgType,"From=",msg.MsgSender)

    switch myState {
    case STATE_INIT:
        break
    case STATE_CONNECTING:
        // stateConnectingMessages(msg)
        break
    case STATE_CONNECTED:
        // fmt.Println(myName,"State=",myState," Send MSG to=", receiver, "Type:", msg.MsgType)
        stateUpMessages(message)
        break
    case STATE_UP:
        break
    case STATE_DOWN:
        stateUpMessages(message)
        break
    default:
    }
}
//=======================================================================
//
//=======================================================================
func handleTimerMessages(message string) {
    fmt.Println(myName, "MAIN: Timer tick in state", myState, "MSG=",string(message))
    // fmt.Println(myName,"HandleTimerMessages MSG(",unsafe.Sizeof(message),")=", string(message))
    // switch on myState
    switch myState {
    case STATE_INIT:
    case STATE_CONNECTING:
        officeMasterInit()
        break
    case STATE_CONNECTED:
    case STATE_UP:
    case STATE_DOWN:
        fmt.Println("Timer Tick")
        break
    default:
    }
}

//insert myUdpAddress into message insted of id Uint ..........
//    to be used for replies
//=======================================================================
//
//=======================================================================
func stateUpMessages(message [] byte) {

    // Unmarshal
    msg := new(TBmessage)
    TBunmarshal(message, &msg)

    switch msg.MsgType {
    case MSG_TYPE_REGISTER:
        receiveRegisterMsg(msg)
        break
    case MSG_TYPE_HELLO:
        sendHelloReplyMsg(msg)
        break
    default:
        break
    }
    //currentTime := strconv.FormatInt(TBtimestamp(), 10)
    //replyMessage := "stateUpMessages:Replying to You at " + currentTime + "ms"
    //fmt.Println("REPLY to ", )
    //fmt.Println(myName, "stateUpMessages: REPLY=", replyMessage)
    //sendBuffer, _ := TBmarshal(replyMessage)
    //myConnection.WriteToUDP(sendBuffer, myUdpAddress)

}

//=======================================================================
//
//=======================================================================
func officeMgrSetState(newState string) {
    fmt.Println(myName,"OldState=",myState, " NewState=", newState)
    myState = newState
}
//=======================================================================
//
//=======================================================================
func checkError(err error) {
    if err != nil {
        fmt.Fprintf(os.Stderr, "Fatal error ", err.Error())
        os.Exit(1)
    }
}

//=======================================================================
//
//=======================================================================
func sendHelloReplyMsg(msg * TBmessage) {
    remoteUdpAddress := net.UDPAddr{IP: msg.MsgSender.Address.IP,
                        Port: msg.MsgSender.Address.Port}

    replyBuffer := TBhelloReplyMsg(myFullName, msg.MsgSender, string(msg.MsgBody))

    // fmt.Println("WriteToUdp Reply remoteUdpAddress=", remoteUdpAddress)
    myConnection.WriteToUDP([]byte (replyBuffer), &remoteUdpAddress)
}
//=======================================================================
//
//=======================================================================
func locateMysqlMaster() string {
fmt.Println("---> Locate MYSQL MASTER")
    mysqlMasterIP = GetMastersIP(TBmysqlMasterName)
    //fmt.Println("INIT TB-MYSQLMASTER is at ", mysqlMasterIP)
    if mysqlMasterIP != "" {
        // TODO save address into file .... to be used by www server
        ipData := []byte(mysqlMasterIP)
        err := ioutil.WriteFile("/var/www/mysqlip", ipData, 0644)
        if err != nil {
            fmt.Println("TICK: FAILED TO STORE TB-MYSQLMASTER IP to /var/www/mysqlip", err)
        } else {
            fmt.Println("TICK: DISCOVERED TB-MYSQLMASTER is at ", mysqlMasterIP)
        }
    } else {
        os.Remove("/var/www/mysqlip")
    }
    return mysqlMasterIP
}
//=======================================================================
//
//=======================================================================
func receiveRegisterMsg(msg *TBmessage) {
    fmt.Println("REGISTER MSG FROM=", msg.MsgSender)

    // Unmarshal the message body
    var theMgr TBmgr
    TBunmarshal(msg.MsgBody, &theMgr)

    fmt.Println("MGR:",theMgr.Name.Name, "STATUS:",theMgr.Up,"ADDRESS:",theMgr.Name.Address,
        "CREATED:",theMgr.Name.TimeCreated, "MSGSRCVD:",theMgr.MsgsRcvd)

    // add sliceOfManagers, check if already there and update, otherwise append
    existingMgr := TBlocateMngr(sliceOfMgrs, theMgr.Name.Name)
    if existingMgr != nil { // Update existing mgr/master record
        fmt.Println("UPDATE in sliceOfMgrs MGR=", existingMgr.Name)
        *existingMgr = theMgr
    } else { // Add a new manager/master
        fmt.Println("STORE in sliceOfMgrs MGR=", theMgr.Name)
        sliceOfMgrs = append(sliceOfMgrs, theMgr)
    }
    fmt.Println("New SLICE of Managers=", sliceOfMgrs)
    fmt.Println("LENGTH of sliceOfMgrs=",len(sliceOfMgrs))
}
//============================================================================
// Locate specific row in the slice of all managers, containing rows for
// all known managers including ourselves and the office manager
// Return nil if row not found
//============================================================================
func TBlocateMngr(sliceTable [] TBmgr, mngr string) *TBmgr{
    for index := range sliceTable {
        if  sliceTable[index].Name.Name == mngr {
            return &sliceTable[index]
        }
    }

    return nil
}
//============================================================================
// Locate specific row in the slice of all managers, containing rows for
// all known managers including ourselves and the office manager
// Return nil if row not found
//============================================================================
func locateMngr(slice [] TBmgr, mngr string) *TBmgr{
    for index := range slice {
        if  slice[index].Name.Name == mngr {
            return &slice[index]
        }
    }
    return nil
}
//====================================================================================
// send keep alive messages to everybody registered
// There has to bea way to convey the IP addresses of some modules to webMaster,
// until either the php code is conevrted to GO, or somehow learning those at the PHP
// level ... SO, JUST TEMPORARY - simpler method seems to be where officeMaster will
// delete the two files in the www/ space, namely mysqlip and expmasterip files.
// The mysql server should be combined with dbaseMaster which can participate in the
// register and keepalive protocol. At the moment this is not done, therefore office
// master will create the "www/mysqlip" file whenever it discoveres mysql server
// container - not exectly elegant, but will do for now
// On the other hand, webMAster PHP also needs to talk to expMaster, and until more work
// is done the IP address of the expMaster will be stored in the www/expmasterip file.
// Here we had two choice - either let expMaster or officeMaster create that file.
// The simplest for now seems to be letting officeMaster deleting the file if the
// expMaster is not alive, and creating the file when expMaster registers in....
//====================================================================================
var mysqlStored = 0
func sendKeepAliveMsg() {
    // GS also send only to modules that are up ....
    //{TB-EXPMASTER 1 1522878314281123 {172.18.0.3 1200 }} true 1522878314281123 0 1522880258356310 0 0}}
    // TODO a lots of cleanup and better logic btw this, registration and peiodic timer
    mysqlIp := locateMysqlMaster()
    if mysqlIp != "" {
        fmt.Println("MYSQL MASTER IP = ", mysqlIp)
        var mysqlName= TBmysqlMasterName
        mysqlUdpAddress, _ := net.ResolveUDPAddr("udp", TBmysqlMaster)

        mysqlFullName := NameId{Name: mysqlName, OsId: os.Getpid(),
            TimeCreated: myCreationTime, Address: *mysqlUdpAddress}
		fmt.Println("MYSQL MASTER FULL NAME+", mysqlFullName)
		//mysqlFullName.Address.IP.

        mysqlEntry := TBmgr{Name: mysqlFullName, Up: true, LastChangeTime: myCreationTime,
            MsgsSent: 0, LastSentAt: "0", MsgsRcvd: 0, LastRcvdAt: "0"}
        if mysqlStored == 0 {
            sliceOfMgrs = append(sliceOfMgrs, mysqlEntry)
            mysqlStored++
        }
        //  check that the record is there
        existingMgr, index :=  LocateMaster(sliceOfMgrs, mysqlName)
        if existingMgr != nil { // Update existing mgr/master record
            fmt.Println("UPDATE in sliceOfMgrs MGR=", existingMgr.Name, " Index=", index)
            //sliceOfMgrs[index] = sliceOfMgrs[len(sliceOfMgrs)-1]  // Replace it with the last one.
            //sliceOfMgrs[len(sliceOfMgrs)-1] = nil                 // Chop off the last one.
            //sliceOfMgrs = sliceOfMgrs[:len(sliceOfMgrs)-1]        // adjust length

        } else { // Temporary: Add a new mysql master
         //    fmt.Println("STORE in sliceOfMgrs MGR=", mysqlEntry.Name)
         //   sliceOfMgrs = append(sliceOfMgrs, mysqlEntry)
        }
    }

    var names = ""
    if len(sliceOfMgrs) > 0 {
        fmt.Println("sendKeepAlive: LENGTH of sliceOfMgrs=", len(sliceOfMgrs))
        msgBody, _ := TBmarshal(sliceOfMgrs)
        fmt.Println("sendKeepAlive: LENGTH of msgBody=", len(msgBody))
        for mgrIndex := range sliceOfMgrs {
            receiver := sliceOfMgrs[mgrIndex].Name
            if receiver.Name != myName { // Do not send to self
                udpAddress := sliceOfMgrs[mgrIndex].Name.Address
                newMsg := TBkeepAliveMsg(myFullName, receiver, string(msgBody))
                TBsendMsgOut(newMsg, udpAddress, myConnection)
                names += " " + receiver.Name
            }
        }
        fmt.Println(myName,": KNOWN MODULES=", names)
    }
}
