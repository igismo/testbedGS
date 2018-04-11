/* mysql manager - talks to mysql server */
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
	"fmt"
	"database/sql"
	"log"
	_ "github.com/go-sql-driver/mysql"
	"testbedGS/common/tbConfiguration"
	"testbedGS/common/tbMessages"
	"testbedGS/common/tbNetUtils"
	"net"
	"strconv"
	"testbedGS/common/tbMsgUtils"
	"os"
	"io"
	"io/ioutil"
	"bufio"
	"testbedGS/common/tbDbaseUtils"
)
//"errors"
//"database/sql/driver"
//"github.com/go-sql-driver/mysql"

type Tag struct {
	ID   int    `json:"id"`
	Name string `json:"name"`
}

var myName                = tbConfig.TBdBaseMasterName
var myFullName            tbMessages.NameId
var myUdpAddress          = new(net.UDPAddr)
var myIpAddress           = ""
var myConnection	      *net.UDPConn = nil
var myState               =  ""
var myCreationTime        = strconv.FormatInt(tbMsgUtils.TBtimestamp(),10)
var mysqlServerIP         = ""
var mysqlServerUdpAddress = new(net.UDPAddr)

var myDbConnection *sql.DB = nil
//====================================================================================
//
//====================================================================================
func main() {
	fmt.Println("Go MySQL Tutorial")
	fmt.Println("TO RUN CLIENT: docker exec -it TB-MYSQLSERVER mysql -uroot -ppassword ")
/*
## START DOCKER MYSQL SERVERSCRIPT:
##-----------------------------------
## create the user network - what if already created ??
docker network create TB-NETWORK
## remove container name from the embedded DNS server
docker rm TB-MYSQLMGR

docker run --name=TB-MYSQLSERVER --network TB-NETWORK \
--mount type=bind,src=/Users/scuric/go/testbed/db/my.cnf,dst=/et/my.cnf \
--mount type=bind,src=/Users/scuric/go/testbed/db/data,dst=/var/lib/mysql \
--mount type=bind,src=/Users/scuric/go/testbed/db/scripts/,dst=/docker-entrypoint-initdb.d/ \
--env MYSQL_DATABASE=tbdb \
--env MYSQL_ROOT_HOST=% \
--env MYSQL_ALLOW_EMPTY_PASSWORD=true \
--env MYSQL_USER=scuric \
--env MYSQL_PASSWORD="" \
-d mysql/mysql-server:latest
*/
/*
	fmt.Println("1111111")
	cmd := exec.Command("/root/myScript.py")
	output, err := cmd.Output()
	if (err != nil) {fmt.Println(err)}
	fmt.Println(string(output))

	fmt.Println("2222222")
	cmd2 := exec.Command("/root/myScript.py")
	cmd2.Stdout = os.Stdout
	cmd2.Stderr = os.Stderr
	log.Println(cmd2.Run())

	fmt.Println("3333333")
	cmd1 := exec.Command("ls","-l","/proj")
	cmd1.Stdout = os.Stdout
	cmd1.Stderr = os.Stderr
	log.Println(cmd1.Run())

	fmt.Printf("Copying %s to %s\n", "/proj/junk", "/proj/junk1")
	//errx := CopyFile(os.Args[1], os.Args[2])
	errx := CopyFile("/proj/junk", "/proj/junk1")
	if errx != nil {
		fmt.Printf("CopyFile failed %q\n", errx)
	} else {
		fmt.Printf("CopyFile succeeded\n")
	}
	*/
	//-------------------------------------------------------------------------------
	// Get our IP and UDP addresses
	myUdpAddress, _ = net.ResolveUDPAddr("udp", tbConfig.TBdBaseMaster)
	myIpAddress = tbNetUtils.GetLocalIp()
	fmt.Println(myName,"dBaseMaster Local IP=", myIpAddress, " My UDP address=", myUdpAddress)
	// Get IP and UDP addresses of MySql Server
	mysqlServerUdpAddress, _ = net.ResolveUDPAddr("udp", tbConfig.TBmysqlServer)
	if mysqlServerUdpAddress != nil {
	mysqlServerIP            = mysqlServerUdpAddress.IP.String()
	}
	//fmt.Println(tbConfig.TBmysqlServerName,": MySqlIP=", mysqlServerIP, " MySqlUDPaddress=", mysqlServerUdpAddress)
	//-------------------------------------------------------------------------------
	// Connect to MysqlSever, change state if successfull, else keep retrying

	myDb, _ :=tbDbaseUtils.DBopenMysql(myName,"scuric",
		"password", "tbdb" ,tbConfig.TBmysqlServer)
	defer myDb.DbaseConnection.Close()

	tbDbaseUtils.TbDbPing(myDb.DbaseConnection)
	tbDbaseUtils.DBshowDatabases(myName, myDb.DbaseConnection)


}
//====================================================================================
// // FOR TESTING ONLY: myTest(myDbConnection)
//====================================================================================
func myTest(dbConnection *sql.DB) {
	tbDbaseUtils.TbDbCreateTable(dbConnection, "CREATE TABLE IF NOT EXISTS test"+" ( id integer, data varchar(32) ) ")
	tbDbaseUtils.TbDbInsertRow(dbConnection, "INSERT INTO test VALUES ( 2, 'data-for-2' )")

	queryResult, err := tbDbaseUtils.TbDbQuery(dbConnection, "SELECT id, data FROM test")
	defer queryResult.Close()

	for queryResult.Next() {
		var tag Tag
		// for each row, scan the result into our tag composite object
		err = queryResult.Scan(&tag.ID, &tag.Name)
		if err != nil {
			fmt.Println("ROW QUERY FAILED")
		} else {
			fmt.Println("ROW:", tag.ID, tag.Name)
		}
	}

	var tag Tag
	rowResult := tbDbaseUtils.TbDbQueryRow(dbConnection,
			"SELECT id, data FROM test where id = ?", 2)
	err7 := rowResult.Scan(&tag.ID, &tag.Name)
	if err7 != nil {
		fmt.Println("ROW SCAN FAILED")
	} else {
		log.Println(tag.ID)
		log.Println(tag.Name)
	}
}
//====================================================================================
//
//====================================================================================
func showResult(txt string, result sql.Result) {
	if result == nil {return}
	res,_ := result.RowsAffected()
		fmt.Println(txt,": Affected=",res)
}
//====================================================================================
//
//====================================================================================
func showRows1(txt string, result *sql.Rows) {

	var col1 []byte
	for result.Next() {
		err := result.Scan(&col1)
		if err != nil {
			panic(err.Error()) // Just for example purpose. You should use proper handling
		}
		fmt.Println(txt,string(col1))
	}
}
//====================================================================================
// Just for reference
//====================================================================================
func create(dbName string) {

	db, err := sql.Open("mysql", "admin:admin@tcp(127.0.0.1:3306)/")
	if err != nil {
		panic(err)
	}
	defer db.Close()

	_,err = db.Exec("CREATE DATABASE " + dbName)
	if err != nil {
		panic(err)
	}

	_,err = db.Exec("USE " + dbName)
	if err != nil {
		panic(err)
	}

	_,err = db.Exec("CREATE TABLE example ( id integer, data varchar(32) )")
	if err != nil {
		panic(err)
	}
}
//====================================================================================
// Copy File
//====================================================================================
// Readln returns a single line (without the ending \n)
// from the input buffered reader.
// An error is returned iff there is an error with the
// buffered reader.
func Readln(r *bufio.Reader) (string, error) {
	var (isPrefix bool = true
		err error = nil
		line, ln []byte
	)
	for isPrefix && err == nil {
		line, isPrefix, err = r.ReadLine()
		ln = append(ln, line...)
	}
	return string(ln),err
}
func ReadAllLines(fi string) {
	f, err := os.Open(fi)
	if err != nil {
		fmt.Printf("error opening file: %v\n", err)
		os.Exit(1)
	}
	r := bufio.NewReader(f)
	s, e := Readln(r)
	for e == nil {
		fmt.Println(s)
		s, e = Readln(r)
	}
}
func readFileWithReadString(fn string) (err error) {
	fmt.Println("readFileWithReadString")

	file, err := os.Open(fn)
	defer file.Close()

	if err != nil {
		return err
	}

	// Start reading from the file with a reader.
	reader := bufio.NewReader(file)

	var line string
	for {
		line, err = reader.ReadString('\n')

		fmt.Printf(" > Read %d characters\n", len(line))

		// Process the line here.
		fmt.Println(" > > " + limitLength(line, 50))

		if err != nil {
			break
		}
	}

	if err != io.EOF {
		fmt.Printf(" > Failed!: %v\n", err)
	}

	return
}
func limitLength(s string, length int) string {
	if len(s) < length {
		return s
	}

	return s[:length]
}
func readFileWithScanner(fn string) (err error) {
	fmt.Println("readFileWithScanner - this will fail!")

	// Don't use this, it doesn't work with long lines...

	file, err := os.Open(fn)
	defer file.Close()

	if err != nil {
		return err
	}

	// Start reading from the file using a scanner.
	scanner := bufio.NewScanner(file)

	for scanner.Scan() {
		line := scanner.Text()

		fmt.Printf(" > Read %d characters\n", len(line))

		// Process the line here.
		fmt.Println(" > > " + limitLength(line, 50))
	}

	if scanner.Err() != nil {
		fmt.Printf(" > Failed!: %v\n", scanner.Err())
	}

	return
}
func readFile() {
	data, err := ioutil.ReadFile("text.txt")
	if err != nil {
		return
	}
	fmt.Println(string(data))
}

