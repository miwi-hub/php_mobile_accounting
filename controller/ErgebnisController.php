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

class ErgebnisController {

private $dispatcher, $mandant_id;

# Einsprungpunkt, hier übergibt das Framework
function invoke($action, $request, $dispatcher) {

    $this->dispatcher = $dispatcher;
    $this->mandant_id = $dispatcher->getMandantId();

    switch($action) {
        case "bilanz":
            return $this->getBilanz($request);
        case "guv":
            return $this->getGuV($request);
        case "guv_month":
            return $this->getGuVMonth($request);
        case "guv_prognose":
            return $this->getGuVPrognose();
        case "verlauf":
            return $this->getVerlauf($request);
        case "verlauf_gewinn":
            return $this->getVerlaufGewinn();
        case "months":
            return $this->getMonths();
        case "years":
            return $this->getYears();
        default:
            $message = array();
            $message['message'] = "Unbekannte Action";
            return $message;
    }
}

# Berechnet eine aktuelle Bilanz und liefert
# sie als Array zurück
function getBilanz($request) {
    $result = array();
    $db = getPdoConnection();
    $year = $request['year'];
    if($this->isValidYear($year)) {
        $query = new QueryHandler("bilanz_detail.sql");
        $query->setParameterUnchecked("mandant_id", $this->mandant_id);
        $query->setNumericParameter("year", $year);
 //       $query->setNumericParameter("geschj_start_monat",
 //           get_config_key("geschj_start_monat", $this->mandant_id)->param_value);
        $sql = $query->getSql();

	$stmt = $db->prepare($sql);
	$stmt->execute();

        $zeilen = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $zeilen[] = $row;
        }
        $row = null;
	$stmt->closeCursor();
        $result['zeilen'] = $zeilen;

        $query = new QueryHandler("bilanz_summen.sql");
        $query->setParameterUnchecked("mandant_id", $this->mandant_id);
        $query->setNumericParameter("year", $year);
//        $query->setNumericParameter("geschj_start_monat",
//            get_config_key("geschj_start_monat", $this->mandant_id)->param_value);
        $sql = $query->getSql();
	$stmt = $db->prepare($sql);
        $stmt->execute();
        $ergebnisse = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ergebnisse[] = $row;
        }
        $result['ergebnisse'] = $ergebnisse;
	$stmt->closeCursor();
        return wrap_response($result);
    } else {
        return wrap_response("Fehler aufgetreten, das angegebene Jahr hat ein ungültiges Format");
    }
}

# Berechnet eine aktuelle GuV-Rechnung und liefert
# sie als Array zurück
function getGuV($request) {
    $db = getPdoConnection();
    $year = $request['year'];
    if($this->isValidYear($year)) {

        $query = new QueryHandler("guv_jahr.sql");
        $query->setParameterUnchecked("mandant_id", $this->mandant_id);
        $query->setNumericParameter("jahr_id", $year);
//        $query->setParameterUnchecked("geschj_start_monat",
//            get_config_key("geschj_start_monat", $this->mandant_id)->param_value);
        $sql = $query->getSql();
        $stmt = $db->prepare($sql);
        $stmt->execute(); 
        $zeilen = array();
        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $zeilen[] = $row;
        }
        $stmt->closeCursor();
        $result['zeilen'] = $zeilen;

        $query = new QueryHandler("guv_jahr_summen.sql");
        $query->setParameterUnchecked("mandant_id", $this->mandant_id);
        $query->setNumericParameter("jahr_id", $year);
//        $query->setParameterUnchecked("geschj_start_monat",
//            get_config_key("geschj_start_monat", $this->mandant_id)->param_value);
        $sql2  = $query->getSql();
        $stmt = $db->prepare($sql2);
        $stmt->execute();
        $ergebnisse = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ergebnisse[] = $row;
        }
        $stmt->closeCursor();
        $result['ergebnisse'] = $ergebnisse;
      
        return wrap_response($result);
    } else {
        return wrap_response("Der übergebene Parameter year erfüllt nicht die Formatvorgaben für gültige Jahre");
    }
}

# Berechnet eine GuV-Rechnung fuer das angegebene oder aktuelle Monat
# und liefert sie als Array zurück
function getGuVMonth($request) {
    $month_id = $this->getMonthFromRequest($request);

    $db = getPdoConnection();
    $query = new QueryHandler("guv_monat.sql");
    $query->setParameterUnchecked("mandant_id", $this->mandant_id);
    $query->setParameterUnchecked("yearmonth","'". $month_id ."'");
    $sql = $query->getSql();
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $zeilen = array();
    $result = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $zeilen[] = $row;
    }
    $stmt->closeCursor();
    $result['zeilen'] = $zeilen;

    $query = new QueryHandler("guv_monat_summen.sql");
    $query->setParameterUnchecked("mandant_id", $this->mandant_id);
    $query->setParameterUnchecked("yearmonth", "'". $month_id ."'");
    $sql = $query->getSql();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $ergebnisse = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ergebnisse[] = $row;
    }
    $stmt->closeCursor();
    $result['ergebnisse'] = $ergebnisse;
    

    return wrap_response($result);
}

