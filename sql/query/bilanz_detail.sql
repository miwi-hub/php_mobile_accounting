select konto,
       kontenname,
       sum(saldo) as saldo
  from fi_ergebnisrechnungen
 where kontenart_id in (1, 2)
   and mandant_id  = #mandant_id#
   and year <=  #year#
 group by konto,
          kontenname
 order by konto