func writeFile() {
	file, err := os.Create("text.txt")
	if err != nil {
		return
	}
	defer file.Close()

	file.WriteString("test\nhello")
}
//====================================================================================
// Copy File
//====================================================================================
func FileCopy(src, dst string) (int64, error) {
	src_file, err := os.Open(src)
	if err != nil {
		return 0, err
	}
	defer src_file.Close()

	src_file_stat, err := src_file.Stat()
	if err != nil {
		return 0, err
	}

	if !src_file_stat.Mode().IsRegular() {
		return 0, fmt.Errorf("%s is not a regular file", src)
	}

	dst_file, err := os.Create(dst)
	if err != nil {
		return 0, err
	}
	defer dst_file.Close()

	return io.Copy(dst_file, src_file)
	// err = eFile.Sync()
}
//====================================================================================
// copyFileContents copies the contents of the file named src to the file named
// by dst. The file will be created if it does not already exist. If the
// destination file exists, all it's contents will be replaced by the contents
// of the source file.
//====================================================================================
func copyFileContents(src, dst string) (err error) {
	in, err := os.Open(src)
	if err != nil {
		return
	}
	defer in.Close()
	out, err := os.Create(dst)
	if err != nil {
		return
	}
	defer func() {
		cerr := out.Close()
		if err == nil {
			err = cerr
		}
	}()
	if _, err = io.Copy(out, in); err != nil {
		return
	}
	err = out.Sync()
	return
}
//====================================================================================
// CopyFile copies a file from src to dst. If src and dst files exist, and are
// the same, then return success. Otherise, attempt to create a hard link
// between the two files. If that fail, copy the file contents from src to dst.
//====================================================================================
func CopyFile(src, dst string) (err error) {
	sfi, err := os.Stat(src)
	if err != nil {
		return
	}
	if !sfi.Mode().IsRegular() {
		// cannot copy non-regular files (e.g., directories,
		// symlinks, devices, etc.)
		return fmt.Errorf("CopyFile: non-regular source file %s (%q)", sfi.Name(), sfi.Mode().String())
	}
	dfi, err := os.Stat(dst)
	if err != nil {
		if !os.IsNotExist(err) {
			return
		}
	} else {
		if !(dfi.Mode().IsRegular()) {
			return fmt.Errorf("CopyFile: non-regular destination file %s (%q)", dfi.Name(), dfi.Mode().String())
		}
		if os.SameFile(sfi, dfi) {
			return
		}
	}
	if err = os.Link(src, dst); err == nil {
		return
	}
	err = copyFileContents(src, dst)
	return
}
////////////////////////////////////////////////////////////////////////////
//////================
/* JUNK
var createTableStatements = []string {
	`CREATE DATABASE IF NOT EXISTS library DEFAULT CHARACTER SET = 'utf8' DEFAULT COLLATE 'utf8_general_ci';`,
	`USE library;`,
	`CREATE TABLE IF NOT EXISTS books (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(255) NULL,
		author VARCHAR(255) NULL,
		publishedDate VARCHAR(255) NULL,
		imageUrl VARCHAR(255) NULL,
		description TEXT NULL,
		createdBy VARCHAR(255) NULL,
		createdById VARCHAR(255) NULL,
		PRIMARY KEY (id)
	)`,
}

// mysqlDB persists books to a MySQL instance.
type mysqlDB struct {
	conn *sql.DB

	list   *sql.Stmt
	listBy *sql.Stmt
	insert *sql.Stmt
	get    *sql.Stmt
	update *sql.Stmt
	delete *sql.Stmt
}

// Ensure mysqlDB conforms to the BookDatabase interface.
var _BookDatabase = &mysqlDB{}

type MySQLConfig struct {
	// Optional.
	Username, Password string

	// Host of the MySQL instance.
	//
	// If set, UnixSocket should be unset.
	Host string

	// Port of the MySQL instance.
	//
	// If set, UnixSocket should be unset.
	Port int

	// UnixSocket is the filepath to a unix socket.
	//
	// If set, Host and Port should be unset.
	UnixSocket string
}

// dataStoreName returns a connection string suitable for sql.Open.
func (c MySQLConfig) dataStoreName(databaseName string) string {
	var cred string
	// [username[:password]@]
	if c.Username != "" {
		cred = c.Username
		if c.Password != "" {
			cred = cred + ":" + c.Password
		}
		cred = cred + "@"
	}

	if c.UnixSocket != "" {
		return fmt.Sprintf("%sunix(%s)/%s", cred, c.UnixSocket, databaseName)
	}
	return fmt.Sprintf("%stcp([%s]:%d)/%s", cred, c.Host, c.Port, databaseName)
}

// newMySQLDB creates a new BookDatabase backed by a given MySQL server.
func newMySQLDB(config MySQLConfig) (BookDatabase, error) {
	// Check database and table exists. If not, create it.
	if err := config.ensureTableExists(); err != nil {
		return nil, err
	}

	conn, err := sql.Open("mysql", config.dataStoreName("library"))
	if err != nil {
		return nil, fmt.Errorf("mysql: could not get a connection: %v", err)
	}
	if err := conn.Ping(); err != nil {
		conn.Close()
		return nil, fmt.Errorf("mysql: could not establish a good connection: %v", err)
	}

	db := &mysqlDB{
		conn: conn,
	}

	// Prepared statements. The actual SQL queries are in the code near the
	// relevant method (e.g. addBook).
	if db.list, err = conn.Prepare(listStatement); err != nil {
		return nil, fmt.Errorf("mysql: prepare list: %v", err)
	}
	if db.listBy, err = conn.Prepare(listByStatement); err != nil {
		return nil, fmt.Errorf("mysql: prepare listBy: %v", err)
	}
	if db.get, err = conn.Prepare(getStatement); err != nil {
		return nil, fmt.Errorf("mysql: prepare get: %v", err)
	}
	if db.insert, err = conn.Prepare(insertStatement); err != nil {
		return nil, fmt.Errorf("mysql: prepare insert: %v", err)
	}
	if db.update, err = conn.Prepare(updateStatement); err != nil {
		return nil, fmt.Errorf("mysql: prepare update: %v", err)
	}
	if db.delete, err = conn.Prepare(deleteStatement); err != nil {
		return nil, fmt.Errorf("mysql: prepare delete: %v", err)
	}

	return db, nil
}

// Close closes the database, freeing up any resources.
func (db *mysqlDB) Close() {
	db.conn.Close()
}

// rowScanner is implemented by sql.Row and sql.Rows
type rowScanner interface {
	Scan(dest ...interface{}) error
}

// scanBook reads a book from a sql.Row or sql.Rows
func scanBook(s rowScanner) (*Book, error) {
	var (
		id            int64
		title         sql.NullString
		author        sql.NullString
		publishedDate sql.NullString
		imageURL      sql.NullString
		description   sql.NullString
		createdBy     sql.NullString
		createdByID   sql.NullString
	)
	if err := s.Scan(&id, &title, &author, &publishedDate, &imageURL,
		&description, &createdBy, &createdByID); err != nil {
		return nil, err
	}

	book := &Book{
		ID:            id,
		Title:         title.String,
		Author:        author.String,
		PublishedDate: publishedDate.String,
		ImageURL:      imageURL.String,
		Description:   description.String,
		CreatedBy:     createdBy.String,
		CreatedByID:   createdByID.String,
	}
	return book, nil
}

const listStatement = `SELECT * FROM books ORDER BY title`

// ListBooks returns a list of books, ordered by title.
func (db *mysqlDB) ListBooks() ([]*Book, error) {
	rows, err := db.list.Query()
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var books []*Book
	for rows.Next() {
		book, err := scanBook(rows)
		if err != nil {
			return nil, fmt.Errorf("mysql: could not read row: %v", err)
		}

		books = append(books, book)
	}

	return books, nil
}

const listByStatement = `
  SELECT * FROM books
  WHERE createdById = ? ORDER BY title`

// ListBooksCreatedBy returns a list of books, ordered by title, filtered by
// the user who created the book entry.
func (db *mysqlDB) ListBooksCreatedBy(userID string) ([]*Book, error) {
	if userID == "" {
		return db.ListBooks()
	}

	rows, err := db.listBy.Query(userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var books []*Book
	for rows.Next() {
		book, err := scanBook(rows)
		if err != nil {
			return nil, fmt.Errorf("mysql: could not read row: %v", err)
		}

		books = append(books, book)
	}

	return books, nil
}

const getStatement = "SELECT * FROM books WHERE id = ?"

// GetBook retrieves a book by its ID.
func (db *mysqlDB) GetBook(id int64) (*Book, error) {
	book, err := scanBook(db.get.QueryRow(id))
	if err == sql.ErrNoRows {
		return nil, fmt.Errorf("mysql: could not find book with id %d", id)
	}
	if err != nil {
		return nil, fmt.Errorf("mysql: could not get book: %v", err)
	}
	return book, nil
}

const insertStatement = `
  INSERT INTO books (
    title, author, publishedDate, imageUrl, description, createdBy, createdById
  ) VALUES (?, ?, ?, ?, ?, ?, ?)`

// AddBook saves a given book, assigning it a new ID.
func (db *mysqlDB) AddBook(b *Book) (id int64, err error) {
	r, err := execAffectingOneRow(db.insert, b.Title, b.Author, b.PublishedDate,
		b.ImageURL, b.Description, b.CreatedBy, b.CreatedByID)
	if err != nil {
		return 0, err
	}

	lastInsertID, err := r.LastInsertId()
	if err != nil {
		return 0, fmt.Errorf("mysql: could not get last insert ID: %v", err)
	}
	return lastInsertID, nil
}

const deleteStatement = `DELETE FROM books WHERE id = ?`

// DeleteBook removes a given book by its ID.
func (db *mysqlDB) DeleteBook(id int64) error {
	if id == 0 {
		return errors.New("mysql: book with unassigned ID passed into deleteBook")
	}
	_, err := execAffectingOneRow(db.delete, id)
	return err
}

const updateStatement = `
  UPDATE books
  SET title=?, author=?, publishedDate=?, imageUrl=?, description=?,
      createdBy=?, createdById=?
  WHERE id = ?`

// UpdateBook updates the entry for a given book.
func (db *mysqlDB) UpdateBook(b *Book) error {
	if b.ID == 0 {
		return errors.New("mysql: book with unassigned ID passed into updateBook")
	}

	_, err := execAffectingOneRow(db.update, b.Title, b.Author, b.PublishedDate,
		b.ImageURL, b.Description, b.CreatedBy, b.CreatedByID, b.ID)
	return err
}

// ensureTableExists checks the table exists. If not, it creates it.
func (config MySQLConfig) ensureTableExists() error {
	conn, err := sql.Open("mysql", config.dataStoreName(""))
	if err != nil {
		return fmt.Errorf("mysql: could not get a connection: %v", err)
	}
	defer conn.Close()

	// Check the connection.
	if conn.Ping() == driver.ErrBadConn {
		return fmt.Errorf("mysql: could not connect to the database. " +
			"could be bad address, or this address is not whitelisted for access.")
	}

	if _, err := conn.Exec("USE library"); err != nil {
		// MySQL error 1049 is "database does not exist"
		if mErr, ok := err.(*mysql.MySQLError); ok && mErr.Number == 1049 {
			return createTable(conn)
		}
	}

	if _, err := conn.Exec("DESCRIBE books"); err != nil {
		// MySQL error 1146 is "table does not exist"
		if mErr, ok := err.(*mysql.MySQLError); ok && mErr.Number == 1146 {
			return createTable(conn)
		}
		// Unknown error.
		return fmt.Errorf("mysql: could not connect to the database: %v", err)
	}
	return nil
}

// createTable creates the table, and if necessary, the database.
func createTable(conn *sql.DB) error {
	for _, stmt := range createTableStatements {
		_, err := conn.Exec(stmt)
		if err != nil {
			return err
		}
	}
	return nil
}

// execAffectingOneRow executes a given statement, expecting one row to be affected.
func execAffectingOneRow(stmt *sql.Stmt, args ...interface{}) (sql.Result, error) {
	r, err := stmt.Exec(args...)
	if err != nil {
		return r, fmt.Errorf("mysql: could not execute statement: %v", err)
	}
	rowsAffected, err := r.RowsAffected()
	if err != nil {
		return r, fmt.Errorf("mysql: could not get rows affected: %v", err)
	} else if rowsAffected != 1 {
		return r, fmt.Errorf("mysql: expected 1 row affected, got %d", rowsAffected)
	}
	return r, nil
}
*/



