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

# Die Klasse Dispatcher kapselt die Funktionalitaet,
# zur Weiterleitung der Anfragen an die passenden
# Controller-Klassen.
class Dispatcher {
# Attribute
private $request, $user, $user_id, $mandant;

# Einstiegspunkt in den Dispatcher
# (wird automatisch aus ../index.php aufgerufen)
function invoke($request) {
    $this->request = $request;
    if($this->isValidControllerName()) {
        $controller = $this->getControllerObject();
        $action = $this->getActionName();
        $filename = "export.csv";
        $response = $controller->invoke($action, $this->request, $this);

        if(isset($response->format)) 
        if($response->format == "csv") {
            # Wenn der Action-Name keine ungültigen Zeichen enthält
            # diesen als Dateinamen für den Export verwenden.
            if($this->isValidActionName($action)) {
                $filename = $action.".csv";
            }
            # HTTP-Header auf text/csv stellen
            header("Content-type: text/csv");
            header("Content-Disposition: attachment; filename=$filename");
            header("Pragma: no-cache");
	        return $this->csvEncode($response->obj);
        } else if($response->format == "gz") { 
            # Wenn der Action-Name keine ungültigen Zeichen enthält
            # diesen als Dateinamen für den Export verwenden.
            if($this->isValidActionName($action)) {
                $filename = $action.".gz";
            } else {
                $filename = "export.gz";
            }
            # HTTP-Header auf application/x-gzip stellen
            header("Content-type: application/x-gzip");
            header("Content-Disposition: attachment; filename=$filename");
            header("Pragma: no-cache");
	        return $response->obj;
        } else {
            # HTTP-Header auf application/json stellen
            header("Content-type: application/json");
            return json_encode($response->obj);
        }
    } else {
          # HTTP-Header auf application/json stellen
          header("Content-type: application/json");
          return "{'message':'ControllerName enthält ungültige Zeichen'}";
    }    
}

# Ermitteln der Mandant-Nummer des aktuell angemeldeten Benutzers
# (Zur Nutzung im jeweiligen Controller)
function getMandantId() {
    return $this->mandant;
}

# Ermittlung des Benutzernamens des aktuell angemeldeten Benutzers
# (Zur Nutzung im jeweiligen Controller)
function getUser() {
    return $this->user;
}

# Ermittlung der Benutzer-ID des aktuell angemeldeten Benutzers
# (Zur Nutzung im jeweiligen Controller)
function getUserId() {
    return $this->user_id;
}

# Methode zum Encodieren von Arrays in *.csv-Strings
function csvEncode($data) {
    $csv = ""; $header = ""; $id = 0;
    foreach($data as $line) {
        $count = count((array)$line)-1;
        $i = 0;
        foreach($line as $key => $value) {
            if($id == 0) {
                 $header .= $this->removeIllegalCsvChars($key);
                 if($i < $count) {
                     $header .= ";";
                 }
            }
            $csv .= $this->removeIllegalCsvChars($value);
            if($i < $count) {
                $csv .= ";";
            }
            $i++;
        }    
        $csv .= "\n";
        $id++;
    }
    return $header."\n".$csv; 
}

# Methode zum entfernen von Strichpunkten und Newlines aus Strings
function removeIllegalCsvChars($string) {
    return str_replace("\n", " ", str_replace(";", " ", $string));
}

# Methode zum uebergeben des Benutzers an den Dispatcher
# ermittelt den zugeordneten Mandanten und setzt dessen id in das Feld $this->mandant_id
function setRemoteUser($user) {
    if($this->isValidUserName($user)) {
	try {
        $db = getPDOConnection();
        $this->user = $user;
	$stmt = $db->prepare('select mandant_id, user_id from fi_user where user_name = ?');    
        $rs = $stmt->execute(array($user));
	while ( $rs = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->mandant = $rs->mandant_id;
            $this->user_id = $rs->user_id;
        } catch (PDOException $e) {
            print $e->getMessage();
            throw new Exception("Kein Mandant für den Benutzer $user konfiguriert");
        }
        $stmt->closeCursor();
    } else {
        throw new Exception("Der Benutzername enthält ungültige Zeichen");
    }
}

# Ein Objekt der angefragten Controller-Klasse laden
function getControllerObject() {
    $name = $this->getControllerString();
    if($this->isValidControllerName()) {
        $fileName = "./controller/".ucwords($name)."Controller.php";
        require_once($fileName);
        $className = ucwords($name)."Controller";
        $obj = new $className;
        return $obj;
    } else {
        throw new ErrorException("Controller-Name ungueltig");
    }
}

# Den angefragten Controller ermitteln
function getControllerString() {
    return $this->request['controller'];
} 

# Prüft, ob der ControllerName keine ungültigen Zeichen enthält
function isValidControllerName() {
    $pattern = '/[^a-zA-Z0-9]/';
    preg_match($pattern, $this->getControllerString(), $results);
    return count($results) == 0;
}

# Prüft, ob der Bezeichner der Action keine ungültigen Zeichen enthält
function isValidActionName($action) {
    $pattern = '/[^a-zA-Z0-9]/';
    preg_match($pattern, $action, $results);
    return count($results) == 0;
}

# Prüft, ob der Benutzername keine ungültigen Zeichen enthält
function isValidUserName($username) {
    $pattern = "/[']/";
    preg_match($pattern, $username, $results);
    return count($results) == 0;
}

# Die angefragte Action ermitteln
function getActionName() {
    return $this->request['action'];
}

}

?>
