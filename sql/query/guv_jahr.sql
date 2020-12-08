select konto,
       kontenname,
       saldo
  from fi_ergebnisrechnungen
 where kontenart_id in (3,4)
   and mandant_id = #mandant_id#
 order by konto
