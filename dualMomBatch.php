<?php

include 'db.php';

#### NOTE NOTE NOTE NOTE
# be VERY careful about $types passed into sql
# i = integer,  d = double,  s=string
# I have been burned using integer instead of double


date_default_timezone_set('America/New_York');

$result = batchRequest();

function batchRequest(){
    $dbh = createDatabaseConnection();
    echo "I am in the batchRequest\n";
    #$cookie_jar = initFunction();
    #$data = getUserInfo($dbh,1);
    #return $data;
    #refreshTickerDataAlphaVantage($dbh);
    #refreshTickerYahoo($dbh);
    #$theCrumb = getYahooCrumb($cookie_jar);
    #$csv = readYahoo('VTI', mktime(0, 0, 0, 6, 2, 2000), mktime(0, 0, 0, 10, 30, 2017));
    #echo $csv;
    #getTickerDataWeb('VTI','yahoo',$theCrumb,$cookie_jar);
    #getTickerDataWeb('VTI','yahoo');
    updatePerformanceNEW($dbh, 1);  # 1 = Yahoo
    #updatePerformanceNEW($dbh, 3);  # 3 = AlphaVantage
    #getPerformanceForTickerAndDate($dbh, 1,'2017-10-25');
    #getPerformanceForTickerAndDate($dbh, 2,'2017-09-30');
    #getPerformanceForTickerAndDate($dbh, 3,'2017-09-30');
}

function  initFunction(){
    print "creating cookie_jar\n";
    $cookie_jar = tempnam('/tmp','cookie');
    return $cookie_jar;
}


function  getYahooCrumb($cookie_jar){
    $url = 'https://uk.finance.yahoo.com/quote/AAPL/history';
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_COOKIEJAR, $cookie_jar);
    $page = curl_exec($c);
    curl_close($c);    

    #print_r(get_headers($url));
    #print_r(get_headers($url, 1));
    $arrHeaders = get_headers($url);
    $setCookieItem =  $arrHeaders[5];
    # set-cookie: B=bjt8uupcv9rm9&b=3&s=el; expires=Sat, 28-Oct-2018
    #todo figure out what is held in arrItemsWithMatch - looks like an array of arrays
    #$arrItemsWithMatches = preg_grep("/set-cookie/", $arrHeaders);
    #var_dump($arrItemsWithMatches[0]);
    #preg_match('/set-cookie: B=(.+?)&b=/', $setCookieItem, $matches, PREG_OFFSET_CAPTURE);
    preg_match('/set-cookie: B=(.+?)&b=/', $setCookieItem, $matches);
    #var_dump($matches);
    print "-->" . $matches[1] . "<--\n";
    return $matches[1];
}

##this doesnt work.. .gets invalid cookie... use the getYahoo data above
function  getTickerDataWeb($ticker,$website,$crumb, $cookie_jar){
    
    $url = 'https://query1.finance.yahoo.com/v7/finance/download/' . $ticker . '?period1=0&period2=1509163200&interval=1d&events=history&crumb=' . $crumb;
    #$data = file_get_contents($url);
    # to download file    
    #file_put_contents("~/sites/dm/src/app/Yahoo-$ticker." . 'csv', file_get_contents($url));
    
    print "this is url:$url\n";
    print "this is cookie jar: $cookie_jar\n";

    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_COOKIEFILE, $cookie_jar);
    $page = curl_exec($c);
    curl_close($c);

    echo $page;
    return $page;
}

function  getUserInfo($dbh, $customer_id){
    $query = "select * from user where iduser = ?";
    $types = 'i';  ## pass
    $params = array($customer_id);
    $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
    return $data;
}


function  refreshTickerYahoo($dbh){
    $tickers = getTickers($dbh);
    
    foreach ($tickers as $id => $data){
        $ticker = $data{'ticker'};
        $idTicker = $data{'idticker'};
        echo "Refreshing Ticker Data for idTicker: $idTicker and ticker: $ticker \n";
        $method = 'W';
        #$tickerData = readYahoo($ticker, $method);
        $tickerData = readYahoo($ticker, mktime(0, 0, 0, 6, 2, 1995), mktime(0, 0, 0, 11, 04, 2017));
        updateTickerDataTableYahoo($dbh,$idTicker,$tickerData);
    }
}



