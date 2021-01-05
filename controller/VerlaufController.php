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

class VerlaufController {

private $dispatcher, $mandant_id;

# Einsprungpunkt, hier übergibt das Framework
function invoke($action, $request, $dispatcher) {
    $this->dispatcher = $dispatcher;
    $this->mandant_id = $dispatcher->getMandantId();
    switch($action) {
        case "monatssalden":
            return $this->getMonatsSalden($request['id']);
        case "cashflow":
            return $this->getCashFlow($request['id'], $request['side']);
        case "intramonth":
            return $this->getIntraMonth($request);
        default:
            throw new ErrorException("Unbekannte Action");
    }
}

# Ermittelt die Monats-Salden des Kontos
function getMonatsSalden($kontonummer) {
    if(is_numeric($kontonummer) || $this->is_numeric_list($kontonummer)) {
        $kto_prepared = $this->prepareKontoNummern($kontonummer);
        try {
          $db = getPdoConnection();
          $rechnungsart = $this->getRechnungsart($kto_prepared);
//        if($rechnungsart != 0) {
/*
           if($rechnungsart == 2) {
                // Monatssummen, fuer Aufwands- und Ertragskonten
                $sql = "select groupingx, sum(saldo)*-1 as saldo from "
                      ."(select groupingx, konto, sum(betrag) as saldo from "
                      ."(select (year(v.datum)*100)+month(v.datum) as groupingx, v.konto, v.betrag "
                      ."from fi_ergebnisrechnungen_base v inner join fi_konto kt "
                      ."on v.konto = kt.kontonummer and v.mandant_id = kt.mandant_id "
                      ."where v.mandant_id = $this->mandant_id "
                      ."and v.gegenkontenart_id <> 5) as x "
                      ."group by groupingx, konto) as y "
                      ."where y.konto in ($kto_prepared) " 
                      ."and y.groupingx > ((year(now())*100)+month(now()))-100 "
                      ."group by groupingx ";

            } else if($rechnungsart == 1) {
                // Laufende Summen, fuer Bestandskonten
                $sql = "select x1.groupingx, sum(x2.betrag) as saldo "
                      ."from (select distinct (year(datum)*100)+month(datum) as groupingx from fi_buchungen_view "
                      ."where mandant_id = '$this->mandant_id') x1 "
                      ."inner join (select (year(datum)*100+month(datum)) as groupingx, konto, betrag "
                      ."from fi_buchungen_view where mandant_id = '$this->mandant_id') x2 "
                      ."on x2.groupingx <= x1.groupingx "
                      ."where konto in ($kto_prepared) and x1.groupingx > ((year(now())*100)+month(now()))-100 "
                      ."group by groupingx";

            } */
            if($rechnungsart == 1) {
            $sql = "select yearmonth as groupingx, "
                  ."( select sum(saldo) from fi_ergebnisrechnungen "
                  ."   where yearmonth <= base.yearmonth "
                  ."     and konto in ($kto_prepared) "
                  ."     and mandant_id = '$this->mandant_id' ) as saldo " 
                  ."from fi_ergebnisrechnungen as base "
                  ."where konto in ($kto_prepared) "
                  ."  and mandant_id = '$this->mandant_id' "  
                  ."group by yearmonth "
                  ."order by yearmonth desc"; }
            if($rechnungsart == 2) {
            $sql = "select yearmonth as groupingx, "
                  ."       sum(saldo) as saldo "
                  ."from fi_ergebnisrechnungen "
                  ."where konto in ($kto_prepared) "
                  ."  and mandant_id = '$this->mandant_id' "  
                  ."group by yearmonth "
                  ."order by yearmonth desc"; }
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = array();
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $row;
            }

            $stmt->closeCursor();
            return wrap_response($result);
        } catch (Exception $e) {
          throw $e;
        }
  //      } else {
  //          throw new Exception("Mindestens eine Kontonummer ist unbekannt");
//        }
   } else throw new Exception("Mindestens eine Kontonummer ist nicht numerisch");

}

