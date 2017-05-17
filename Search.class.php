<?php

class Search {

    /**
     * @access  protected
     */
    protected $dbh;

    /**
     * @access  protected
     */
    protected $commonWords = array(
        'NL' => array(

        ),
        'EN' => array(

        ),
        'DE' => array(

        )
    );

    protected $languageAliases = array(
        'EN' => 'E',
        'FR' => 'F',
        'DE' => 'D',
        'NL' => 'NL'
    );

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function __construct($dbh) {
        $this->dbh = $dbh;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function synonym($aKeyword, array $aSynonyms = array(), $aCorrected = false) {

        if(isset($_SESSION['synonyms'][LANGUAGE][$aKeyword])) {
            return $_SESSION['synonyms'][LANGUAGE][$aKeyword];
        }
        
        $lang = $this->languageAliases[LANGUAGE];

        // first correct the keyword
        if (!$aCorrected) {
            $query = "SELECT `wrdSynoniem` FROM `base_woordlijst` WHERE `wrdVervangtype` = 'C' AND `wrdZoekwoord` LIKE :keyword AND `wrdTaal` = :language LIMIT 1";

            $stmt = $this->dbh->prepare($query);
            $stmt->bindParam('keyword', $aKeyword);
            $stmt->bindParam('language', $lang);
            
            $stmt->execute();

            $correction = $stmt->fetch();
            if($correction) {
                $aKeyword = $correction['wrdSynoniem'];
            }
        }

        // add keyword
        $aSynonyms[] = $aKeyword;

        // limit results
        if (sizeof($aSynonyms) >= 8) return array();

        // get related keywords from database
        $query = "SELECT `wrdZoekwoord` FROM `base_woordlijst` WHERE `wrdVervangtype` = 'S' AND `wrdSynoniem` LIKE :keyword AND `wrdTaal` = :language";
        
        $keywordWildCards = trim($aKeyword) . '%';
        $stmt = $this->dbh->prepare($query);
        $stmt->bindParam('keyword', $keywordWildCards);
        $stmt->bindParam('language', $lang);

        $stmt->execute();

        $results = $stmt->fetchAll();

        if (is_array($results)) {
            foreach($results as $result) {
                $aSynonyms[] = $result['wrdZoekwoord'];
                $aSynonyms = array_merge($this->synonym($result['wrdZoekwoord'], $aSynonyms, true), $aSynonyms);
            }
        }

        // get synonym from database
        $query = "SELECT `wrdSynoniem` FROM `base_woordlijst` WHERE `wrdVervangtype` = 'S' AND `wrdZoekwoord` LIKE :keyword AND `wrdTaal` = :language LIMIT 1";

        $keywordWildCards = '' . $aKeyword . '%';
        $stmt = $this->dbh->prepare($query);
        $stmt->bindParam('keyword', $keywordWildCards);
        $stmt->bindParam('language', $lang);

        $stmt->execute();

        $results = $stmt->fetchAll();

        if (is_array($results)) {
            foreach($results as $result) {
                $aSynonyms[] = $result['wrdSynoniem'];
            }
        }

        $aSynonyms = array_unique($aSynonyms);
        $_SESSION['synonyms'][$aKeyword] = $aSynonyms;

        return $aSynonyms;
    }

    /**
     * @access  public
     */
    public function find($options) { 
        
        $magicdb = $this->dbh;

        $admincode = ADMIN_CODE;
        isset($options['joins']) || $options['joins'] = '';
        // joins
        $options["joins"] .= "INNER JOIN %dbprf%artbase ab ON ab.artKode = zi.zkiKode
            INNER JOIN %dbprf%artdesc ad ON ad.aotArtikelkode = zi.zkiKode AND ad.aotTaalkode = zi.zkiTaal
            INNER JOIN %dbprf%zksart za ON za.zkaArtikelkode = zi.zkiKode
            LEFT JOIN %dbprf%zkstrc zs5 ON za.azlZoekkode = zs5.zstKode
            LEFT JOIN %dbprf%zkstrc zs4 ON zs5.zstHoofdgroep = zs4.zstKode
            LEFT JOIN %dbprf%zkstrc zs3 ON zs4.zstHoofdgroep = zs3.zstKode
            LEFT JOIN %dbprf%zkstrc zs2 ON zs3.zstHoofdgroep = zs2.zstKode
            LEFT JOIN %dbprf%zkstrc zs1 ON zs2.zstHoofdgroep = zs1.zstKode
            ";
        
        // basic query
        $query = sprintf(
            "SELECT %s FROM %s%s zi %s ",
            (isset($options["select"])) ? $options["select"] : "*",
            "%dbprf%",
            (isset($options["table"])) ? $options["table"] : "zoektekst",
            (isset($options["joins"])) ? $options["joins"] : ""
        );

        // conditions
        $where = ($options["where"]) ? $options["where"] : array();

        //TODO::USE QUOTEINTO
        if(isset($options["keyword"])) {
            $where[] = sprintf("(zkiZoekwoord LIKE '%s' OR za.zkaArtikelkode LIKE '%s%%')", $options["keyword"], $options["keyword"]);
        }
        $where[] = sprintf("zkiIndextype = '%s'", "A");

        $lang    = $this->languageAliases[LANGUAGE];
        $where[] = sprintf("zkiTaal = '%s'", $lang);
        $where[] = sprintf("zkiWebsite = '%s'", WEBSITE_CODE);

        $options["where"] = "(" . implode(" AND ", $where) . ")";
        if(isset($options["conditions"])) {
            $options["where"] .= $options["conditions"];
        }

        $query .= " WHERE " . $options['where'];
        if(isset($options['groupby'])) {
            $query .= " GROUP BY " . $options['groupby'];
        }
        
        if(isset($options['orderby']) && is_array($options['orderby']) && count($options['orderby']) > 0) {
            $query .= " ORDER BY ";
            foreach($options['orderby'] as $column => $value) {
                $query .= $column . ' ' . $value . ',';
            }

            $query = substr($query, 0, -1);
        }
        
        if(is_array($options['limit'])) {
            $query .= " LIMIT " . (int) $options['limit'][0] . ", " . (int) $options['limit'][1];
        } else {
            $query .= " LIMIT " . (int) $options['limit'];
        }

        $stmt = $this->dbh->prepare($query);

        $stmt->execute();

        $searchResults = $stmt->fetchAll();

        if (ZOEKSTRUCTUUR_ID != ""){
            $realSearchResults = array();
            $categoriesModel = new Custom_Webshop_Model_DbTable_Hkvproductcategories();
            $availableCategories = $categoriesModel->getSubCategories(ZOEKSTRUCTUUR_ID);

            foreach ($searchResults as $searchResult){
                if (in_array($searchResult['azlZoekkode'], $availableCategories)){
                    $realSearchResults[] = $searchResult;
                }
            }
        } else {
            $realSearchResults = $searchResults;
        }

        return $realSearchResults;
    }

    public function filterKeyword($aKeyword, $aSynonyms = false) {

        $org      = trim(strtolower($aKeyword));
        $aKeyword = trim($aKeyword);
        $aKeyword = split(" ", $aKeyword);

        if ($aSynonyms) {
            $synonyms = $this->synonym($org);

            $_synonyms = array();
            foreach($synonyms as $synonym) {
                if (trim(strtolower($synonym)) == $org) continue;
                if (in_array($synonym,$_synonyms)) continue;
                $_synonyms[] = $synonym;
            }
            $aKeyword = array_merge($aKeyword, $_synonyms);
        }

        return $aKeyword;
    }

    /**
     * Get the categories en number of hits for this keyword.
     * @access public
     * @param aKeyword
     * @return array
     * @ParamType aKeyword
     * @ReturnType array
     */
    public function getCategoriesByKeyword($aKeyword) {

        //$magicdb = database::create("magic");
        $admincode  = ADMIN_CODE;
        $orgKeyword = $aKeyword;

        // process keywords
        $keywords  = $this->filterKeyword($aKeyword, true);
        $wkeywords = array();
        foreach($keywords as $keyword) {
            $wkeywords = array_merge($wkeywords, split(" ", $keyword));
        }

        // build keywords for full-text sorting
        $bkeywords = "";
        foreach($wkeywords as $bkeyword) {
            $bkeywords .= "*{$bkeyword}* ";
        }
        $bkeywords = trim($bkeywords);

        // build conditions
        $lang   = $this->languageAliases[LANGUAGE];
        $where  = "WHERE MATCH (`zkiZoektekst`) AGAINST(" . $this->dbh->quoteInto('?', $bkeywords) . " IN BOOLEAN MODE)";
        $where .= sprintf(" AND `zkiIndextype` = '%s'", "A");
        $where .= sprintf(" AND `zkiTaal` = '%s'", $lang);
        $where .= sprintf(" AND `zkiWebsite` = '%s'", WEBSITE_CODE);

        // build query
        $query = "SELECT
            count(za.`azlZoekkode`) cnt,
            zs1.`zstKode` AS cat1,
            zs2.`zstKode` AS cat2,
            zs3.`zstKode` AS cat3,
            zs4.`zstKode` AS cat4,
            zs5.`zstKode` AS cat5,
            zs1.`zstOmschrijving` AS desc1,
            zs2.`zstOmschrijving` AS desc2,
            zs3.`zstOmschrijving` AS desc3,
            zs4.`zstOmschrijving` AS desc4,
            zs5.`zstOmschrijving` AS desc5
            FROM `%dbprf%zoektekst` zi
            INNER JOIN `%dbprf%zksart` za ON za.`zkaArtikelkode` = zi.`zkiKode`
            LEFT JOIN `%dbprf%zkstrc` zs5 ON za.`azlZoekkode` = zs5.`zstKode`
            LEFT JOIN `%dbprf%zkstrc` zs4 ON zs5.`zstHoofdgroep` = zs4.`zstKode`
            LEFT JOIN `%dbprf%zkstrc` zs3 ON zs4.`zstHoofdgroep` = zs3.`zstKode`
            LEFT JOIN `%dbprf%zkstrc` zs2 ON zs3.`zstHoofdgroep` = zs2.`zstKode`
            LEFT JOIN `%dbprf%zkstrc` zs1 ON zs2.`zstHoofdgroep` = zs1.`zstKode`
            {$where}
            GROUP BY `azlZoekkode`
            ORDER BY
            MATCH(desc5) AGAINST (" . $this->dbh->quoteInto('?', $bkeywords) . " IN BOOLEAN MODE)
            +MATCH(desc4) AGAINST (" . $this->dbh->quoteInto('?', $bkeywords) . " IN BOOLEAN MODE)
            +MATCH(desc3) AGAINST (" . $this->dbh->quoteInto('?', $bkeywords) . " IN BOOLEAN MODE)
            +MATCH(desc2) AGAINST (" . $this->dbh->quoteInto('?', $bkeywords) . " IN BOOLEAN MODE)
            +MATCH(desc1) AGAINST (" . $this->dbh->quoteInto('?', $bkeywords) . " IN BOOLEAN MODE) DESC
        ";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        $prefix = null;

        // process results
        if (ZOEKSTRUCTUUR_ID != ""){
            $categoriesModel = new Custom_Webshop_Model_DbTable_Hkvproductcategories();
            $availableCategories = $categoriesModel->getSubCategories(ZOEKSTRUCTUUR_ID);
        }

        $aCategorieen = array();
        if (is_array($rows)) {
            foreach($rows as $f) {

                for($x=1;$x<=5;$x) {
                    if ($f["cat{$x}"]) {
                        $topcat = $f["cat{$x}"];
                        if ($topcat == $prefix) {
                            $x++;
                            $topcat = $f["cat{$x}"];
                        }
                        if(!isset($aCategorieen[$topcat]) && (ZOEKSTRUCTUUR_ID == "" || in_array($f["cat{$x}"], $availableCategories))) {
                            $aCategorieen[$f["cat{$x}"]] = array(
                                "title" => $f["desc{$x}"],
                                "url"   => "/".$f["desc{$x}"]."-c".$f["cat{$x}"].".html",
                                "catId" => $f["cat{$x}"],
                                "sub"   => array()
                            );
                        }
                        $x = 6;
                    }
                    $x++;
                }

                if (ZOEKSTRUCTUUR_ID == "" || in_array($f["cat5"], $availableCategories)) {
                    $catid = $f["cat5"];
                    $aCategorieen[$topcat]["sub"][] = array(
                        "title" => $f["desc5"],
                        'catId' => $f['cat5'],
                        "cnt"   => $f["cnt"]
                    );
                }
            }
        }

        return $aCategorieen;
    }

    /**
     *
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function autocomplete($keyword) {

        $keywords = "(`zkiZoekwoord` LIKE '" . implode("%' OR `zkiZoekwoord` LIKE '", $this->filterKeyword($keyword)) . "%')";

        $results = $this->find(array(
            "table"     => "zoekindex",
            "select"    => "`zkiZoekwoord` as word, count(`zkiZoekwoord`) as cnt, za.azlZoekkode",
            "where"     => array($keywords),
            "groupby"   => "`zkiZoekwoord`",
            "orderby"   => array("cnt"=>"DESC"),
            "limit"     => array(0,10)
        ));
        return is_array($results) ? $results : array();

    }

    /**
     *
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function logKeyword($keyword, $amount = 1, $userId = null) {
        // register keyword
        $admincode = ADMIN_CODE;
        $lang = $this->languageAliases[LANGUAGE];
        $startTime = Zend_Registry::get('startTime');

        if(is_null($userId)) {
            $userId = '';
        }

        $query = "  INSERT
                  INTO %dbprf%webzoek
                   SET zoekstring = :keyword,
                       zoeksoundex = SOUNDEX(:keyword),
                       taal = :lang,
                       aantal = :amount,
                       datum = :date,
                       tijd = :time,
                       ipadres = :ip,
                       webrelatieID = :userId,
                       generatietijd = :executionTime
        ";
        
        $date = date('Ymd');
        $time = date('His');
        $stmt = $this->dbh->prepare($query);

        $stmt->bindParam('keyword', $keyword);
        $stmt->bindParam('lang', $lang);
        $stmt->bindParam('amount', $amount);
        $stmt->bindParam('date', $date);
        $stmt->bindParam('time', $time);
        $stmt->bindParam('ip', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam('userId', $userId);

        $executionTime = microtime(true) - $startTime;

        $stmt->bindParam('executionTime', $executionTime);

        $stmt->execute();
    }
}
