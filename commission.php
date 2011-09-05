<?php

function active_table_cell($uid, $txid, $orderid, $sub, $amount, $precision)
{
    $url = "?page=view_order&orderid=$orderid&uid=$uid";
    echo "<td class='active' id='cell_${txid}_${orderid}_$sub' onmouseover='In(\"$orderid\");' onmouseout='Out(\"$orderid\");' onclick='document.location=\"$url\"'>", internal_to_numstr($amount, $precision), "</td>";
}

?>
<div class='content_box'>
<h3>Rates</h3>
<?php
show_commission_rates();
echo "</div>\n";

echo "<div class='content_box'>\n";
echo "<h3>Commission</h3>\n";

$query = "
    SELECT txid,
           a_orderid, a_amount, a_commission,
           b_orderid, b_amount, b_commission, " .
           sql_format_date("t.timest") . " as timest,
           a.uid as a_uid, b.uid as b_uid
    FROM transactions AS t
    JOIN
        orderbook AS a
    ON
        a.orderid = a_orderid
    JOIN
        orderbook AS b
    ON
        b.orderid = b_orderid
    WHERE a_commission != 0
          OR b_commission != 0
    ORDER BY txid;
";
$result = do_query($query);
$first = true;
$commission_aud_total = $commission_btc_total = '0';
$amount_aud_total = $amount_btc_total = '0';
$cells = array();
while ($row = mysql_fetch_assoc($result)) {
    if ($first) {
        $first = false;
        echo "<table class='display_data'>\n";
        echo "<tr>";
        echo "<th></th>";
        echo "<th style='text-align: center;' colspan=2>AUD</th>";
        echo "<th style='text-align: center;' colspan=2>BTC</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<th>TID</th>";
        echo "<th>Got</th>";
        echo "<th>Fee</th>";
        echo "<th>Got</th>";
        echo "<th>Fee</th>";
        echo "<th>Date</th>";
        echo "</tr>";
    }
    
    $txid = $row['txid'];
    $a_orderid = $row['a_orderid'];
    $a_amount = $row['a_amount'];
    $a_commission = $row['a_commission'];
    $b_orderid = $row['b_orderid'];
    $b_amount = $row['b_amount'];
    $b_commission = $row['b_commission'];
    $timest = $row['timest'];
    $a_uid = $row['a_uid'];
    $b_uid = $row['b_uid'];

    $amount_aud_total = gmp_add($amount_aud_total, $a_amount);
    $amount_btc_total = gmp_add($amount_btc_total, $b_amount);

    $commission_aud_total = gmp_add($commission_aud_total, $a_commission);
    $commission_btc_total = gmp_add($commission_btc_total, $b_commission);

    if (isset($cells[$a_orderid]))
        array_push($cells[$a_orderid], "'".$txid."'");
    else
        $cells[$a_orderid] = array("'".$txid."'");

    if (isset($cells[$b_orderid]))
        array_push($cells[$b_orderid], "'".$txid."'");
    else
        $cells[$b_orderid] = array("'".$txid."'");

    echo "<tr>";
    echo "<td>$txid</td>";
    active_table_cell($a_uid, $txid, $b_orderid, 'amount', $a_amount    , FIAT_PRECISION);
    active_table_cell($a_uid, $txid, $b_orderid, 'comm',   $a_commission, FIAT_PRECISION);
    active_table_cell($b_uid, $txid, $a_orderid, 'amount', $b_amount    ,  BTC_PRECISION);
    active_table_cell($b_uid, $txid, $a_orderid, 'comm',   $b_commission,  BTC_PRECISION);
    echo "<td>$timest</td>";
    echo "</tr>\n";
}

if (!$first) {
    echo "    <tr>\n";
    echo "        <td></td><td>--------</td><td>--------</td><td>--------</td><td>--------</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "        <td></td>";
    echo "        <td>", internal_to_numstr($amount_aud_total,     FIAT_PRECISION), "</td>";
    echo "        <td>", internal_to_numstr($commission_aud_total, FIAT_PRECISION), "</td>";
    echo "        <td>", internal_to_numstr($amount_btc_total,      BTC_PRECISION), "</td>";
    echo "        <td>", internal_to_numstr($commission_btc_total,  BTC_PRECISION), "</td>";
    echo "    </tr>\n";
    echo "</table>\n";
}

$commissions = fetch_balances('1');
echo "<p>In the commission purse, there is ",
    internal_to_numstr($commissions['AUD'], FIAT_PRECISION), " AUD and ",
    internal_to_numstr($commissions['BTC'],  BTC_PRECISION), " BTC.\n";
echo "Hopefully that matches with the totals shown above.</p>\n";
?>
<script type="text/javascript">
var tx = [];
<?php foreach ($cells as $orderid => $array) {
    echo "tx['$orderid'] = [";
    echo implode($array, ',');
    echo "];\n";
}
?>

function ObjById(id) 
{ 
    if (document.getElementById) 
        var returnVar = document.getElementById(id); 
    else if (document.all) 
        var returnVar = document.all[id]; 
    else if (document.layers) 
        var returnVar = document.layers[id]; 
    return returnVar; 
}

function Color(oid, color)
{
    var txs = tx[oid];
    var endings = ['amount', 'comm'];
    for (var a in txs) {
        var base = "cell_" + txs[a] + "_" + oid + "_";
        for (var ending in endings)
            ObjById(base + endings[ending]).style.backgroundColor=color;
    }
}

function In(oid)
{
    Color(oid, "#8ae3bf");
}

function Out(oid)
{
    Color(oid, "#7ad3af");
}
</script>
</div>
