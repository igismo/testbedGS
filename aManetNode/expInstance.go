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
	"strconv"
	"fmt"
	"testbedGS/common/tbMsgUtils"
	"time"
	"testbedGS/common/tbMessages"
)

// 	"time"
//	"net"
//	"os"
//	"strings"
//	"testbedGS/common/tbJsonUtils"
//	"testbedGS/common/tbNetUtils"
//	"testbedGS/common/tbConfiguration"
//	"testbedGS/common/tbMessages"
//	"testbedGS/common/tbMsgUtils"

// Experiment states
const EXP_STATE_INIT        = "INIT"
const EXP_STATE_TRANSLATING = "TRANSLATING"
const EXP_STATE_UP          = "UP"
const EXP_STATE_DOWN        = "DOWN"

var EXPTSTATE_NEW	          = "new"
var EXPTSTATE_PRERUN          = "prerunning"
var EXPTSTATE_SWAPPED         = "swapped"
var EXPTSTATE_QUEUED          = "queued"
var EXPTSTATE_SWAPPING        = "swapping"
var EXPTSTATE_ACTIVATING      = "activating"
var EXPTSTATE_ACTIVE          = "active"
var EXPTSTATE_PANICED         = "paniced"
var EXPTSTATE_TERMINATING     = "terminating"
var EXPTSTATE_TERMINATED      = "ended"
var EXPTSTATE_MODIFY_PARSE    = "modify_parse"
var EXPTSTATE_MODIFY_REPARSE  = "modify_reparse"
var EXPTSTATE_MODIFY_RESWAP	  = "modify_reswap"
var EXPTSTATE_RESTARTING      = "restarting"

var TBDB_STATS_PRELOAD        = "preload"
var TBDB_STATS_START          = "start"
var TBDB_STATS_TERMINATE      = "destroy"
var TBDB_STATS_SWAPIN         = "swapin"
var TBDB_STATS_SWAPOUT        = "swapout"
var TBDB_STATS_SWAPMODIFY     = "swapmod"
var TBDB_STATS_SWAPUPDATE     = "swapupdate"
//====================================================================================
// https://www.slideshare.net/weaveworks/an-actor-model-in-go-65174438
// experiment Actor needs private data struct plus a channel
// the master needs a table containing exp mappings (project,exp, channel, timeCreated,)
// also number msgs sent,received, time of send/recv, ..
//====================================================================================
type ExperimentInstance  struct {
	Project             string
	Experiment          string
	User                string
	Channel      <-chan tbMessages.TBmessage
	State			    string
	TimeCreated         string // strconv.FormatInt(tbMsgUtils.TBtimestamp(),10)
	TimeLastChange		string
	MsgsSent			int64
	LastSentAt			string
	MsgsRcvd			int64
	LastRcvdAt			string
	LastRcvdType        string
}

var TableOfExperiments [] ExperimentInstance

func ExperimentStart(project,experiment,user string) {
	fmt.Println(project,"/",experiment,"EXPERIMENT INSTANCE SWAPIN ")
	experimentRecord := experimentLocate(project, experiment)
	if 	experimentRecord == nil {
		expChannel := make(chan tbMessages.TBmessage, 1) // size 16 ??
		// Create new experiment record
		experimentRecord               := &ExperimentInstance{}
		experimentRecord.Project        = project
		experimentRecord.Experiment     = experiment
		experimentRecord.User           = user
		experimentRecord.TimeCreated    = strconv.FormatInt(tbMsgUtils.TBtimestamp(), 10)
		experimentRecord.Channel        = expChannel
		experimentRecord.State          = EXP_STATE_INIT
		experimentRecord.TimeLastChange = experimentRecord.TimeCreated
		experimentRecord.MsgsSent       = 0
		experimentRecord.LastSentAt     = ""
		experimentRecord.MsgsRcvd       = 0
		experimentRecord.LastRcvdAt     = ""
		experimentRecord.LastRcvdType   = "NONE"

		TableOfExperiments = append(TableOfExperiments, *experimentRecord)

		fmt.Println(project,"/",experiment,"START EXPERIMENT INSTANCE FSM, TABLE SIZE=",len(TableOfExperiments))

		go experimentFSM(experimentRecord)

	} else {
		fmt.Println(experimentRecord.Project,"/",experimentRecord.Experiment," ERROR - Already Active 1")
	}
}