/*

package main

import (
	"database/sql"
	"fmt"
	_ "github.com/go-sql-driver/mysql"
)

func main() {
	// Open database connection
	db, err := sql.Open("mysql", "user:password@/dbname")
	if err != nil {
		panic(err.Error())  // Just for example purpose. You should use proper error handling instead of panic
	}
	defer db.Close()

	// Execute the query
	rows, err := db.Query("SELECT * FROM table")
	if err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}

	// Get column names
	columns, err := rows.Columns()
	if err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}

	// Make a slice for the values
	values := make([]sql.RawBytes, len(columns))

	// rows.Scan wants '[]interface{}' as an argument, so we must copy the
	// references into such a slice
	// See http://code.google.com/p/go-wiki/wiki/InterfaceSlice for details
	scanArgs := make([]interface{}, len(values))
	for i := range values {
		scanArgs[i] = &values[i]
	}

	// Fetch rows
	for rows.Next() {
		// get RawBytes from data
		err = rows.Scan(scanArgs...)
		if err != nil {
			panic(err.Error()) // proper error handling instead of panic in your app
		}

		// Now do something with the data.
		// Here we just print each column as a string.
		var value string
		for i, col := range values {
			// Here we can check if the value is nil (NULL value)
			if col == nil {
				value = "NULL"
			} else {
				value = string(col)
			}
			fmt.Println(columns[i], ": ", value)
		}
		fmt.Println("-----------------------------------")
	}
	if err = rows.Err(); err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}
}
 */

 /*
 IGNORING NULL VALUES
 var col1, col2 []byte

for rows.Next() {
	// Scan the value to []byte
	err = rows.Scan(&col1, &col2)

	if err != nil {
		panic(err.Error()) // Just for example purpose. You should use proper error handling instead of panic
	}

	// Use the string value
	fmt.Println(string(col1), string(col2))
}
  */

  /* USING PREPARE
  package main

import (
	"database/sql"
	"fmt"
	_ "github.com/go-sql-driver/mysql"
)

func main() {
	db, err := sql.Open("mysql", "user:password@/database")
	if err != nil {
		panic(err.Error())  // Just for example purpose. You should use proper error handling instead of panic
	}
	defer db.Close()

	// Prepare statement for inserting data
	stmtIns, err := db.Prepare("INSERT INTO squareNum VALUES( ?, ? )") // ? = placeholder
	if err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}
	defer stmtIns.Close() // Close the statement when we leave main() / the program terminates

	// Prepare statement for reading data
	stmtOut, err := db.Prepare("SELECT squareNumber FROM squarenum WHERE number = ?")
	if err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}
	defer stmtOut.Close()

	// Insert square numbers for 0-24 in the database
	for i := 0; i < 25; i++ {
		_, err = stmtIns.Exec(i, (i * i)) // Insert tuples (i, i^2)
		if err != nil {
			panic(err.Error()) // proper error handling instead of panic in your app
		}
	}

	var squareNum int // we "scan" the result in here

	// Query the square-number of 13
	err = stmtOut.QueryRow(13).Scan(&squareNum) // WHERE number = 13
	if err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}
	fmt.Printf("The square number of 13 is: %d", squareNum)

	// Query another number.. 1 maybe?
	err = stmtOut.QueryRow(1).Scan(&squareNum) // WHERE number = 1
	if err != nil {
		panic(err.Error()) // proper error handling instead of panic in your app
	}
	fmt.Printf("The square number of 1 is: %d", squareNum)
}

   */
