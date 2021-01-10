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

class BuchungController {

private $dispatcher, $mandant_id;

# Einsprungpunkt, hier übergibt das Framework
function invoke($action, $request, $dispatcher) {

    $this->dispatcher = $dispatcher;
    $this->mandant_id = $dispatcher->getMandantId();

    switch($action) {
        case "create":
            return $this->createBuchung($request);
        case "aktuellste":
            return $this->getTop25();
        case "listbykonto":
            return $this->getListByKonto($request);
        case "listoffeneposten":
            return $this->getOpList();
        case "closeop":
            return $this->closeOpAndGetList($request);
        default:
            $message = array();
            $message['message'] = "Unbekannte Action";
            return $message;
    }
}

# legt das als JSON-Objekt übergebene Konto an
function createBuchung($request) {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode( $inputJSON, TRUE );
    $result = $this->createBuchungInternal($input);
    return $result;
}

# Innerhalb dieses Controllers wiederverwendbare Funktion zum
# Anlegen von Buchungen
private function createBuchungInternal($input) {
    if($this->isValidBuchung($input)) {
        if($input['is_offener_posten']) {
            $temp_op = 1;
        } else {
            $temp_op = 0;
        }

        $sql = "insert into fi_buchungen (mandant_id, buchungstext, sollkonto, habenkonto, "
            ."betrag, datum, bearbeiter_user_id, is_offener_posten)"
            ." values ($this->mandant_id, '".$input['buchungstext']
            ."', '".$input['sollkonto']."', '".$input['habenkonto']."', ".$input['betrag'].", '"
            .$input['datum']."', ".$this->dispatcher->getUserId().", ".$temp_op.")";
        $db = getPdoConnection();
        $db->beginTransaction();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $db->commit();
        $empty = array();
        return wrap_response($empty, "json");
    } else {
        throw new ErrorException("Das Buchungsobjekt enthält nicht gültige Elemente");
    }

}

# liest die aktuellsten 25 Buchungen aus
function getTop25() {
    $db = getPdoConnection();
    $top = array();
    $sql = "select * from fi_buchungen "
        ."where mandant_id = $this->mandant_id "
        ."order by buchungsnummer desc limit 25";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))  {
        $top[] = $row;
    }
    
    $stmt->closeCursor();
    return wrap_response($top);
}

