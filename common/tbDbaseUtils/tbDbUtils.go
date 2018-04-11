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
package tbDbaseUtils

import (
	"database/sql"
	_ "github.com/go-sql-driver/mysql"
	"fmt"
	//"strconv"
	//"log"
	"errors"
	"os"
	"crypto/md5"
	"io"
	"encoding/hex"
)


// Configure variables
var TB		    = "/usr/testbed"
var DBErrorString     = ""
var DB_NO_MAIL        = true
type DbaseType struct {
	UserId string
	UserPass string
	DbaseName string
	DbaseServer string
	DbaseConnection *sql.DB
	DbaseError string
}
// NOTE: Use Exec(), preferably with a prepared statement, to accomplish an
// INSERT, UPDATE, DELETE, or other statement that doesn’t return rows
// At the database level, a prepared statement is bound to a single database connection.
// Here’s how it works:
// When you prepare a statement, it’s prepared on a connection in the pool.
// The Stmt object remembers which connection was used.
// When you execute the Stmt, it tries to use the connection. If it’s not available because
// it’s closed or busy doing something else, it gets another connection from the pool and re-prepares
// the statement with the database on another connection.
// EXAMPLE:
/*
stmt, err := db.Prepare("INSERT INTO users(name) VALUES(?)")
if err != nil {
	log.Fatal(err)
}
res, err := stmt.Exec("Dolly")
if err != nil {
	log.Fatal(err)
}
lastId, err := res.LastInsertId()
if err != nil {
	log.Fatal(err)
}
rowCnt, err := res.RowsAffected()
if err != nil {
	log.Fatal(err)
}
*/
// If you don’t want to use a prepared statement, you need to use fmt.Sprint() or similar
// to assemble the SQL, and pass this as the only argument to db.Query() or db.QueryRow()
// The syntax for placeholder parameters in prepared statements is database-specific.
// For example, comparing MySQL, PostgreSQL, and Oracle:
/* --=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
MySQL               PostgreSQL            Oracle
=====               ==========            ======
WHERE col = ?       WHERE col = $1        WHERE col = :col
VALUES(?, ?, ?)     VALUES($1, $2, $3)    VALUES(:val1, :val2, :val3)
*/
/* Fetching Data from the Database --=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
var (
	id int
	name string
)
rows, err := db.Query("select id, name from users where id = ?", 1)
if err != nil {
	log.Fatal(err)
}
defer rows.Close() // IMPORTANT
for rows.Next() {
	err := rows.Scan(&id, &name)
	if err != nil {
		log.Fatal(err)
	}
	log.Println(id, name)
}
err = rows.Err()
if err != nil {
	log.Fatal(err)
}
 */

   /* -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- TRANSACTIONS:
tx, err := db.Begin()
if err != nil {
	log.Fatal(err)
}
defer tx.Rollback()
stmt, err := tx.Prepare("INSERT INTO foo VALUES (?)")
if err != nil {
	log.Fatal(err)
}
defer stmt.Close() // danger!
for i := 0; i < 10; i++ {
	_, err = stmt.Exec(i)
	if err != nil {
		log.Fatal(err)
	}
}
err = tx.Commit()
if err != nil {
	log.Fatal(err)
}
// stmt.Close() runs here!
    */
