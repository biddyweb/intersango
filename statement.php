<?php

function deposited_or_withdrawn($deposit, $withdraw)
{
    $net = gmp_sub($deposit, $withdraw);
    $abs = gmp_abs($net);

    if (gmp_cmp($net, 0) < 0)
        $word = "withdrawn";
    else
        $word = "deposited";

    return array($abs, $word);
}

function bought_or_sold($bought, $bought_for, $sold, $sold_for)
{
    if (gmp_cmp($bought, $sold) < 0) {
        $word = "sold";
        $net     = gmp_sub($sold,     $bought    );
        $net_for = gmp_sub($sold_for, $bought_for);
    } else {
        $word = "bought";
        $net     = gmp_sub($bought,     $sold    );
        $net_for = gmp_sub($bought_for, $sold_for);
    }

    return array($word, $net, $net_for);
}

function trade_price($btc, $for, $precision, $verbose = false) {
    if (gmp_cmp($btc, 0) == 0)
        return '';
    if ($verbose)
        return "(price " . bcdiv(gmp_strval($for), gmp_strval($btc), $precision) . ")";
    else
        return bcdiv(gmp_strval($for), gmp_strval($btc), $precision);
}

function show_statement($userid)
{
    $show_increments = false;
    $show_prices = true;

    echo "<div class='content_box'>\n";
    echo "<h3>Statement (UID $userid)</h3>\n";

    if ($userid == 'all')
        $check_userid = "";
    else
        $check_userid = "uid='$userid' AND";

    $query = "
        SELECT
            txid, a_orderid AS orderid,
            a_amount AS gave_amount, 'AUD' AS gave_curr,
            (b_amount-b_commission) AS got_amount,  'BTC' AS got_curr,
            NULL as reqid,  NULL as req_type,
            NULL as amount, NULL as curr_type, NULL as addy, NULL as voucher, NULL as final, NULL as bank, NULL as acc_num,
            " . sql_format_date('transactions.timest') . " AS date,
            transactions.timest as timest
        FROM
            transactions
        JOIN
            orderbook
        ON
            orderbook.orderid = transactions.a_orderid
        WHERE
            $check_userid
            b_amount != -1

    UNION

        SELECT
            txid, b_orderid AS orderid,
            b_amount AS gave_amount, 'BTC' AS gave_curr,
            (a_amount-a_commission) AS got_amount,  'AUD' AS got_curr,
            NULL, NULL,
            NULL, NULL, NULL, NULL, NULL, NULL, NULL,
            " . sql_format_date('transactions.timest') . " AS date,
            transactions.timest as timest
        FROM
            transactions
        JOIN
            orderbook
        ON
            orderbook.orderid=transactions.b_orderid
        WHERE
            $check_userid
            b_amount != -1

    UNION

        SELECT
            NULL, NULL,
            NULL, NULL,
            NULL, NULL,
            requests.reqid,  req_type,
            amount, curr_type, addy, CONCAT(prefix, '-...') as voucher, status = 'FINAL', bank, acc_num,
            " . sql_format_date('timest') . " AS date,
            timest
        FROM
            requests
        LEFT JOIN
            bitcoin_requests
        ON
            requests.reqid = bitcoin_requests.reqid
        LEFT JOIN
            voucher_requests
        ON
            (requests.reqid = voucher_requests.reqid OR
             requests.reqid = voucher_requests.redeem_reqid)
        LEFT JOIN
            uk_requests
        ON
            requests.reqid = uk_requests.reqid
        WHERE
            $check_userid
            status != 'CANCEL'

    ORDER BY
        timest
    ";

    $first = true;
    $result = do_query($query);
    $aud = 0;
    $btc = 0;

    $total_aud_deposit = $total_aud_withdrawal = $total_btc_deposit = $total_btc_withdrawal = numstr_to_internal(0);
    $total_aud_got = $total_aud_given = $total_btc_got = $total_btc_given = numstr_to_internal(0);

    $first = false;
    echo "<table class='display_data'>\n";
    echo "<tr>";
    echo "<th>Date</th>";
    echo "<th>Description</th>";
    if ($show_prices)
        echo "<th>Price</th>";
    if ($show_increments)
        echo "<th>+/-</th>";
    echo "<th>BTC</th>";
    if ($show_increments)
        echo "<th>+/-</th>";
    echo "<th>AUD</th>";
    echo "</tr>";

    echo "<tr>";
    echo "<td></td>";
    echo "<td></td>";
    if ($show_prices)
        echo "<td></td>";
    if ($show_increments)
        echo "<td></td>";
    printf("<td>%s</td>", internal_to_numstr('0',  BTC_PRECISION));
    if ($show_increments)
        echo "<td></td>";
    printf("<td>%s</td>", internal_to_numstr('0',  FIAT_PRECISION));
    echo "</tr>\n";

    $all_final = true;
    while ($row = mysql_fetch_array($result)) {

        echo "<tr>";
        echo "<td>{$row['date']}</td>";

        if (isset($row['txid'])) { /* buying or selling */
            $txid = $row['txid'];
            $orderid = $row['orderid'];
            $gave_amount = $row['gave_amount'];
            $gave_curr = $row['gave_curr'];
            $got_amount = $row['got_amount'];
            $got_curr = $row['got_curr'];

            if ($got_curr == 'BTC') {
                printf("<td>Buy %s %s for %s %s</td>",
                       internal_to_numstr($got_amount, BTC_PRECISION), $got_curr,
                       internal_to_numstr($gave_amount, FIAT_PRECISION), $gave_curr);

                $aud = gmp_sub($aud, $gave_amount);
                $btc = gmp_add($btc, $got_amount);

                $total_btc_got   = gmp_add($total_btc_got  , $got_amount );
                $total_aud_given = gmp_add($total_aud_given, $gave_amount);

                if ($show_prices)
                    printf("<td>%s</td>", trade_price($got_amount, $gave_amount, PRICE_PRECISION));
                if ($show_increments)
                    printf("<td>+ %s</td>", internal_to_numstr($got_amount, BTC_PRECISION));
                printf("<td> %s</td>",  internal_to_numstr($btc, BTC_PRECISION));
                if ($show_increments)
                    printf("<td>- %s</td>", internal_to_numstr($gave_amount, FIAT_PRECISION));
                printf("<td> %s</td>",  internal_to_numstr($aud, FIAT_PRECISION));
            } else {
                printf("<td>Sell %s %s for %s %s</td>",
                       internal_to_numstr($gave_amount, BTC_PRECISION), $gave_curr,
                       internal_to_numstr($got_amount, FIAT_PRECISION), $got_curr);

                $aud = gmp_add($aud, $got_amount);
                $btc = gmp_sub($btc, $gave_amount);

                $total_aud_got   = gmp_add($total_aud_got  , $got_amount );
                $total_btc_given = gmp_add($total_btc_given, $gave_amount);

                if ($show_prices)
                    printf("<td>%s</td>", trade_price($gave_amount, $got_amount, PRICE_PRECISION));
                if ($show_increments)
                    printf("<td>-%s</td>", internal_to_numstr($gave_amount, BTC_PRECISION));
                printf("<td>%s</td>", internal_to_numstr($btc, BTC_PRECISION));
                if ($show_increments)
                    printf("<td>+%s</td>", internal_to_numstr($got_amount, FIAT_PRECISION));
                printf("<td>%s</td>", internal_to_numstr($aud, FIAT_PRECISION));
            }
        } else {                /* withdrawal or deposit */
            $reqid = $row['reqid'];
            $req_type = $row['req_type'];
            $amount = $row['amount'];
            $curr_type = $row['curr_type'];
            $voucher = $row['voucher'];
            $final = $row['final'];
            // echo "final is $final<br/>\n";

            if (!$final)
                $all_final = false;

            if ($req_type == 'DEPOS') { /* deposit */
                $title = '';
                if ($voucher)
                    $title = sprintf("from voucher &quot;%s&quot;", $voucher);

                if ($curr_type == 'BTC') { /* deposit BTC */
                    $btc = gmp_add($btc, $amount);
                    $total_btc_deposit = gmp_add($total_btc_deposit, $amount);
                    
                    printf("<td><strong title='%s'>%s%s %s BTC%s</strong></td>",
                           $title,
                           $final ? "" : "* ",
                           $voucher ? "Redeem" : "Deposit",
                           internal_to_numstr($amount, BTC_PRECISION),
                           $final ? "" : " *");
                    if ($show_prices)
                        printf("<td></td>");
                    if ($show_increments)
                        printf("<td>+%s</td>", internal_to_numstr($amount, BTC_PRECISION));
                    printf("<td>%s</td>", internal_to_numstr($btc, BTC_PRECISION));
                    if ($show_increments)
                        printf("<td></td>");
                    printf("<td></td>");
                } else {        /* deposit AUD */
                    $aud = gmp_add($aud, $amount);
                    $total_aud_deposit = gmp_add($total_aud_deposit, $amount);

                    printf("<td><strong title='%s'>%s%s %s AUD%s</strong></td>",
                           $title,
                           $final ? "" : "* ",
                           $voucher ? "Redeem" : "Deposit",
                           internal_to_numstr($amount, FIAT_PRECISION),
                           $final ? "" : " *");
                    if ($show_prices)
                        printf("<td></td>");
                    if ($show_increments)
                        printf("<td></td>");
                    printf("<td></td>");
                    if ($show_increments)
                        printf("<td>+%s</td>", internal_to_numstr($amount, FIAT_PRECISION));
                    printf("<td>%s</td>", internal_to_numstr($aud, FIAT_PRECISION));
                }
            } else {            /* withdrawal */
                if ($curr_type == 'BTC') { /* withdraw BTC */
                    $btc = gmp_sub($btc, $amount);
                    $total_btc_withdrawal = gmp_add($total_btc_withdrawal, $amount);

                    $addy = $row['addy'];
                    if ($addy)
                        $title = sprintf("to Bitcoin address &quot;%s&quot;", $addy);
                    else if ($voucher) {
                        $title = sprintf("to %svoucher &quot;%s&quot;",
                                         $final ? "" : "unredeemed ",
                                         $voucher);
                    }
                    
                    printf("<td><strong title='%s'>%s%s %s BTC%s</strong></td>",
                           $title,
                           $final ? "" : "* ",
                           $voucher ? "Voucher" : "Withdraw",
                           internal_to_numstr($amount, BTC_PRECISION),
                           $final ? "" : " *");
                    if ($show_prices)
                        printf("<td></td>");
                    if ($show_increments)
                        printf("<td>-%s</td>", internal_to_numstr($amount, BTC_PRECISION));
                    printf("<td>%s</td>", internal_to_numstr($btc, BTC_PRECISION));
                    if ($show_increments)
                        printf("<td></td>");
                    printf("<td></td>");
                } else {        /* withdraw AUD */
                    $aud = gmp_sub($aud, $amount);
                    $total_aud_withdrawal = gmp_add($total_aud_withdrawal, $amount);

                    $title = '';
                    if ($voucher) {
                        $title = sprintf("to %svoucher &quot;%s&quot;",
                                         $final ? "" : "unredeemed ",
                                         $voucher);
                    } else
                        $title = sprintf("to account %s at %s", $row['acc_num'], $row['bank']);

                    printf("<td><strong title='%s'>%s%s %s AUD%s</strong></td>",
                           $title,
                           $final ? "" : "* ",
                           $voucher ? "Voucher" : "Withdraw",
                           internal_to_numstr($amount, FIAT_PRECISION),
                           $final ? "" : " *");
                    if ($show_prices)
                        printf("<td></td>");
                    if ($show_increments)
                        printf("<td></td>");
                    printf("<td></td>");
                    if ($show_increments)
                        printf("<td>-%s</td>", internal_to_numstr($amount, FIAT_PRECISION));
                    printf("<td>%s</td>", internal_to_numstr($aud, FIAT_PRECISION));
                }
            }
        }

        echo "</tr>";
    }

    list ($net_aud, $net_aud_word) = deposited_or_withdrawn($total_aud_deposit, $total_aud_withdrawal);
    list ($net_btc, $net_btc_word) = deposited_or_withdrawn($total_btc_deposit, $total_btc_withdrawal);

    list ($trade_word, $trade_btc, $trade_aud) = bought_or_sold($total_btc_got, $total_aud_given,
                                                                $total_btc_given, $total_aud_got);

    $bought_price = trade_price($total_btc_got,   $total_aud_given, PRICE_PRECISION, 'verbose');
    $sold_price   = trade_price($total_btc_given, $total_aud_got,   PRICE_PRECISION, 'verbose');
    $net_price    = trade_price($trade_btc,       $trade_aud,       PRICE_PRECISION, 'verbose');

    echo "</table>\n";

    echo "<table class='display_data'>\n";
    foreach (array(
                 "total AUD deposited"   => internal_to_numstr($total_aud_deposit,    FIAT_PRECISION),
                 "total AUD withdrawn"   => internal_to_numstr($total_aud_withdrawal, FIAT_PRECISION),
                 "net AUD $net_aud_word" => internal_to_numstr($net_aud,              FIAT_PRECISION),
                 ""                      => "",
                 "total BTC deposited"   => internal_to_numstr($total_btc_deposit,    BTC_PRECISION ),
                 "total BTC withdrawn"   => internal_to_numstr($total_btc_withdrawal, BTC_PRECISION ),
                 "net BTC $net_btc_word" => internal_to_numstr($net_btc,              BTC_PRECISION ),
                 " "                     => "",
                 ) as $a => $b)
        echo "<tr><td>$a</td><td>$b</td></tr>\n";
    foreach (array(
                 "total BTC bought"      => array(internal_to_numstr($total_btc_got,        BTC_PRECISION ) . " BTC", "for",
                                                  internal_to_numstr($total_aud_given,      FIAT_PRECISION) . " AUD",
                                                  $bought_price),
                 "total BTC sold"        => array(internal_to_numstr($total_btc_given,      BTC_PRECISION ) . " BTC", "for",
                                                  internal_to_numstr($total_aud_got,        FIAT_PRECISION) . " AUD",
                                                  $sold_price),
                 "net BTC $trade_word"   => array(internal_to_numstr($trade_btc,            BTC_PRECISION ) . " BTC", "for",
                                                  internal_to_numstr($trade_aud,            FIAT_PRECISION) . " AUD",
                                                  $net_price),
                 ) as $a => $b) {
        echo "<tr><td>$a</td>";
        foreach ($b as $c)
            echo "<td>$c</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";

    if (!$all_final) {
        echo "<p>Items marked with '*' are not yet final.</p>\n";
        echo "<p>Any such withdrawals and vouchers can be cancelled.</p>\n";
        echo "<p>Any such deposits are pending, and should be finalised within a minute or two.</p>\n";
    }
    echo "</div>";
}

if ($is_admin && isset($_GET['user']))
    show_statement(get('user'));
else
    show_statement($is_logged_in);

?>