# Ermittelt die monatlichen Werte des Zu- oder Abfluss 
# (side = S => Sollbuchungen)
# (side = H => Habenbuchungen)
# von Aktivkonten. Bei anderen Kontenarten wird eine
# Exception zurückgeliefert
function getCashFlow($kontonummer, $side) {
    $values = array();
    $db = getPdoconnection();
/*    if($this->isAktivKonto($kontonummer)) {
        $db = getPdoConnection();
        
        if($side == 'S') {
            $sql  = "select (year(datum)*100)+month(datum) as groupingx, sum(b.betrag) as saldo ";
            $sql .= "from fi_buchungen as b ";
            $sql .= " inner join fi_konto as k ";
            $sql .= " on k.mandant_id = b.mandant_id and k.kontonummer = b.habenkonto ";
            $sql .= " where b.mandant_id = ".$this->mandant_id;
            $sql .= " and b.sollkonto = '".$kontonummer."' ";
            $sql .= " and year(b.datum) >= year(now())-1 ";
            $sql .= " and year(b.datum) <= year(now()) ";
            $sql .= " and k.kontenart_id <> 5 ";
            $sql .= "group by (year(b.datum)*100)+month(b.datum);";
        } else if($side == 'H') {
            $sql  = "select (year(b.datum)*100)+month(b.datum) as groupingx, sum(b.betrag) as saldo ";
            $sql .= "from fi_buchungen as b ";
            $sql .= " inner join fi_konto as k ";
            $sql .= " on k.mandant_id = b.mandant_id and k.kontonummer = b.sollkonto ";
            $sql .= " where b.mandant_id = ".$this->mandant_id;
            $sql .= " and b.habenkonto = '".$kontonummer."' ";
            $sql .= " and year(b.datum) >= year(now())-1 ";
            $sql .= " and year(b.datum) <= year(now()) ";
            $sql .= " and k.kontenart_id <> 5 ";
            $sql .= "group by (year(b.datum)*100)+month(b.datum);";
        } else {
            throw new Exception("Gültige Werte für side sind S und H");
        }*/
        $year = date("Y");
        $sql = "select yearmonth as groupingx, "
              ."       sum(betrag) as saldo "
              ."  from fi_buchungen_view "
              ." where mandant_id = ".$this->mandant_id
              ."   and konto      = '".$kontonummer."' "
              ."   and knz        = '".$side."' "
              ." group by yearmonth "
              ." order by yearmonth ASC "
              ." limit 12"; 
        $stmt = $db->prepare($sql);
        $stmt->execute();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values[] = $row;
        }
        $stmt->closeCursor();
//    } else {
//        throw new Exception("getCashFlow ist nur für Aktiv-Konten verfügbar");
//    }
    return wrap_response($values); 
}

# Monats-internen Verlauf ermitteln
function getIntraMonth($request) {
    $db = getPdoConnection();

    if(isset($request['month_id'])) { 
      if($this->is_number($request['month_id'])) {

        $month_id = $request['month_id'];

        $query = new QueryHandler("guv_intramonth_aufwand.sql");
        $query->setParameterUnchecked("mandant_id", $this->mandant_id);
        $query->setParameterUnchecked("month_id", $month_id);
        $sql = $query->getSql();

        $result = array();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $aktuell[$row['tag']] = array( 'tag'      => $row['tag'],
                                       'aktuell'  => $row['saldo'] );
     }

        $stmt->closeCursor();
        $month_id_vm = date("Ym", (mktime( 0 , 0, 0, substr($month_id, 4,2)-1, date("d"), substr($month_id, 0, 4))));
        $query_vm = new QueryHandler("guv_intramonth_aufwand.sql");
        $query_vm->setParameterUnchecked("mandant_id", $this->mandant_id);
        $query_vm->setParameterUnchecked("month_id", $month_id_vm);
        $sql_vm = $query_vm->getSql();

        $stmt = $db->prepare($sql_vm);
        $stmt->execute();
        while($row_vm = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $vormonat[$row_vm['tag']] = array( 'tag'      => $row_vm['tag'],
                                             'vormonat' => $row_vm['saldo'] );  
     }
     $stmt->closeCursor();

// Zusammenführen Vormonat und aktueller Monat
        foreach($aktuell as $aktuell_row) {
          if(isset($vormonat[$aktuell_row['tag']])) {
            $vormonat_betrag = $vormonat[$aktuell_row['tag']]['vormonat'];
          } else {
            $vormonat_betrag = '0';
          } 
          $result[$aktuell_row['tag']] = array( 'tag'      => $aktuell_row['tag'],
                                                'aktuell'  => $aktuell_row['aktuell'],
                                                'vormonat' => $vormonat_betrag );
        }

        foreach($vormonat as $vormonat_row) {
          if(isset($aktuell[$vormonat_row['tag']]['aktuell'])) {
            $aktuell_betrag = $aktuell[$vormonat_row['tag']]['aktuell'];
          } else {
            $aktuell_betrag = '0';
          $result[$vormonat_row['tag']] = array( 'tag'      => $vormonat_row['tag'],
                                                 'aktuell'  => $aktuell_betrag,
                                                 'vormonat' => $vormonat_row['vormonat'] );
          }
        }
$result[] = array( 'vm' => $month_id_vm );
        return wrap_response($result);

      } else {
        return wrap_response("Parameter month_id ist nicht ausschließlich numerisch");
      }
    } else {
        return wrap_response("Parameter month_id fehlt");
    } 
}