#
# Laden der GuV-Prognose
# (GuV aktuelles-Monat + Vormonat)
function getGuVPrognose() {
    $db = getPdoConnection();

// Vormonat
    $query = new QueryHandler("guv_prognose.sql");
    $query->setParameterUnchecked("mandant_id", $this->mandant_id);
    $query->setParameterUnchecked("yearmonth", date("Ym", mktime( 0 , 0, 0, date("m")-1, date("d"), date("Y"))));
    $sql = $query->getSql();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vormonat[$row['kontonummer']] = array( 'kontonummer'     => $row['kontonummer'],
                                                'bezeichnung'     => $row['bezeichnung'],
                                                'betrag_vormonat' => $row['betrag'],
                                                'betrag_aktuell'  => 0,
                                                'differenz'       => 0  );
    }
    $stmt->closeCursor();

// aktueller Monat
    $query = new QueryHandler("guv_prognose.sql");
    $query->setParameterUnchecked("mandant_id", $this->mandant_id);
    $query->setParameterUnchecked("yearmonth", date("Ym", mktime( 0 , 0, 0, date("m"), date("d"), date("Y"))));
    $sql = $query->getSql();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $aktuell[$row['kontonummer']] = array( 'kontonummer'     => $row['kontonummer'],
                                               'bezeichnung'     => $row['bezeichnung'],
                                               'betrag_vormonat' => 0,
                                               'betrag_aktuell'  => $row['betrag'],
                                               'differenz'       => 0  );
    }
    $stmt->closeCursor();

// Zusammenführen der Vormonatsergebnissen mit dem aktuellen Monat
    foreach($vormonat as $vormonat_row) {
      if(isset($aktuell[$vormonat_row['kontonummer']])) {
        $betrag_aktuell = $aktuell[$vormonat_row['kontonummer']]['betrag_aktuell'];
      } else {
          $betrag_aktuell = 0;
        }

      $betrag_aktuell_conv = $this->strToCurrency($betrag_aktuell);
      list($betrag_aktuell_symbol, $betrag_aktuell_value) = $betrag_aktuell_conv;
      $betrag_vormonat_conv = $this->strToCurrency($vormonat_row['betrag_vormonat']);
      list($betrag_vormonat_symbol, $betrag_vormonat_value) = $betrag_vormonat_conv; 
      $result['detail'][$vormonat_row['kontonummer']] = array( 'kontonummer'     => $vormonat_row['kontonummer'],
                                                               'bezeichnung'     => $vormonat_row['bezeichnung'],
                                                               'betrag_vormonat' => $vormonat_row['betrag_vormonat'],
                                                               'betrag_aktuell'  => $betrag_aktuell,
                                                               'differenz'       => round( $betrag_aktuell_value - $betrag_vormonat_value, 2) );
    }

    foreach($aktuell as $aktuell_row) {
      if(isset($vormonat[$aktuell_row['kontonummer']])) {
        $betrag_vormonat = $vormonat[$aktuell_row['kontonummer']]['betrag_vormonat'];
      } else {
          $betrag_vormonat = 0;
        }
 $betrag_vormonat_value = 7;
 $betrag_aktuell_value  = 3;

      $betrag_vormonat_conv = $this->strToCurrency($betrag_vormonat);
      list($betrag_vormonat_symbol, $betrag_vormonat_value) = $betrag_vormonat_conv;
      $betrag_aktuell_conv = $this->strToCurrency($aktuell_row['betrag_aktuell']);
      list($betrag_aktuell_symbol, $betrag_aktuell_value) = $betrag_aktuell_conv;

      $result['detail'][$aktuell_row['kontonummer']] = array( 'kontonummer'     => $aktuell_row['kontonummer'],
                                                              'bezeichnung'     => $aktuell_row['bezeichnung'],
                                                              'betrag_vormonat' => $betrag_vormonat,
                                                              'betrag_aktuell'  => $aktuell_row['betrag_aktuell'],
                                                              'differenz'       => round( $betrag_aktuell_value - $betrag_vormonat_value, 2) );
    }

    $query = new QueryHandler("guv_prognose_summen.sql");
    $query->setParameterUnchecked("mandant_id", $this->mandant_id);
    $query->setParameterUnchecked("yearmonth", date("Ym"));
    $sql = $query->getSql();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result['summen'] = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//        $result['summen'][$row['kontenart_id']] = $row;
$result['summen'][] = $row;
    }
    $stmt->closeCursor();
    return wrap_response($result);
}