//====================================================================================
// dbTransaction for INSERT, UPDATE, DELETE like .. "INSERT INTO foo VALUES (xyz)
//====================================================================================
func DBtransaction(caller string, db *sql.DB, what string) (int64, int64, error) {
	fmt.Println(caller,": DBtransaction: ", what)
	tx, err := db.Begin()
	if err != nil {
		fmt.Println(caller,": DBtransaction ERROR ", err)
		//log.Fatal(err)
	}
	defer tx.Rollback()
	stmt, err := tx.Prepare(what)
	if err != nil {
		fmt.Println(caller,": DBtransaction ERROR ", err)
		//log.Fatal(err)
	}
	defer stmt.Close() // danger!

	result,err := stmt.Exec()
	if err != nil {
		fmt.Println(caller,": DBtransaction ERROR ", err)
		//log.Fatal(err)
	}
	lastId, err := result.LastInsertId()
	if err != nil {
		fmt.Println(caller,": DBtransaction ERROR ", err)
		//log.Fatal(err)
	}
	rowsAffected, err := result.RowsAffected()
	if err != nil {
		fmt.Println(caller,": DBtransaction ERROR ", err)
		//log.Fatal(err)
	}
	err = tx.Commit()
	if err != nil {
		fmt.Println(caller,": DBtransaction ERROR ", err)
		//log.Fatal(err)
	}
	// fmt.Println(caller,": DBtransaction: DONE")
	return lastId,rowsAffected,err
	// stmt.Close() runs here!
}
//====================================================================================
// dbSelect one and two
//====================================================================================
func DBselectQuery(caller string , db *sql.DB, what string) (*sql.Rows,error) {
	fmt.Println(caller,": DBselectQuery: ", what)
	stmt, err := db.Prepare(what)
	if err != nil {
		fmt.Println(caller,": DBselectQuery: db prepare FAILED:",err)
		return nil, err   //log.Fatal(err)
	}
	//fmt.Println(caller,": DBselectQuery: After PREPARE")
	if stmt == nil {
		fmt.Println(caller,": DBselectQuery: PROBLEM prepare stmt=:",nil)
		return nil, errors.New("DBselectQuery: PROBLEM prepare stmt=nil")
	}
	rows, err := stmt.Query()

	if err != nil {
		stmt.Close()
		fmt.Println(caller,": DBselectQuery: query FAILED:", err)
		return nil, err  //log.Fatal(err)
	}
	defer stmt.Close()
	// fmt.Println(caller,": DBselectQuery: DONE ",err)
	return rows,err
}
//====================================================================================
// OK
//====================================================================================
func DbunlockTables(callerId string, dbConnection *sql.DB) (sql.Result,error) {
	fmt.Println(callerId,": UNLOCK TABLES ===========================================")
	/* TODO ... uncomment
	fmt.Println(callerId,": MySql database - Unlock Tables")
	useResult, err2 := dbConnection.Exec("unlock tables")
	if err2 != nil {
		fmt.Println(callerId + ": USE DB ERROR =", err2)
	} //else { showResult(callerId + ": use database result", useResult) }
	return useResult, err2
		*/
	return nil,nil
}
//====================================================================================
// OK
//====================================================================================
func DblockTables(callerId string, dbConnection *sql.DB, tableList string) (sql.Result,error) {
	fmt.Println(callerId,": LOCK TABLES =============================================")
	/* TODO ... uncomment
	fmt.Println(callerId,": MySql database - Unlock Tables")
	useResult, err2 := dbConnection.Exec("lock tables " + tableList)
	if err2 != nil {
		fmt.Println(callerId + ": USE DB ERROR =", err2)
	} //else { showResult(callerId + ": use database result", useResult) }
	return useResult, err2
	*/
	return nil,nil
}
//====================================================================================
// OK
//====================================================================================
func TbDbPing(callerId string, dbConnection *sql.DB) error {
	fmt.Println(callerId,": Ping DB")
	err := dbConnection.Ping()
	if err != nil {
		fmt.Println(callerId,": MySql: PING mySql DB failed")
	} else {fmt.Println("\nMySql: PING is working OK")}
	return err
}

