<?php

/**
 * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
 */
class Custom_Webshop_Model_DbTable_Exel {
    protected $languageAliases = array(
        'EN' => 'E',
        'FR' => 'F',
        'DE' => 'D',
        'NL' => 'NL'
    );
    private $_dbh;

    /**
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public  
     */
    public function __construct() {
        //Define variables
        $this->_dbh = Zend_Registry::get('Dbh');
    }

    
	public function getOrders($prefix){	
		$query = "SELECT  WO.wokWeborder AS id,  
				WO.wokOrderdatum AS date, 
				WO.wokOrdertijd AS time, 
				WO.wokRefGevraagd AS sentRequest,
				U.webVoorletters AS name,
				U.webAchternaam AS lastname,
				U.webNaam1 AS company, 
				U.webWoonplaats AS city,
				U.webTelPrive AS phonenumber,
				REPLACE(WO.wokBetaaldBedrag, '.', ',') AS amount,							
				 
				WO.wokBetaalmethode AS method,
				WO.wokOrdertype AS type,
				WO.wokFormule AS  website_code,
				WO.wokVerzendwijze AS shipping_type,
				WO.wokTekst AS comments,
				%artprf%zendingdetail.zgdFaktuur,
				%artprf%zendingen.zndDatum AS shipping_date
		FROM %artprf%webord AS WO
		INNER JOIN %artprf%webrelatie AS U ON U.webPersoonID = WO.wokPersoonID
		LEFT  JOIN %artprf%zendingdetail ON %artprf%zendingdetail.zgdOrdernummer = WO.wokBOOrder
		LEFT  JOIN %artprf%zendingen ON %artprf%zendingen.zndFaktuur = %artprf%zendingdetail.zgdFaktuur 
		GROUP BY id";
		
		$query = str_replace("%artprf%", $prefix, $query);
		$stmt = $this->_dbh->prepare($query);
		$stmt->execute();
		$result  = $stmt->fetchAll();
		return $result;
	}
	public function getOrder($orderID,$prefix){
		
		$query = "SELECT    WO.wokWeborder AS orderid,
				WO.wokRefGevraagd AS sendRequest,	
				WO.wokBOOrder AS handel_orderid,
				WO.wokOrderdatum AS date, 
				WO.wokOrdertijd AS time, 
				U.webVoorletters AS name,
				U.webAchternaam AS lastname,
				U.webNaam1 AS company, 
				U.webWoonplaats AS city,
				U.webTelPrive AS phonenumber,
				U.webEmail AS email,
				WO.wokBetaaldBedrag AS amount, 
				WO.wokBetaalmethode AS method,
				WO.wokFormule AS  website_code,
				WO.wokOrdertype AS type,
				D.aotOfferteHeaderTekst AS description,
				WG.worArtikel AS id,
				WG.worAantal  AS quantity,
				WG.worStuksprijs AS price,
				WO.wokVerzendwijze AS shipping_type,
				WO.wokTekst AS comments,
				U.webPersoonID AS user_id
				
				FROM %artprf%webord AS WO
				INNER JOIN %artprf%webrelatie AS U ON U.webPersoonID = WO.wokPersoonID
				INNER JOIN %artprf%webordrgl as WG on WG.worOrder = WO.wokWeborder
				LEFT JOIN %artprf%artdesc AS D ON D.aotArtikelkode = WG.worArtikel
				WHERE WO.wokWeborder = :orderId AND (D.aotOfferteHeaderTekst IS NULL OR D.aotTaalkode = 'NL')
				";
		$query = str_replace("%artprf%", $prefix, $query);
		$stmt = $this->_dbh->prepare($query);
		$stmt->bindParam('orderId', $orderID);
		$stmt->execute();
		$result  = $stmt->fetchAll();
		return $result;	
	}
	

}