func experimentFSM (myRecord *ExperimentInstance) {
	fmt.Println(myRecord.Project,"/",myRecord.Experiment,"Start experiment FSM")
	expInstanceChannel := myRecord.Channel
	//timer := time.Ticker{}
	ticker := time.NewTicker(10 * time.Second)
	go func(myRecord *ExperimentInstance){
		for t := range ticker.C {
			//Call the periodic function here.
			expInstanceTick(t, myRecord)
		}
	}(myRecord)

	expInstanceSetState(myRecord, EXP_STATE_INIT)
	fmt.Println(myRecord.Project,"/",myRecord.Experiment,"EXPERIMENT INSTANCE FSM - WAIT FOR MESSAGES ")
	for {
		select {
		case msg := <-expInstanceChannel:
			expInstanceHandleMsg(myRecord, &msg)

		//case <-timer.C:
		//	fmt.Println("Do Something Timer")
		}
	}
}
//====================================================================================
//
//====================================================================================
func expInstanceTick(tick time.Time, myRecord *ExperimentInstance){
	fmt.Println("--------------->>", myRecord.Project,"/",myRecord.Experiment,"  EXP INSTANCE Tick at: ", tick)

	sendRegisterMsg()
	// newMsg := tbMsgUtils.TBhelloMsg(myFullName, offMgrFullName, "ABCDEFG")
	// sendMsgOut(newMsg, *offMgrUdpAddress)
}
//====================================================================================
// Change the state of the experiment
//====================================================================================
func expInstanceHandleMsg(myRecord *ExperimentInstance, msg *tbMessages.TBmessage) {
	fmt.Println(myRecord.Project,"/",myRecord.Experiment,"expInstanceHandleMsg in STATE=", myRecord.State)
}

//====================================================================================
// Change the state of the experiment
//====================================================================================
func expInstanceSetState(myRecord *ExperimentInstance, newState string) {
	fmt.Println(myRecord.Project,"/",myRecord.Experiment,"expInstanceSetState: OldState=",
		        myRecord.State, " NewState=", newState)
	myRecord.State = newState
}

//============================================================================
// Locate specific row in the slice(table) of all experiments, containing rows
// rows all acive experiments
// Return nil if row not found. Simple search for now
//============================================================================
func experimentLocate(project, experiment string) *ExperimentInstance {
	for index := range TableOfExperiments {
		if  TableOfExperiments[index].Project == project &&
			TableOfExperiments[index].Project == experiment {
			return &TableOfExperiments[index]
		}
	}

	return nil
}
//---------------------------- temporary junk ----------------------
//=============================
//type expActorData1 struct {who string}
//type expActorData2 struct {who string}
//type expMsg1 struct {what string}
//
//func ExpStartActor1() * expActorData1 {
//	expChannel := make(chan expMsg1, 1) // size
//	actor := &expActorData1{"xyz"}
//	go actor.expFSM(expChannel)
//	return actor
//}
//
//
//func (actor * expActorData1) expFSM (expCh <-chan expMsg1) {
//
//	timer := time.NewTimer(3)
//	for {
//		select {
//		case action := <-expCh:
//			// deal with action action()
//			fmt.Println("Do Something", action, "by",actor.who)
//		case <-timer.C:
//			fmt.Println("Do Something Timer")
//		}
//	}
//}
//
// MAybe diferent type of experiment - just a place holder ...
//func (actor * expActorData2) expFSM (expCh <-chan expMsg1) {
//
//}
////- BonBlocking Send ------
//func (actor *SomeActor) TryTo(something) {
//	select {
//	case actor.actionChan <- func() {
//		// do the thing
//	}:
//		default:
//			// chan is full; throw it away
//	}
//}
//console.ReadLine()