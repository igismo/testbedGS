package common

import (
	"net"
	"time"
)
//======================================================================
// M3 terminal has one of these for each of the 4 M2 terminals
// and one of these for the M1 terminal
//======================================================================
type TerminalInfo struct {
	TerminalTimeCreated       time.Time // int64 // string    // time
	TerminalLastChangeTime    float64   // string // time.Time // time
	TerminalActive            bool
	TerminalKeepAliveRecvTime time.Time
	TerminalRole              int // M1, M2, M3
	TerminalName              string
	TerminalId                int
	TerminalIP                string
	TerminalMac               string
	TerminalPort              string
	TerminalIPandPort         string
	TerminalState             string
	//----------------------------
	TerminalNextMsgSeq    int
	TerminalMsgsSent      int64
	TerminalMsgsRcvd      int64
	TerminalMsgLastSentAt time.Time // float64 //  //
	TerminalMsgLastRcvdAt time.Time //float64 //  //

	TerminalHelloTimerLength     int64 // in milli sec
	TerminalLastHelloSendTime    int64
	TerminalLastHelloReceiveTime int64

	TerminalLogPath          string
	TerminalUdpAddrStructure       *net.UDPAddr
	TerminalConnection       *net.UDPConn
	TerminalFullName         NameId
	TerminalCreationTime     string
	TerminalConnectionTimer int
	TerminalReceiveCount     int64
	TerminalSendCount        int64
}

type ConnectivityInfo struct {
	//---------
	UnicastRxIP			    string
	UnicastRxPort			string
	UnicastRxAddress		string
	UnicastRxStruct	    *net.UDPAddr
	UnicastTxStruct	    *net.UDPAddr
	UnicastRxConnection net.PacketConn //*net.UDPConn
	UnicastTxPort			string
	//----------
	BroadcastTxIP      	string
	BroadcastTxPort    	string
	BroadcastTxAddress 	string // IP:Port
	BroadcastRxIP      	string
	BroadcastRxPort   	string
	BroadcastRxAddress	string // IP:Port
	BroadcastFullName 	NameId
	BroadcastConnection	net.PacketConn
	BroadcastTxStruct   *net.UDPAddr
}

type MyChannels struct {
	ControlChannel          chan []byte
	CmdChannel              chan []byte
	UnicastRcvCtrlChannel   chan []byte
	BroadcastRcvCtrlChannel chan []byte
	MulticastRcvCtrlChannel chan []byte
}
// m1 terminal own info
type M1Info struct {
	m3Terminal  TerminalInfo
	Connectivity ConnectivityInfo
	Channels     MyChannels
	// other own info
}
// m2 terminal own info
type M2Info struct {
	m3Terminal  TerminalInfo
	Connectivity ConnectivityInfo
	Channels     MyChannels
	// other own info
}

// m3Info ======================================================
// The following structure is host's m3 owned structure
// TerminalInfo (above) is one per each m1 and m2
//===============================================================
type M3Info struct {
	Terminal[6]         TerminalInfo // 0=M3, 1=M1, 2,3,4,5=M2
	//M3Terminal			TerminalInfo
	//M1Terminal			TerminalInfo
	//M2Terminal[4]		TerminalInfo

	Connectivity ConnectivityInfo
	Channels     MyChannels
	//-----------------
	GroundFullName   NameId
	GroundIsKnown    bool
	GroundIP         string
	GroundUdpPort    int
	GroundIPandPort  string
	GroundUdpAddrSTR *net.UDPAddr
	//--------------------------------------
}
