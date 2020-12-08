select kontenart_id, 
       sum(saldo) as saldo
  from fi_ergebnisrechnungen
 where kontenart_id in (1, 2)
   and mandant_id = #mandant_id#
   and year       <= #year#
 group by kontenart_id
union
select '5' as kontenart_id,
       b.saldo
  from ( select sum(saldo) as saldo
           from fi_ergebnisrechnungen
          where kontenart_id in (1,2)
            and mandant_id = #mandant_id#
            and year       <= #year# 
       ) as b
