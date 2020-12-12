select konto as "vormonat.konto",
       kontenname as "vormonat.kontobezeichnung",
       sum (saldo) as betrag_vormonat
  from fi_ergebnisrechnungen as vormonat
 where kontenart_id in (3,4)
   and mandant_id = #mandant_id#
   and yearmonth  = '202006'
 group by konto,
          kontenname
 order by konto 