# liest die offenen Posten aus
function getOpList() {
    $db = getPdoConnection();
    $op = array();
    $sql = "select * from fi_buchungen "
        ."where mandant_id = $this->mandant_id "
        ."and is_offener_posten = 1 "
        ."order by buchungsnummer";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//workaround, da Anwendung noch nicht mit Währung umgehen kann
//        $row['betrag'] = str_replace("$","",$row['betrag']);
$row['betrag'] = filter_var($row['betrag'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $op[] = $row;
    }

    $stmt->closeCursor();
    return wrap_response($op);
}

# liest die offenen Posten aus
function closeOpAndGetList($request) {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode( $inputJSON, TRUE );
    if($this->isValidOPCloseRequest($input)) {
        try {
            // Buchung anlegen
            $buchung = $input['buchung'];
            $this->createBuchungInternal($buchung);
            // Offener-Posten-Flag auf false setzen
            $buchungsnummer = $input['offenerposten'];
            if (is_numeric($buchungsnummer)) {
                $sql  = "update fi_buchungen set is_offener_posten = 0";
                $sql .= " where mandant_id = $this->mandant_id"; 
                $sql .= " and buchungsnummer = $buchungsnummer";
                $db = getPdoConnection();
                $db->beginTransaction();
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $db->commit();
            }
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        // Aktualisierte Offene-Posten-Liste an den Client liefern
   	 $db = getPdoConnection();
   	 $op = array();
   	 $sql = "select * from fi_buchungen "
       		 ."where mandant_id = $this->mandant_id "
       		 ."and is_offener_posten = 1 "
       		 ."order by buchungsnummer";
   	 $stmt = $db->prepare($sql);
   	 $stmt->execute();
   	 while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//workaround, da Anwendung noch nicht mit Währung umgehen kann
//        $row['betrag'] = str_replace("$","",$row['betrag']);
		$row['betrag'] = filter_var($row['betrag'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
       		 $op[] = $row;
   	 }
   	 $stmt->closeCursor();
         return wrap_response($op);
    } else {
        throw new ErrorException("Der OP-Close-Request ist ungültig!");
    }
}

function getListByKonto($request) {
    $db = getPdoConnection();
    $kontonummer = $request['konto'];
    $jahr = $request['jahr'];
    # Nur verarbeiten, wenn konto eine Ziffernfolge ist, um SQL-Injections zu vermeiden
    if(is_numeric($kontonummer) && is_numeric($jahr)) {

        $result = array();
        $result_list = array(); 

        // Buchungen laden
        $sql =  "SELECT buchungsnummer, buchungstext, habenkonto as gegenkonto, betrag, datum, is_offener_posten ";
        $sql .= "FROM fi_buchungen "; 
        $sql .= "WHERE mandant_id = $this->mandant_id and sollkonto = '$kontonummer' and to_char(datum,'YYYY') = '$jahr' ";
        $sql .= "union ";
        $sql .= "select buchungsnummer, buchungstext, sollkonto as gegenkonto, betrag*-1 as betrag, datum, is_offener_posten ";
        $sql .= "from fi_buchungen ";
        $sql .= "where mandant_id = $this->mandant_id and habenkonto = '$kontonummer' and to_char(datum,'YYYY') = '$jahr' ";
        $sql .= "order by buchungsnummer desc";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result_list[] = $row;
        }

        $stmt->closeCursor();
        $result['list'] = $result_list;

        // Kontenart bestimmen

        $sql = "select kontenart_id "
            .  "  from fi_konto "
            . " where mandant_id  = $this->mandant_id "
            . "   and kontonummer = '$kontonummer'"; 
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $kontenart_id = $stmt->fetchAll();
        $stmt->closeCursor();

        // Saldo laden: 
	if($kontenart_id[0]['kontenart_id'] === 3 || $kontenart_id[0]['kontenart_id'] === 4 ) {
          $cond = " and year = $jahr ";
        } else {
            $cond = "";
          }

        $sql =  "select sum(saldo) as saldo from fi_ergebnisrechnungen ";
        $sql .= "where mandant_id = $this->mandant_id and konto = '$kontonummer' ";
        $sql .= $cond;
        $stmt = $db->prepare($sql);
        $stmt->execute();
        while ($obj = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['saldo'] = $obj['saldo'];
        }
        $stmt->closeCursor();
        return wrap_response($result);
    # Wenn konto keine Ziffernfolge ist, leeres Ergebnis zurück liefern
    } else {
        throw new ErrorException("Die Kontonummer ist nicht numerisch");
    }
}

# -----------------------------------------------------
# Eingabevalidierung
# -----------------------------------------------------

# Validiert ein Buchungsobjekt und prüft die Gültigkeit
# der einzelnen Felder des Objekts
function isValidBuchung($buchung) {
    if(count($buchung) < 6 && count($buchung) > 7) {
        return false;
    }
    foreach($buchung as $key => $value) {
        if(!$this->isInValidFields($key)) return false;
        if(!$this->isValidValueForField($key, $value)) return false;       
    }
    return true;
}

# Validiert ein OPCloseRequest-Objekt und prüft seine
# Gültigkeit (auch die zu schließende Buchungsnummer
# muss größer 0 sein!)
function isValidOPCloseRequest($request) {
    # Hauptgliederung prüfen
    if(!(isset($request['offenerposten'])
         && isset($request['buchung']))) {
        error_log("isValidOPCloseRequest: Hauptgliederung falsch");
        return false;
    }
    $op = $request['offenerposten'];
    $buchung = $request['buchung'];
    # Buchung prüfen
    if(!$this->isValidBuchung($buchung)) {
        error_log("isValidOPCloseRequest: Buchung invalide");
        return false;
    }
    # Offener Posten Buchungsnummer prüfen
    if(is_numeric($op) && $op != 0) {
        return true;
    } else {
        error_log("isValidOPCloseRequest: buchungsnummer == 0");
        error_log(print_r($op,true));
        return false;
    }
}

# Prüft, ob das gegebene Feld in der Menge der
# gueltigen Felder enthalten ist.
function isInValidFields($key) {
   switch($key) {
       case 'mandant_id':       return true;
       case 'buchungsnummer':   return true;
       case 'buchungstext':     return true;
       case 'sollkonto':        return true;
       case 'habenkonto':       return true;
       case 'betrag':           return true;
       case 'datum':            return true;
       case 'datum_de':         return true;
       case 'benutzer':         return true;
       case 'is_offener_posten':return true;
       default:                 return false;
   }
}

# Prüft, ob jeder Feldinhalt valide sein kann
function isValidValueForField($key, $value) {
   switch($key) {
       case 'buchungsnummer':
       case 'mandant_id':
       case 'betrag':
            return is_numeric($value);
       case 'sollkonto':
       case 'habenkonto':
            $pattern = '/[^0-9]/';
            preg_match($pattern, $value, $results);
            return count($results) == 0;
       case 'buchungstext':
       case 'datum':
       case 'datum_de':
            $pattern = '/[\']/';
            preg_match($pattern, $value, $results);
            return count($results) == 0;
       case 'is_offener_posten':
            return $value === false || $value === true;
       default: return true;
   }
}

}

?>
