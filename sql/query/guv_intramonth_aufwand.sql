select xday as tag,
       ( select sum(betrag)
           from fi_buchungen_view as b
          inner join fi_konto as k
             on b.mandant_id = k.mandant_id
            and b.konto      = k.kontonummer
          where b.mandant_id = #mandant_id# 
            and k.kontenart_id = 3
            and b.yearmonth = '#month_id#'
            and to_number(b.tag, '99') <= base.xday ) as saldo
  from fi_hlp_days as base
 order by xday asc
