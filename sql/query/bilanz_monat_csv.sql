select mandant_id,
       konto,
       kontenname,
       kontenart_id,
       saldo,
       year,
       yearmonth
  from fi_ergebnisrechnungen
 where kontenart_id in (1,2)
   and mandant_id = #mandant_id#