### credit for this goes to Code4R7 - https://stackoverflow.com/questions/44030983/yahoo-finance-url-not-working
function readYahoo($symbol, $tsStart, $tsEnd) {
    ## Gets the crumb from the page source by doing search
    ## results are stored in $crumb.  The results are an array
      preg_match('"CrumbStore\":{\"crumb\":\"(?<crumb>.+?)\"}"',
        file_get_contents('https://uk.finance.yahoo.com/quote/' . $symbol),
        $crumb);  // can contain \uXXXX chars
      
      if (!isset($crumb['crumb'])) return 'Crumb not found.';
      
      $crumb = json_decode('"' . $crumb['crumb'] . '"');  // \uXXXX to UTF-8
      
      foreach ($http_response_header as $header) {
        if (0 !== stripos($header, 'Set-Cookie: ')) continue;
        $cookie = substr($header, 14, strpos($header, ';') - 14);  // after 'B='
      }  // cookie looks like "fkjfom9cj65jo&b=3&s=sg"
      
      if (!isset($cookie)) return 'Cookie not found.';


      #### This is the magic....   OPENS url but also supplies the cookie in the header
      $fp = fopen('https://query1.finance.yahoo.com/v7/finance/download/' . $symbol
        . '?period1=' . $tsStart . '&period2=' . $tsEnd . '&interval=1d'
        . '&events=history&crumb=' . $crumb, 'rb', FALSE,
        stream_context_create(array('http' => array('method' => 'GET',
          'header' => 'Cookie: B=' . $cookie))));
      
      if (FALSE === $fp) return 'Can not open data.';
      
      $buffer = '';
      
      while (!feof($fp)) $buffer .= implode(',', fgetcsv($fp, 5000)) . PHP_EOL;
      
      fclose($fp);
    
// var_dump($buffer);
// die;

      return $buffer;
}




function  refreshTickerDataAlphaVantage($dbh){
    $tickers = getTickers($dbh);
    
    foreach ($tickers as $id => $data){
        $ticker = $data{'ticker'};
        $idTicker = $data{'idticker'};
        echo "Refreshing Ticker Data for idTicker: $idTicker and ticker: $ticker \n";
        $method = 'F';
        $tickerData = getTickerData($ticker, $method);
        updateTickerDataTableAlphaVantage($dbh,$idTicker,$tickerData);
    }
}

function  getTickers($dbh){
    $query = "select * from ticker";
    $data = execSqlMultiRowPREPARED($dbh, $query);
    #var_dump($data);
    return $data;
}


function  getTickerData($ticker, $method){
    $data = getTickerDataFile($ticker);
    return $data;
}


function  getTickerDataFile($ticker){
    $pathName =  $ticker . '.json';
    $fh = fopen ($pathName, 'r') or die ("cant open File--> $pathName");
    $data='';
    while (! feof($fh)){
	   $data .= fread($fh, 1048576);
    }
    return json_decode($data);;
}



