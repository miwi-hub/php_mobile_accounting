select kontenart_id,
       yearmonth as monat,
       sum(saldo) as saldo
  from fi_ergebnisrechnungen
 where kontenart_id in (3, 4)
   and mandant_id = #mandant_id#
   and yearmonth  = '#yearmonth#' 
 group by kontenart_id,
          yearmonth
union
select '5' as kontenart_id,
       yearmonth as monat,
       b.saldo 
  from ( select yearmonth,
                sum(saldo) as saldo
           from fi_ergebnisrechnungen
          where kontenart_id in (3,4)
            and mandant_id = #mandant_id#
            and yearmonth  = '#yearmonth#'
          group by yearmonth
       ) as b
order by kontenart_id