//====================================================================================
// OK
//====================================================================================
func DbuseDB(callerId string, dbConnection *sql.DB, newDb string) (sql.Result,error) {

	fmt.Println(callerId,": MySql database - USE mysql db=", newDb)
	useResult, err2 := dbConnection.Exec("use " + newDb)
	if err2 != nil {
		fmt.Println(callerId + ": USE DB ERROR =", err2)
		//return nil, err2
	} else {
		fmt.Println(callerId + ": USE DB OK .....")
		return useResult, err2
		}
	fmt.Println(callerId + ": Trying useDb select")
	useResult1, err2 := DBselectQuery("DbuseDB",dbConnection, "use " + newDb)
	useResult1.Close()
	if err2 != nil {
		fmt.Println(callerId + ": USE DB ERROR =", err2)
		return nil, err2
	} //else { showResult(callerId + ": use database result", useResult) }
	fmt.Println(callerId + ": USE DB OK .....")
	return nil, err2
}
func DBopenMysql(callerId, userId, userPass, dbName, myServer string) (*DbaseType,error) {
	var myDbase DbaseType
	fmt.Println(callerId,":OpenDB UID=",userId, " userPass=",userPass, " dbName=", dbName)
	//db, err := sql.Open("mysql", "root:password@tcp("+ tbConfig.TBmysqlServer +")/mysql")
	dataSourceName := userId + ":"+ userPass+ "@tcp("+ myServer +")/mysql"
	dbConnection, err := sql.Open("mysql", dataSourceName)
	if err != nil {
		// panic(err.Error())
		fmt.Println(callerId,": DID NOT OPEN DB ERROR", err)
		return nil, err
	} else {
		fmt.Println(callerId,": MySql database open OK ", dbConnection)
		DBshowDatabases(callerId, dbConnection)

		_,err3 := DbuseDB(callerId, dbConnection, dbName)
		if err3 != nil {
			fmt.Println(callerId,": PING FAILED")
			return nil, err3
		}
		myDbase.UserId = userId
		myDbase.UserPass = userPass
		myDbase.DbaseName = dbName
		myDbase.DbaseServer = myServer
		myDbase.DbaseConnection = dbConnection
		myDbase.DbaseError = ""
		// DbuseDB(callerId, dbConnection, dbName)
		return &myDbase,err
	}
}
//====================================================================================
//
//====================================================================================
func DBshowDatabases(callerId string, db *sql.DB) (*sql.Rows,error) {
	fmt.Println(callerId,": MySql database - show databases")

	dbResult, err1 := db.Query("show databases")
	if err1 != nil {
		fmt.Println(callerId,": SHOW ERROR =", err1)
	} // else { showRows1(callerId + ": Show databases result", dbResult) }

	if dbResult != nil {
		for dbResult.Next() {
			var name string
			dbResult.Scan(&name)
			fmt.Println(callerId,":SHOW DATABASE: ", name)
		}
		dbResult.Close()
	}
	return dbResult, err1
}
//====================================================================================
//
//====================================================================================

func Escape(sql string) string {
	// TODO change buffer size handling .....
	dest := make([]byte, 0, len(sql) + 200)
	var escape byte
	for i := 0; i < len(sql); i++ {
		c := sql[i]

		escape = 0

		switch c {
		case 0: /* Must be escaped for 'mysql' */
			escape = '0'
			break
		case '\n': /* Must be escaped for logs */
			escape = 'n'
			break
		case '\r':
			escape = 'r'
			break
		case '\\':
			escape = '\\'
			break
		case '\'':
			escape = '\''
			break
		case '"': /* Better safe than sorry */
			escape = '"'
			break
		case '\032': /* This gives problems on Win32 */
			escape = 'Z'
		}

		if escape != 0 {
			dest = append(dest, '\\', escape)
		} else {
			dest = append(dest, c)
		}
	}

	return string(dest)
}

func Hash_file_md5(filePath string) (string, error) {
	var returnMD5String string
	file, err := os.Open(filePath)
	if err != nil {
		return returnMD5String, err
	}
	defer file.Close()
	hash := md5.New()
	if _, err := io.Copy(hash, file); err != nil {
		return returnMD5String, err
	}
	hashInBytes := hash.Sum(nil)[:16]
	returnMD5String = hex.EncodeToString(hashInBytes)
	return returnMD5String, nil

}