# Ermittelt aus dem Request und dessen Parameter "id" das ausgewählte Monat
# sofern das möglich ist. Ansonsten wird 'Undef' zurückgegeben
function getMonthFromRequest($request) {
    // Monat aus dem Request auslesen und dann ggf. verwenden (ansonsten das jetzt verwenden)
    $month_id = 'Undef';
    if(array_key_exists('id', $request)) {
        $month_id = $request['id'];
    }
    if(!is_numeric($month_id)) {
        $month_id = date('Ym');
    }
    return $month_id;
}

# Liefert eine Liste der gültigen Monate aus den Buchungen des Mandanten
function getMonths() {
    $db = getPdoConnection();
    $months = array();

    $sql =  "select distinct to_char(datum, 'YYYYMM') as yearmonth ";
    $sql .= " from fi_buchungen where mandant_id = ".$this->mandant_id;
    $sql .= " order by yearmonth desc";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while ( $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $months[] = $row['yearmonth'];
    }
//$months[] = 202001;
    $stmt->closeCursor();
    return wrap_response($months);
}

# Liefert eine Liste der gültigen Jahre aus den Buchungen des Mandanten
function getYears() {
    $db = getPdoConnection();
    $years = array();

    $sql = "select distinct to_char(datum, 'YYYY') as year from fi_buchungen where mandant_id = ".$this->mandant_id." order by year desc";
    $stmt = $db->prepare($sql);	
    $stmt->execute();
    while ( $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $years[] = $row['year'];
    };
    $stmt->closeCursor();
    return wrap_response($years);
}

# Verlauf Aufwand, Ertrag, Aktiva und Passiva in Monatsraster
function getVerlauf($request) {
    $result = array();

    if(!array_key_exists('id', $request)) 
        return $result;

    $kontenart_id = $request['id'];
    if(is_numeric($kontenart_id)) {

        $db = getPdoConnection();
        $sql =  "select yearmonth as groupingx, sum(saldo) as saldo ";
        $sql .= "from fi_ergebnisrechnungen ";
        $sql .= "where kontenart_id = $kontenart_id and mandant_id = $this->mandant_id ";

        # Nur immer die letzten 12 Monate anzeigen
        $sql .= "group by kontenart_id, groupingx ";
        $sql .= "order by groupingx desc ";
        $sql .= "limit 12";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        while ($row =  $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        $stmt->closeCursor();
    } 
    return wrap_response($result);
}

# Verlauf des Gewinns in Monatsraster
function getVerlaufGewinn() {
    $result = array();
    $db = getPdoConnection();

    $sql =  "select yearmonth as groupingx, sum(saldo) as saldo ";
    $sql .= "from fi_ergebnisrechnungen ";
    $sql .= "where kontenart_id in (3, 4) and mandant_id = $this->mandant_id ";

    # Nur immer die letzten 12 Monate anzeigen
    $sql .= "group by groupingx ";
    $sql .= "order by groupingx desc ";
    $sql .= "limit 12";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while ($row =  $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[] = $row;
    }

    $stmt->closeCursor();
    
    return wrap_response($result);
}

# Prüft, ob das Zahlenformat des übergebenen Jahres korrekt ist
function isValidYear($year) {
    // Jahr-Regex: [0-9]{4}
    if(preg_match("/[0-9]{4}/", $year, $matches) == 1) {
        if($matches[0] == $year) {
            return true;
        }
    } 
    return false;
}

# Auslesen Währung aus String
function strToCurrency($str, $validCurrencies=null) {
    if ($validCurrencies === null) {
        return $this->strToCurrency($str,
               array(
                        'EUR' => array('€', 'eur', 'euro'),
                        'USD' => array('$', 'usd', 'dollar', 'us-dollar', 'us dollar'),
                        'JPY' => array('¥', 'jpy', 'yen'),
                        'CHF' => array('chf')
                    )
            );
    }
         
        $currs = array();
        foreach ($validCurrencies as $symbol => $alternatives) {
            foreach ($alternatives as $a) {
                $currs[$a] = $symbol;
            }
        }
         
        $str = mb_strtolower(trim($str));
        $resultFloat = null;
        $resultSymbol = null;
         
        foreach ($currs as $curr=>$symbol) {
            $len = strlen($curr);
            if (substr($str, 0, $len) === $curr) {
                $resultSymbol = $symbol;
                $resultFloat = substr($str, $len);
                break;
            } else if (substr($str, -$len, $len) === $curr) {
                $resultSymbol = $symbol;
                $resultFloat = substr($str, 0, -$len);
                break;
            }
        }
         
        if ($resultSymbol === null) {
            return false;
        } else {
            return array($resultSymbol, $this->strToFloat($resultFloat));
        }
}
     
#Umwandlung String zu Zahl
function strToFloat($str) {
    $pos = strrpos($str = strtr(trim(strval($str)), ',', '.'), '.');
    return ($pos===false ? floatval($str) : floatval(str_replace('.', '', substr($str, 0, $pos)) . substr($str, $pos)));
}


}

?>
