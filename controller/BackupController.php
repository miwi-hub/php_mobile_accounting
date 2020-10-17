<?php
/*
 * Copyright (c) 2013 by Wolfgang Wiedermann
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; version 3 of the
 * License.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
 * USA
 */

class BackupController {

private $dispatcher, $mandant_id;

# Einsprungpunkt, hier übergibt das Framework
function invoke($action, $request, $dispatcher) {
    $this->dispatcher = $dispatcher;
    $this->mandant_id = $dispatcher->getMandantId();
	
    switch($action) {
        case "sqlbackup":
            return $this->db_backup($request);
        default:
            throw new ErrorException("Unbekannte Action");
    }
}

# Erstellt ein Datenbankbackup (Insert-Statements) von 
# den Buchungen und Konten des aktuell angemeldeten Mandanten
function getMysqlBackup($request) {
    $db = getDBConnection();
    $backup_sql = $this->getKontenBackup($db);
    $backup_sql .= $this->getBuchungenBackup($db);
    mysqli_close($db);

    $result = gzencode($backup_sql);
    return wrap_response($result, "gz");
}

# Insert-Statements für alle Buchungen des Mandanten generieren
private function getBuchungenBackup($db) {
    $sql = "select mandant_id, buchungsnummer, buchungstext, sollkonto, habenkonto, ";
    $sql .= "betrag, datum, bearbeiter_user_id, is_offener_posten ";
    $sql .= "from fi_buchungen ";
    $sql .= "where mandant_id = $this->mandant_id";

    $result = "";
    $rs = mysqli_query($db, $sql);

    while($obj = mysqli_fetch_object($rs)) {
        $result .= "insert into fi_buchungen (mandant_id, buchungsnummer, buchungstext, sollkonto, habenkonto, ";
        $result .= "betrag, datum, bearbeiter_user_id, is_offener_posten) values ";
        $result .= "(".$obj->mandant_id.", ".$obj->buchungsnummer.", ";
        $result .= "'".mysqli_escape_string($db, $obj->buchungstext)."', ";
        $result .= "'".mysqli_escape_string($db, $obj->sollkonto)."', ";
        $result .= "'".mysqli_escape_string($db, $obj->habenkonto)."', ";
        $result .= "".$obj->betrag.", '".$obj->datum."', ";
        $result .= "".$obj->bearbeiter_user_id.", ".$obj->is_offener_posten."); \n";
    }
    mysqli_free_result($rs);
    return $result;
}

# Insert-Statements für alle Konten des Mandanten generieren
private function getKontenBackup($db) {
    $sql = "select mandant_id, kontonummer, bezeichnung, kontenart_id ";
    $sql .= "from fi_konto ";
    $sql .= "where mandant_id = $this->mandant_id";

    $result = "";
    $rs = mysqli_query($db, $sql);

    while($obj = mysqli_fetch_object($rs)) {
        $result .= "insert into fi_konto (mandant_id, kontonummer, bezeichnung, kontenart_id) values ";
        $result .= "(".$obj->mandant_id.", ";
        $result .= "'".mysqli_escape_string($db, $obj->kontonummer)."', ";
        $result .= "'".mysqli_escape_string($db, $obj->bezeichnung)."', ";
        $result .= "".$obj->kontenart_id."); \n";
    }
    mysqli_free_result($rs);
    return $result;
}
}

private function db_backup( ) {

//put table names you want backed up in this array.
//leave empty to do all
$tables = array();
$DBH = getPDOConnection( );
backup_tables($DBH, $tables);	
}

private function backup_tables($DBH, $tables) {

$DBH->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL );

//Script Variables
$compression = false;
$BACKUP_PATH = "";
$nowtimename = time();


//create/open files
if ($compression) {
$zp = gzopen($BACKUP_PATH.$nowtimename.'.sql.gz', "a9");
} else {
$handle = fopen($BACKUP_PATH.$nowtimename.'.sql','a+');
}


//array of all database field types which just take numbers 
$numtypes=array('tinyint','smallint','mediumint','int','bigint','float','double','decimal','real');

//get all of the tables
if(empty($tables)) {
$pstm1 = $DBH->query('SHOW TABLES');
while ($row = $pstm1->fetch(PDO::FETCH_NUM)) {
$tables[] = $row[0];
}
} else {
$tables = is_array($tables) ? $tables : explode(',',$tables);
}

//cycle through the table(s)

foreach($tables as $table) {
$result = $DBH->query("SELECT * FROM $table");
$num_fields = $result->columnCount();
$num_rows = $result->rowCount();

$return="";
//uncomment below if you want 'DROP TABLE IF EXISTS' displayed
//$return.= 'DROP TABLE IF EXISTS `'.$table.'`;'; 


//table structure
$pstm2 = $DBH->query("SHOW CREATE TABLE $table");
$row2 = $pstm2->fetch(PDO::FETCH_NUM);
$ifnotexists = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $row2[1]);
$return.= "\n\n".$ifnotexists.";\n\n";


if ($compression) {
gzwrite($zp, $return);
} else {
fwrite($handle,$return);
}
$return = "";

//insert values
if ($num_rows){
$return= 'INSERT INTO `'."$table"."` (";
$pstm3 = $DBH->query("SHOW COLUMNS FROM $table");
$count = 0;
$type = array();

while ($rows = $pstm3->fetch(PDO::FETCH_NUM)) {

if (stripos($rows[1], '(')) {$type[$table][] = stristr($rows[1], '(', true);
} else $type[$table][] = $rows[1];

$return.= "`".$rows[0]."`";
$count++;
if ($count < ($pstm3->rowCount())) {
$return.= ", ";
}
}

$return.= ")".' VALUES';

if ($compression) {
gzwrite($zp, $return);
} else {
fwrite($handle,$return);
}
$return = "";
}
$count =0;
while($row = $result->fetch(PDO::FETCH_NUM)) {
$return= "\n\t(";

for($j=0; $j<$num_fields; $j++) {

//$row[$j] = preg_replace("\n","\\n",$row[$j]);


if (isset($row[$j])) {

//if number, take away "". else leave as string
if ((in_array($type[$table][$j], $numtypes)) && (!empty($row[$j]))) $return.= $row[$j] ; else $return.= $DBH->quote($row[$j]); 

} else {
$return.= 'NULL';
}
if ($j<($num_fields-1)) {
$return.= ',';
}
}
$count++;
if ($count < ($result->rowCount())) {
$return.= "),";
} else {
$return.= ");";

}
if ($compression) {
gzwrite($zp, $return);
} else {
fwrite($handle,$return);
}
$return = "";
}
$return="\n\n-- ------------------------------------------------ \n\n";
if ($compression) {
gzwrite($zp, $return);
} else {
fwrite($handle,$return);
}
$return = "";
}



$error1= $pstm2->errorInfo();
$error2= $pstm3->errorInfo();
$error3= $result->errorInfo();
echo $error1[2];
echo $error2[2];
echo $error3[2];

if ($compression) {
gzclose($zp);
} else {
fclose($handle);
}
}
