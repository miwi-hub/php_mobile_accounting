select konto,
       kontenname,
       sum (saldo) as saldo
  from fi_ergebnisrechnungen
 where kontenart_id in (3,4)
   and mandant_id = #mandant_id#
 group by konto,
          kontenname
 order by konto 

