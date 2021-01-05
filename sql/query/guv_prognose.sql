select konto as kontonummer,
       kontenname as bezeichnung,
       sum (saldo) as betrag
  from fi_ergebnisrechnungen
 where kontenart_id in (3,4)
   and mandant_id = #mandant_id#
   and yearmonth  = '#yearmonth#'
 group by konto,
          kontenname
 order by konto 