# Prüft, ob das angegebene Konto ein Aktiv-Konto ist.
function isAktivKonto($kontonummer) {
    if(!is_numeric($kontonummer)) return false;
    $db = getPdoConnection();
    $sql = "select kontenart_id from fi_konto "
                            ."where mandant_id = ".$this->mandant_id
                            ." and kontonummer = '".$kontonummer."'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $isActive = false;
    if($obj = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $isActive = $obj['kontenart_id'] == 1; // Ist Aktiv-Konto
    }
    $stmt->closeCursor();
    return $isActive; 
}

# Macht aus einer oder mehreren durch Komma getrennten Kontonummern
# ein Array von Kontonummern-Strings und verwirft dabei 
# nichtnumerische Elemente
function kontonummernToArray($value) {
    $list = array();
    if(is_numeric($value)) {
        $list[] = $value;
    } else {
        $tmp = explode(',', $value);
        foreach($tmp as $item) {
            if(is_numeric($item)) {
                $list[] = $item;
            }
        }
    }
    return $list; 
}

# Macht aus einer oder mehreren durch Komma getrennten Kontonummern
# eine passende Liste für SQL-IN
function prepareKontoNummern($value) {
    $list = $this->kontonummernToArray($value);

    $result = "";
    foreach($list as $item) {
        $result .= "'".$item."',";
    }
    $result = substr($result, 0, strlen($result)-1);
    return $result; 
}

# Prüft mittels RegEx ob $value ausschließlich aus Ziffern und Kommas besteht
function is_numeric_list($value) {
    $pattern = '/[^0-9,]/';
    preg_match($pattern, $value, $results);
    return count($results) == 0; 
}

# Prüft mittels RegEx ob der übergebene Wert ausschließlich aus Ziffern besteht
function is_number($value) {
    $pattern = '/[^0-9]/';
    preg_match($pattern, $value, $results);
    return count($results) == 0; 
}

# Ermittelt, ob es sich bei den ausgewählten Konten um 
# eine GUV-Betrachtung (nur Aufwand und Ertrag) oder
# eine Bestandsbetrachtung (nur Aktiv und Passiv) handelt.
function getRechnungsart($kto_prepared) {
    $db = getPdoConnection();
    $kontenarten = array();
    $type = 0;
    $sql = "select distinct kontenart_id from fi_konto where kontonummer in ($kto_prepared)";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    while($obj = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $kontenart_id = $obj['kontenart_id'];
        if($type == 0) {
            // noch ERGEBNISOFFEN
            if($kontenart_id == 1 || $kontenart_id == 2) $type = 1;
            else if($kontenart_id == 3 || $kontenart_id == 4) $type = 2;
        } else if($type == 1) {
            // BESTANDSBETRACHTUNG
            if($kontenart_id == 3 || $kontenart_id == 4) throw new Exception("Falsche Mischung von Kontenarten");
        } else if($type == 2) {
            // GUV-BETRACHTUNG
            if($kontenart_id == 1 || $kontenart_id == 2) throw new Exception("Falsche Mischung von Kontenarten");
        }
    }
    $stmt->closeCursor();
    return $type;
} 

}

?>