function  updateTickerDataTableAlphaVantage($dbh,$idTicker,$tickerData){
    $tsdLiteral = 'Time Series (Daily)';
    $openLiteral = '1. open';
    $highLiteral = '2. high';
    $lowLiteral = '3. low';
    $closeLiteral = '4. close';
    $adjCloseLiteral = '5. adjusted close';
    $volumeLiteral = '6. volume';
    $dividendAmtLiteral = '7. dividend amount';
    $splitLiteral = '8. split coefficient';
    #var_dump($tickerData->$tsd);
    #die;
    
    $now = new DateTime(Date('Y-m-d'));
    foreach ($tickerData->$tsdLiteral as $dateKey => $arrDetails){
        #$dtDate = strtotime($dateKey);
        $dtDate = DateTime::createFromFormat("Y-m-d", $dateKey);
        
        $interval = $dtDate->diff($now);
        $yearsAgo = $interval->y;
                              
        #echo $dtDate->format("Y");
        #if ($dtDate->format("Y") > 2013){
        if ($yearsAgo < 20) {
            $open =  floatval($arrDetails->$openLiteral) ;
            $high =  floatval($arrDetails->$highLiteral) ;
            $low =  floatval($arrDetails->$lowLiteral) ;
            $close =  floatval($arrDetails->$closeLiteral) ;
            $adjClose =  floatval($arrDetails->$adjCloseLiteral) ;
            $volume =  intval($arrDetails->$volumeLiteral) ;
            $divAmt =  floatval($arrDetails->$dividendAmtLiteral) ;
            $split =  floatval($arrDetails->$splitLiteral) ;
            #echo "in updateTickerDataTable: $dateKey \t $g  \n";
            $query = "INSERT INTO ticker_data (ticker_idticker, closing_date, open, high, low, close, adjusted_close, volume, dividend_amt) VALUES
               (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dividend_amt = ? ";

            $types = 'isdddddidd';  ## pass
            $params = array($idTicker, $dateKey, $open, $high, $low, $close, $adjClose, $volume, $divAmt,$divAmt);
            $rowsAffected = execSqlActionPREPARED($dbh, $query, $types, $params);
        } else {
            echo "skipped this date because its too old: $dateKey\n";   
        }
        
    }
    
}


function  updateTickerDataTableYahoo($dbh,$idTicker,$tickerData){
    $now = new DateTime(Date('Y-m-d'));
    
    $recs = explode("\n", $tickerData);

    #get rid of the first record which is the header
    array_shift($recs);

    foreach ($recs  as $aRec){

        #var_dump($aRec);

        list($dateKey, $open, $high, $low, $close, $adjClose, $volume) = str_getcsv($aRec, ",");
        print $dateKey . "\t";

        if ($dateKey == '"'){break;}


        $dtDate = DateTime::createFromFormat("Y-m-d", $dateKey);
        
        if (false == $dtDate){
            $dateKey = '1900-01-01';
            $dtDate = DateTime::createFromFormat("Y-m-d", $dateKey);    
        }

        $interval = $dtDate->diff($now);
        $yearsAgo = $interval->y;
                              
        #echo $dtDate->format("Y");
        #if ($dtDate->format("Y") > 2013){
        if ($yearsAgo < 20) {
            $divAmt =  0;
            $split =  0;
            $data_source = 1;
            #echo "in updateTickerDataTable: $dateKey \t $g  \n";
            $query = "INSERT INTO ticker_data (ticker_idticker, data_source_iddata_source, closing_date, open, high, low, close, adjusted_close, volume, dividend_amt) VALUES
               (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dividend_amt = ? ";

            $types = 'iisdddddidd';  ## pass
            $params = array($idTicker, $data_source, $dateKey, $open, $high, $low, $close, $adjClose, $volume, $divAmt,$divAmt);
            $rowsAffected = execSqlActionPREPARED($dbh, $query, $types, $params);
        } else {
            echo "skipped this date because its too old: $dateKey\n";   
        }
        
    }
    
}


function  updatePerformanceNEW($dbh, $sourceData){
    $tickers = getTickers($dbh);
    
    foreach ($tickers as $id => $data){
        $ticker = $data{'ticker'};
        $idTicker = $data{'idticker'};
        echo "Updating Performance for idTicker: $idTicker and ticker: $ticker source:$sourceData\n";
        updatePerformanceForTickerNEW($dbh,$idTicker, $sourceData);
    }
}

function  updatePerformanceForTickerNEW($dbh,$idTicker, $sourceData){
    $dates = getTickerDates($dbh, $idTicker);
    $update = 1;
    $display = 0;
    foreach ($dates as $id => $array){
        $date = $array{'closing_date'};
        getPerformanceForTickerAndDate($dbh,$idTicker,$date, $update, $display, $sourceData);
    }
}


function  getPerformanceForTickerAndDate($dbh,$idTicker,$date, $update=0, $display=0, $sourceData){
        
        #echo "this is date:$date";
        $date1yrAgo = strtotime("$date -1 year");
        $date1yrAgo = date('Y-m-d', $date1yrAgo);
        #echo "date 1 year ago: $date1yrAgo\n";
            
        $query = 'select adjusted_close from ticker_data where ticker_idticker = ? and closing_date = ? and data_source_iddata_source = ?';
        $types = 'isi';  ## pass
        $params = array($idTicker, $date, $sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $closeNewAdj = $data{'adjusted_close'};

        $query = 'select close from ticker_data where ticker_idticker = ? and closing_date = ? and data_source_iddata_source = ?';
        $types = 'isi';  ## pass
        $params = array($idTicker, $date, $sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $closeNewNonAdj = $data{'close'};
        
        $query = 'select sum(dividend_amt/close) divShares from ticker_data where ticker_idticker = ? 
                    and closing_date <= ? 
                    and closing_date >= ? and data_source_iddata_source = ?';
        $types = 'issi';  ## pass
        $params = array($idTicker, $date, $date1yrAgo, $sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $divSharesNonAdj = $data{'divShares'};

        $query = 'select sum(dividend_amt/adjusted_close) divShares from ticker_data where ticker_idticker = ? 
                    and closing_date <= ? 
                    and closing_date >= ? and data_source_iddata_source = ?';
        $types = 'issi';  ## pass
        $params = array($idTicker, $date, $date1yrAgo, $sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $divSharesAdj = $data{'divShares'};
    
        $query = 'select sum(dividend_amt) divAmt from ticker_data where ticker_idticker = ? 
                    and closing_date <= ? 
                    and closing_date >= ? and data_source_iddata_source = ?';
        $types = 'issi';  ## pass
        $params = array($idTicker, $date, $date1yrAgo, $sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $divAmt = $data{'divAmt'};

    
        $query = 'select adjusted_close from ticker_data where ticker_idticker = ?
                    and closing_date = 
                    (select min(closing_date) from ticker_data where  ticker_idticker = ? and closing_date > ? )
                    and data_source_iddata_source = ?';
        $types = 'iisi';  ## pass
        $params = array($idTicker, $idTicker, $date1yrAgo,$sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $closeOldAdj = $data{'adjusted_close'};

        $query = 'select close from ticker_data where ticker_idticker = ?
                    and closing_date = 
                    (select min(closing_date) from ticker_data where  ticker_idticker = ? and closing_date > date_sub(?, interval 52 week) )
                    and data_source_iddata_source = ?';
        $types = 'iisi';  ## pass
        $params = array($idTicker, $idTicker, $date, $sourceData);
        $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
        $closeOldNonAdj = $data{'close'};

        $pricePerformanceAdj = round(((((1) * $closeNewAdj) /  $closeOldAdj ) - 1) * 100, 3);
        $pricePerformanceNonAdj = round(((((1) * $closeNewNonAdj) /  $closeOldNonAdj ) - 1) * 100, 3);
        $performanceNonAdj = round(((((1+$divSharesNonAdj) * $closeNewNonAdj) /  $closeOldNonAdj ) - 1) * 100, 3);
        
        $divCurrPriceNonAdj = $divSharesNonAdj * $closeNewNonAdj;
        $divCurrPriceAdj = $divSharesAdj * $closeNewAdj;
    
        if ($display){
            echo "oldDate: $date1yrAgo  \t recentDate:$date\n";
            echo "closeNonAdj:$closeOldNonAdj \t $closeNewNonAdj\n";
            echo "closeAdj:$closeOldAdj \t $closeNewAdj\n";
            echo "\ndivShrAdj:$divSharesAdj  divShrNonAdj:$divSharesNonAdj\n";
            echo "\ndivAmtAdj:$divCurrPriceAdj  divAmtNonAdj:$divCurrPriceNonAdj divAmtStraightDollars: $divAmt \n";
            echo "pricePerformanceAdj:$pricePerformanceAdj\n";
            echo "pricePerformanceNonAdj:$pricePerformanceNonAdj\n";
            echo "performanceNonAdj:$performanceNonAdj\n";
        }
    
        if ($update){
            $query = 'UPDATE ticker_data SET 1yr_back_performance = ? where closing_date = ? and ticker_idticker = ? and data_source_iddata_source = ?';
            $types = 'dsii';  ## pass
            $params = array($pricePerformanceAdj, $date, $idTicker, $sourceData);
            #$params = array($performanceNonAdj, $date, $idTicker);
            $rowsAffected = execSqlActionPREPARED($dbh, $query, $types, $params);
        }
}

function  getTickerDates($dbh, $idTicker){
    $query = "select closing_date from ticker_data where ticker_idticker = ? and closing_date > 
    date_add((select min(closing_date) from ticker_data where ticker_idticker = ?), interval 53 week)
    order by closing_date desc";
    $types = 'ii';  ## pass
    $params = array($idTicker,$idTicker);
    $data = execSqlMultiRowPREPARED($dbh, $query, $types, $params);
    #var_dump($data);
    return $data;
}


?>