<?php

/**
 * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
 */
class Custom_Webshop_Frontend_Order extends Webshop_Frontend_Order_Abstract {

    /**
     * @access  protected
     */
    protected $db;
	protected $BTW;
	protected $shippingCost;
	protected $totalPriceExVat;
    protected $pickupLocation;
	protected $referentie = NULL;
    protected $orderType = 'O'; //regular order
    protected $isPaid = false;
    protected $pricePaid = 0.00;
    protected $defaultVat;
    protected $mailTemplate = 'ORDWEB';
    protected $preferredDeliveryDate = '00000000';
	protected $calculated_price=0;

    /**
     *
     * @author Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function getPickupLocation() {
        return $this->pickupLocation;
    }


    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com> 
     */
    public function setDefaultVat($vat) {
        $this->defaultVat = $vat;
    }
	public function getDefaultVat() {
        return $this->defaultVat;
    }


    public function setEmailTemplate(){
		$website = Zend_Registry::get('website');
		$website_id =  $website['website_id'];
		$payment_method =  strtolower($this->paymentMethod);
		$dbh = Zend_Registry::get('dbh');
		
		$query = "
			SELECT *
			FROM email_templates
			WHERE website_id = :website_id AND code = :payment_method AND type = 'payment_template'
		";
		$stmt = $dbh->prepare($query);
		
		$stmt->bindParam('payment_method', $payment_method);
		$stmt->bindParam('website_id', $website_id);
		$stmt->execute();
		$result = $stmt->fetch();
		
		if(isset($result['email_template']) && trim($result['email_template']) != ""){
			$this->mailTemplate = trim($result['email_template']);
		}
    }
	
    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function setPickupLocation($pickupLocation) {
        $this->pickupLocation = (string) $pickupLocation;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function setPaid($paid = 0) {

        $this->isPaid = $paid;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function getPaid() {
        return $this->isPaid;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function setPricePaid($pricePaid) {
        $this->pricePaid = $pricePaid;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function getPricePaid() {
        return $this->pricePaid;
    }
	
	public function setCalculatedPrice($price)
	{	
		$this->calculated_price=$this->roundPrice($price);
	}
	
	public function getCalculatedPrice()
	{
		return $this->calculated_price;
	}
	

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     */
    public function setOrderType($orderType) {
        $this->orderType = (string) $orderType;
    }
	public function setBTW($BTW){
		$this->BTW = $BTW;
	}
	public function setReferentie($referentie){
		$this->referentie = $referentie;
	}
	public function setShippingCost($shippingCost){
		$this->shippingCost = $shippingCost;
	}
	public function setTotalPriceExVat($totalPriceExVat){
		$this->totalPriceExVat = $totalPriceExVat;
	}
	public function getTotalPriceExVat(){
		return $this->totalPriceExVat;
	}
	public function getBTW(){
		return $this->BTW;
	}
	public function getShippingCost(){
		return $this->shippingCost;
	}
    public function setPreferredDeliveryDate ($preferredDeliveryDate) {
        trim($preferredDeliveryDate);
        $tmp_array = explode('-', $preferredDeliveryDate);
        @$preferredDeliveryDate = $tmp_array['2'] . $tmp_array['1'] . $tmp_array['0'];
        if(preg_match('/^\d+$/',$preferredDeliveryDate) && strlen($preferredDeliveryDate) == 8) {
            $this->preferredDeliveryDate = $preferredDeliveryDate;
        } else {
            $this->preferredDeliveryDate = "00000000";
        }
    }

    public function getPreferredDeliveryDate () {
        return $this->preferredDeliveryDate;
    }

	public function pre_process($promo_code = 'NULL') {	
	
		$cms_db = Zend_Registry::get('dbh');
		$websiteDetails = Zend_Registry::get('website');
		$website_code = $websiteDetails['website_code'];
		$data = serialize($this); 
		
        $query = " INSERT INTO 	
					order (
						user_id,
						website_code,
						data,
						promo_code
					) VALUES (
					:user_id ,
					:website_code ,
					:data,
					:promo_code
					)";
		$stmt = $cms_db->prepare($query);
        $stmt->bindParam('user_id', $this->customer->customerId);
        $stmt->bindParam('website_code', $website_code);
        $stmt->bindParam('data', $data);
		  $stmt->bindParam('promo_code', $promo_code);
        $stmt->execute();
		return $cms_db->lastInsertId();
	}
	
	public function remove_record_by_orderID($order_id){
		$cms_db = Zend_Registry::get('dbh');		
		$query = "DELETE FROM order
				     WHERE id =:order_id";									
		$stmt = $cms_db->prepare($query);
		$stmt->bindParam('order_id', $order_id);
		$stmt->execute();
	}
	
	public function update_real_order_id($order_id , $real_order_id){
		$cms_db = Zend_Registry::get('dbh');
		$query = " UPDATE order
					SET real_order_id = :real_order_id
					WHERE id = :order_id";		
		$stmt = $cms_db->prepare($query);
		$stmt->bindParam('order_id', $order_id);
		$stmt->bindParam('real_order_id', $real_order_id);
		$stmt->execute();
	}
	
	public function retrieve_order_obj($order_id) {
		$cms_db = Zend_Registry::get('dbh');
		$query = " SELECT * 
					FROM order
					WHERE id=:order_id";
		$stmt = $cms_db->prepare($query);
		$stmt->bindParam('order_id', $order_id);
		$stmt->execute();
	    $obj = $stmt->fetch();
		return $obj;
	}
    /**
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function process() {
		
		$shippingMethodsModel = new Custom_Webshop_Model_DbTable_Hkvshippingmethods();
        $shippingMethod = $shippingMethodsModel->getShippingMethod($this->shippingMethod);
		$shippingMethodCostsExVat = $shippingMethodsModel->calculate($shippingMethod['shippingmethod_code'], $this->customer->deliveryLocation['country'], $this->customer->deliveryLocation['zipcode'], $this->products);
		$this->_db = Zend_Registry::get('Dbh');		
        $query = "
            INSERT INTO
                %dbprf%webord (
                    wokWeborder,
                    wokPersoonID,
                    wokOrderdatum,
                    wokOrderTijd,
                    wokVerzendwijze,
                    wokVrachtBerekenen,
                    wokVrachtkosten,
                    wokBetaald,
                    wokBetaaldBedrag,
                    wokBetaalmethode,
                    wokBetaalReferentie,
                    wokGewensteDatum,
                    wokReferentieKlant,
                    wokBOOrder,
                    wokOrderType,
                    wokTekst,
                    wokFormule,
                    wokAfhalenBij,
                    wokRefNodig,
                    wokRefGevraagd
                ) VALUES (
                    null,
                    :wokPersoonID,
                    :wokOrderdatum,
                    :wokOrderTijd,
                    :wokVerzendwijze,
                    :wokVrachtBerekenen,
                    :wokVrachtkosten,
                    :wokBetaald,
                    :wokBetaaldBedrag,
                    :wokBetaalmethode,
                    :wokBetaalReferentie,
                    :wokGewensteDatum,
                    :wokReferentieKlant,
                    :wokBOOrder,
                    :wokOrderType,
                    :wokTekst,
                    :wokFormule,
                    :wokAfhalenBij,
                    :wokRefNodig,
                    :wokRefGevraagd
                )
        ";

        $stmt = $this->_db->prepare($query);
		$orderDate = date('Ymd', time());
        $orderTime = date('His', time());

        $preferredDeliveryDate = $this->preferredDeliveryDate;

        $hasPaid = (int) $this->getPaid();

        if ($hasPaid == 1) {
            $amountPaid = $this->pricePaid;
        } else {
            $amountPaid = 0.00;
        }

        $betaalReferentie = '';
        $referentieKlant = isset($this->referentie) ? $this->referentie:'';
        $BOOrder = '';

        $wokFormule = WEBSITE_CODE;
        $wokRefNodig = 'N';
        $wokRefGevraagd = '00000000';

        $calculateShippingCosts = $shippingMethod['vrzKostenRekenen'] ? 1 : 0;

        $shippingMethodsModel = new Custom_Webshop_Model_DbTable_Hkvshippingmethods();
        $pickupLocation = $shippingMethodsModel->getPickupLocation($this->pickupLocation);
        $pickupLocationText = $pickupLocation['vesAfhaaltekst'];
        $wokTekst = '';
        if($pickupLocationText) {
                $wokTekst ='';// $pickupLocationText .PHP_EOL
        }

        if(trim($this->remark) != '' ) {
                $wokTekst .= $this->remark;
        }
		$stmt->bindParam('wokPersoonID', $this->customer->customerId );  
		$stmt->bindParam('wokOrderdatum', $orderDate);
        $stmt->bindParam('wokOrderTijd', $orderTime);
        $stmt->bindParam('wokVerzendwijze', $this->shippingMethod);
        $stmt->bindParam('wokVrachtBerekenen', $calculateShippingCosts);
        $stmt->bindParam('wokVrachtkosten', $shippingMethodCostsExVat);
        $stmt->bindParam('wokBetaald', $hasPaid);
        $stmt->bindParam('wokBetaaldBedrag', $amountPaid);
        $stmt->bindParam('wokBetaalmethode', $this->paymentMethod);
        $stmt->bindParam('wokBetaalReferentie', $betaalReferentie); //TODO
        $stmt->bindParam('wokGewensteDatum', $preferredDeliveryDate); //TODO
        $stmt->bindParam('wokReferentieKlant', $referentieKlant); 
		$stmt->bindParam('wokBOOrder', $BOOrder); //TODO
        $stmt->bindParam('wokOrderType', $this->orderType); //TODO
        $stmt->bindParam('wokTekst', $wokTekst);
        $stmt->bindParam('wokFormule', $wokFormule); //TODO
		$stmt->bindParam('wokAfhalenBij', $this->pickupLocation);
        $stmt->bindParam('wokRefNodig', $wokRefNodig); //TODO
        $stmt->bindParam('wokRefGevraagd', $wokRefGevraagd); //TODO
		
		$stmt->execute();	
		$orderId = $this->_db->lastInsertId();
        $this->orderId = $orderId;
		
		$productIds = array();
		foreach ($this->products as $product) {	
			
			$productIds[] = $product['productId'];
		}
		$productFactory = Webshop_Frontend_Product::getInstance('Hkv');
		$tradeInData = $productFactory->getTradeIn($productIds);
		
		foreach ($this->products as $i => $product) {  		
			if(isset($tradeInData[$product['productId']])){
				$this->insertOrderLine($orderId, $i + 1, $product,'', $tradeInData[$product['productId']]['acpInruilbedrag'],  '', 1);
			}else{
				$this->insertOrderLine($orderId, $i + 1, $product,'');
			}
		}

    }
	public function getproductuser($userid) {

        $Dbh = Zend_Registry::get('Dbh');
        $query = "SELECT wokWeborder,wokOrdertijd,
					%dbprf%webordrgl.worArtikel,
					%dbprf%webordrgl.worAantal,
					%dbprf%webordrgl.worStuksprijs
					FROM %dbprf%webord
					INNER JOIN %dbprf%webordrgl
					 ON %dbprf%webordrgl.worOrder = %dbprf%webord.wokWeborder
					 WHERE  wokWeborder=(SELECT MAX(wokWeborder) FROM %dbprf%webord
					 WHERE %dbprf%webord.wokPersoonID = :userid)
					";
        $stmt = $Dbh->prepare($query);
        $stmt->bindParam('userid', $userid);
        $stmt->execute();
        $product = $stmt->fetchAll();
        
        return $product;
       
    }

    public function getTotalPrice($options = array(),$products,$totalpraducts)  {

        is_array($products) || $products = array();

		$i=0;
        $price = 0;
        foreach($products as $product) {

            if(isset($options['ceil']) && $options['ceil']) {
                $product['priceIncVat'] = $product['priceIncVat'];
                $product['priceExVat'] = $product['priceExVat'];
            }

            if(isset($options['vat']) && $options['vat']) {
                $price += (float) $product['priceIncVat'] * (float) $totalpraducts[$i];
            } else {
                $price += (float) $product['priceExVat'] * (float) $totalpraducts[$i];
            }
            $i++;
        }
    
        return $price;
    }
    
    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function insertOrderLine($orderId, $orderLine, $product, $remark = '', $tradeInDiscountPrice = 0, $tradeInMsg = '', $tradeIn = 0) {
		
        $query = "
            INSERT INTO
                %dbprf%webordrgl (
                    worRegel,
                    worOrder,
                    worArtikel,
                    worAantal,
                    worStuksprijs,
                    worVWB,
                    worTekst,
                    worInruil,
                    worInruilbedrag,
                    worInruiltekst
                ) VALUES (
                    :worRegel,
                    :worOrder,
                    :worArtikel,
                    :worAantal,
                    :worStuksprijs,
                    :worVWB,
                    :worTekst,
                    :worInruil, 
                    :worInruilbedrag,
                    :worInruiltekst
                )
        ";

        $stmt = $this->_db->prepare($query);
        $stmt->bindParam('worRegel', $orderLine);
        $stmt->bindParam('worOrder', $orderId);
        $stmt->bindParam('worArtikel', $product['productId']);
        $stmt->bindParam('worAantal', $product['quantity']);
        $stmt->bindParam('worStuksprijs', $product['priceExVat']);
        $stmt->bindValue('worVWB', number_format($product['vwb'], 2));
        $stmt->bindParam('worTekst', $remark);
		
		$stmt->bindParam('worInruil',  $tradeIn);
        $stmt->bindValue('worInruilbedrag', number_format($tradeInDiscountPrice, 2));
		$stmt->bindParam('worInruiltekst', $tradeInMsg);

        $stmt->execute();
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function pay($orderId, $paid = 1, $amountPaid = 0.00, $referentie) {

        $this->_db = Zend_Registry::get('Dbh');

        $query = "
            UPDATE
                %dbprf%webord
            SET
                wokBetaald = :paid,
                wokBetaaldBedrag = :amount,
                wokBetaalReferentie = :referentie
            WHERE
                wokWeborder = :weborder_id
        ";

        $stmt = $this->_db->prepare($query);
        $stmt->bindParam('paid', $paid);
        $stmt->bindParam('amount', $amountPaid);
        $stmt->bindParam('referentie', $referentie);
        $stmt->bindParam('weborder_id', $orderId);
        $stmt->execute();

        return true;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
    public function roundPrice($price) {

        $roundAmount = ROUND_AMOUNT * 100;

        $cents = round($price * 100);
        if ($roundAmount) {
            $residu = $cents % $roundAmount;
        }
        if ($residu > $roundAmount / 2) {
            $cents += ( $roundAmount - $residu);
        } else {
            $cents -= $residu;
        }

        return $cents / 100;
    }

    /**
     *
     * @author  Telman Hovhannisyan <tttdevelop@gmail.com>
     * @access  public
     */
	  
    public function sendOrderConfirmation($price, $shippingCost = '', $BTW = '', $totalPriceExVat = '', $templateVars , $vwbVat = 21) {   

		$shoppingCart = Webshop_Frontend_Shoppingcart::getInstance('digiself');
		if(empty($totalPriceExVat)){ 
			if(WEBSITE_CODE == 'CBN'){
				$totalPriceExVat = $shoppingCart->getTotalPriceForSoapOption( $this->products , array(
					'vat' => false,
					'ceil' => ROUND_AMOUNT
				));
			}else{
				$totalPriceExVat = $shoppingCart->getTotalPrice(array(
					'vat' => false,
					'ceil' => ROUND_AMOUNT
				));
			}
		}
		
		$moneyFormat = isset($settings['money_format']) ? $settings['money_format'] : '%!.2n';		
		$price = money_format($moneyFormat, $price);
		
        $dbh = Zend_Registry::get('dbh');
        $Dbh = Zend_Registry::get('Dbh');
        $language = strtolower(LANGUAGE);
        $languageAliases = array(
            'en' => 'E',
            'de' => 'D',
            'nl' => 'NL',
            'fr' => 'F'
        );
		$language_formated = $languageAliases[$language];
        $adminModel = new Custom_Webshop_Model_DbTable_Hkvadministrations();
		$adminData = $adminModel->getAdministrationByCode(ADMIN_CODE);
		
        $websiteDetails = Zend_Registry::get('website');
        $websiteSettings = Zend_Registry::get('hkvWebsiteSettings');
        $websiteDetails = array_merge($websiteDetails, $websiteSettings);
        
        $productFactory = Webshop_Frontend_Product::getInstance('Hkv');
		$ordered_products_data = "<table>";
		foreach($this->products as $product){
			$ordered_products_data.="<tr>";
			$ordered_products_data .= "<td>".$product['quantity']."</td><td>".$product['description']."</td><td>".round($product['priceIncVat'], 2)."</td>";
			$ordered_products_data.="</tr>";
		}
		$ordered_products_data.="<tr><td>Verzendkosten</td><td>&euro; ".number_format($shippingCost, 2)."</td><td></td></tr>";
		$ordered_products_data.="<tr><td>Totaal order</td><td>&euro; ".number_format($price, 2)."</td><td></td></tr>";
		$ordered_products_data.="<tr><td>BTW</td><td>&euro; ".number_format($BTW, 2)."</td><td></td></tr>";
		$ordered_products_data.="</table>";
		
		if(DISPLAY_VAT){
			$shippingCost = $shippingCost*(1 + $vwbVat / 100);  
			$shippingCost = round($shippingCost, 2);
			
			$html = "<table style='border-collapse: collapse;'>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;border-left:1px solid black;border-top:1px solid black;border-bottom:1px solid black;'>Aantal</td><td style='width:93px;border-top:1px solid black;border-bottom:1px solid black;'>Artikelnr.</td><td style='padding-right:20px;border-top:1px solid black;border-bottom:1px solid black;'>Omschrijving</td><td style='width:100px;border-top:1px solid black;border-bottom:1px solid black;border-right:1px solid black;'>prijs incl BTW</td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;border-left:1px solid black;'></td><td style='width:93px;'></td><td style='padding-right:20px;'></td><td style='border-right:1px solid black;'></td>";
				$html .= "</tr>";
				
				foreach($this->products as $product){
					$html .= "<tr>";
						$html .= "<td style='width:55px;border-left:1px solid black;'>".$product['quantity']."</td><td style='width:93px;'>".$product['productId']."</td><td style='padding-right:20px;'>".($productFactory->formatProductName($product['description']))."</td><td style='border-right:1px solid black;'>&euro;".number_format(round($product['priceToShow'], 2), 2)."</td>"; 
					$html .= "</tr>";
				}
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;border-bottom:1px solid black;border-left:1px solid black;'></td><td style='width:93px;border-bottom:1px solid black;'></td><td style='padding-right:20px;border-bottom:1px solid black;'></td><td style='border-bottom:1px solid black;border-right:1px solid black;'></td>";
				$html .= "</tr>";
				$html .= "<tr>";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'></td><td style=''></td>";
				$html .= "</tr>";
				
				$html .= "<tr>";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>Verzendkosten</td><td style=''>&euro;".number_format($shippingCost, 2)."</td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>BTW</td><td style=''>&euro;".number_format($BTW, 2)."</td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'></td><td style=''></td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>Totaal Order Incl. BTW</td><td style=''>&euro;".number_format($price, 2)."</td>";
				$html .= "</tr>";
			$html .= "</table>";
			$ordered_products_data = $html;  
		}else{
			  if (WEBSITE_CODE == 'CBS') {
                    $amount           =  $templateVars['amount'];
                    $articleNr        =  $templateVars['articleNr'];
                    $description      =  $templateVars['description'];
                    $DisPriceexVAT    =  $templateVars['DisPriceexVAT'];
                    $total_excl       =  $templateVars['total_excl'];
                    $verzendwijze     =  $templateVars['verzendwijze'];
                    $btw_t            =  $templateVars['btw'];
                    $totalorderprice  =  $templateVars['totalorderprice'];
                }
                else{
                        $amount           =  'Aantal';
                        $articleNr        =  'Artikelnr.';
                        $description      =  'Omschrijving';
                        $DisPriceexVAT    =  'prijs ex BTW';
                        $total_excl       =  'Totaal ex. BTW';   
                        $verzendwijze     =  'Verzendkosten ex. BTW';   
                        $btw_t            =  'BTW';  
                        $totalorderprice  =  'Totaal Order Incl. BTW';   
                }
			$html = "<table style='border-collapse: collapse;'>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;border-left:1px solid black;border-top:1px solid black;border-bottom:1px solid black;'>".$amount."</td><td style='width:93px;border-top:1px solid black;border-bottom:1px solid black;'>".$articleNr."</td><td style='padding-right:20px;border-top:1px solid black;border-bottom:1px solid black;'>".$description."</td><td style='width:100px;border-top:1px solid black;border-bottom:1px solid black;border-right:1px solid black;'>".$DisPriceexVAT."</td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;border-left:1px solid black;'></td><td style='width:93px;'></td><td style='padding-right:20px;'></td><td style='border-right:1px solid black;'></td>";
				$html .= "</tr>";
                foreach ($this->products as  $valuePriceToShow) {
                   $sum_price += $valuePriceToShow['quantity']*$valuePriceToShow['priceToShow'];
                }

				foreach($this->products as $product){
					$html .= "<tr>";
						$html .= "<td style='width:55px;border-left:1px solid black;'>".$product['quantity']."</td><td style='width:93px;'>".$product['productId']."</td><td style='padding-right:20px;'>".($productFactory->formatProductName($product['description']))."</td><td style='border-right:1px solid black;'>&euro;".number_format($product['priceToShow'] , 2)."</td>";
					$html .= "</tr>";
                    $price_value += $product['priceToShow'];
				}
                
                if (WEBSITE_CODE == 'CBS') {
                    $price_value = $sum_price;
                    $BTW = $price - $price_value;
                    $totalPriceExVat  = $price_value;
                }
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;border-bottom:1px solid black;border-left:1px solid black;'></td><td style='width:93px;border-bottom:1px solid black;'></td><td style='padding-right:20px;border-bottom:1px solid black;'></td><td style='border-bottom:1px solid black;border-right:1px solid black;'></td>";
				$html .= "</tr>";
				$html .= "<tr>";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'></td><td style=''></td>";
				$html .= "</tr>";
				$html .= "<tr>";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>".$total_excl."</td><td style=''>&euro;".number_format($totalPriceExVat, 2)."</td>";
				$html .= "</tr>";
				$html .= "<tr>";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>".$verzendwijze."</td><td style=''>&euro;".number_format($shippingCost, 2)."</td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>".$btw_t."</td><td style=''>&euro;".number_format($BTW, 2)."</td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'></td><td style=''></td>";
				$html .= "</tr>";
				$html .= "<tr >";
					$html .= "<td style='width:55px;height:20px;'></td><td style='width:93px;'></td><td style='padding-right:20px;'>".$totalorderprice."</td><td style=''>&euro;".$price."</td>";
				$html .= "</tr>";
			$html .= "</table>";
			$ordered_products_data = $html;  

		}
		
        $products = $productFactory->createProductImages($this->products);
       
        $countriesModel = new Custom_Webshop_Model_DbTable_Hkvcountries();
        $countries = $countriesModel->getCountries();
		
		$this->setEmailTemplate();
		
        $sql = "
			SELECT mt.mltSubject,mt.mltBericht FROM base_mail AS bm
			INNER JOIN base_mailtext AS mt ON bm.mlbCode=mt.mltCode
			WHERE
			mt.mltCode='$this->mailTemplate'
			AND mt.mltTaal='$language_formated'
		";
        
        if ($this->customer->gender == "M") {
            $gender = "Dhr.";
        } else {
            $gender = "Mevr.";
        }

        foreach ($countries as $country) {
            if ($country['lndISOCode'] == $this->customer->invoiceLocation['country']) {
                $userCountry = $country['lndNaamEngels'];
            }
        }

        $template = $Dbh->fetchRow($sql);
        $page_map = Zend_Registry::get('page_map');
        $page_addr = $page_map["my-account.html"];
        $referrer = $websiteDetails['url']."/".strtolower($language). "/" . $page_addr . "/cname/orderstatus/view/index?id=".$this->customer->customerId; 
        
        $data = array(
			'ORDREF' => $this->orderId,
            'KONTAKT' => $this->customer->invoiceLocation['fullName'],
            'NAAM' => $this->customer->invoiceLocation['companyName'],
            'AANHEF' => $gender,
            'ADRES' => $this->customer->invoiceLocation['address'],
            'AFZ_EMAIL' => $websiteSettings['email'],
            'AFZ_BEDRIJF' => $adminData['adnAdminnaam'],
            'E-MAIL' => $this->customer->email,
            'LAND' => $userCountry,
            'WEBREF' => $referrer,
            'AFZ_NAAM' => $websiteDetails['website_name'],
            'ONZE_WEBSITE' => $websiteDetails['website_name'],
			'WEBSITE_URL' => $websiteDetails['url'],
            'WOONPLAATS' => $this->customer->invoiceLocation['zipcode'] . " " . $this->customer->invoiceLocation['address'],
            'SYS_DATUM' => date("d-F-o"),
            'TOTAALINCL' => $price,
            'ADMADRES' => $adminData['admAdres'],
            'ADMPOSTCODE' => $adminData['admPostcode'],
            'ADMWOONPLAATS' => $adminData['admWoonplaats'],
            'ADMTELEFOON' => $adminData['admTelefoon'],
            'ADMBANKREKENING' => $adminData['admBankrekening'],
            'ADMBIC' => $adminData['admBIC'],
            'ADMKVK' => $adminData['admKVK'],
			'ADMIBAN' => $adminData['admIBAN'],
			'ORDEROVERVIEW' => $ordered_products_data
        );
        $from = array(
            'email' => $websiteSettings['email'],
            'name' => $websiteDetails['website_name']
        );
        $mail = new Ds_Mail($this->customer->email, $from, $template['mltBericht'], $template['mltSubject'], $data);
        $mail->send();
        if( defined('AIRGRAM_EMAIL') && trim(AIRGRAM_EMAIL) != '' && ( WEBSITE_CODE == 'HKV' || WEBSITE_CODE == 'HON' || WEBSITE_CODE == 'GAS' ) ){
				$aigramArr = explode("," , AIRGRAM_EMAIL );
				for($i = 0;$i < count($aigramArr);$i++ ){
					if(trim($aigramArr[$i] != "")){    
						$aigramemil = new Ds_Mail(trim($aigramArr[$i]), $from, $template['mltBericht'], $template['mltSubject'], $data);
						$aigramemil->send();
					}
				}
        } 

	   return true;
    }
	
	public function sendEmailForOpen($price, $transactionID) {
        
		$websiteDetails = Zend_Registry::get('website');
        $websiteSettings = Zend_Registry::get('WebsiteSettings');
        $websiteDetails = array_merge($websiteDetails, $websiteSettings);
		
		$mail = new Zend_Mail();
		$view = new Zend_View();
		$view->setScriptPath($websiteDetails['website_path'] );
		
		$view->price = $price;
		$view->products = $this->products;
		$view->transactionID = $transactionID;
		$view->customer = $this->customer;
		$view->websiteDetails = $websiteDetails;
		
		$html = $view->render('/templates/elements/webshop/emails/open_order.php');
		$mail->setSubject('Open Order');
        $settingsModel = new Model_DbTable_Settings();
        $settings = $settingsModel->getAllSettings();
		$settingsModel = new Model_DbTable_Settings();
        $settings = $settingsModel->getAllSettings();
        $mail->setFrom($websiteDetails['website_name']);
        $mail->setBodyHtml($html);
        $mail->send();
        
        return true;
    }
	
	public function handle_postback($data){		
	
		$orderID 	   = $data->orderID;
		$paymentID 	   = $data->paymentID;
		$status        = $data->status;	
		$statusCode    = $data->statusCode;	
		$tmp 		   = $this->retrieve_order_obj($orderID);
		$is_paid       = 0;	
		$real_order_id = 0;
		$amount        = ( $data->amount ) / 100;	
		$customer = Webshop_Frontend_Customer::getCustomer('HkvDB', $tmp['user_id']); // for unserialize				
		$webshopOrder = unserialize($tmp['data']);		
		switch (strtoupper($status)){	
			case "OK": 
				$is_paid 	   = 1;
				$referentie = $data->transactionID;							
				$webshopOrder->setPaid($is_paid);				
				if($tmp['real_order_id'] == null) {
					$shippingCost = $webshopOrder->getShippingCost();
					$BTW = $webshopOrder->getBTW();
					$totalPriceExVat = $webshopOrder->getTotalPriceExVat();
					$res = $webshopOrder->checkPayedOrder($referentie);
					if(!empty($res)){
						$real_order_id = $webshopOrder->orderId;	
						$webshopOrder->pay($real_order_id, $is_paid, $amount, $referentie);
						$webshopOrder->update_real_order_id($orderID , $real_order_id);						
						$webshopOrder->sendOrderConfirmation($amount, $shippingCost, $BTW, $totalPriceExVat);
					}else{
						$webshopOrder->process();
						$real_order_id = $webshopOrder->orderId;	
						$webshopOrder->pay($real_order_id, $is_paid, $amount, $referentie);
						$webshopOrder->update_real_order_id($orderID , $real_order_id);						
						$webshopOrder->sendOrderConfirmation($amount, $shippingCost, $BTW, $totalPriceExVat);
					}
					
				}				
				break;				
			case "OPEN": 	
				$is_paid = 0;				
				$referentie =  $status . ' ' . $paymentID;	
				if($tmp['real_order_id'] == null) {
					$webshopOrder->process();
					$real_order_id = $webshopOrder->orderId;	
					$webshopOrder->pay($real_order_id, $is_paid, $amount, $referentie);
					$webshopOrder->update_real_order_id($orderID , $real_order_id);	
					$shippingCost = $webshopOrder->getShippingCost();
					$BTW = $webshopOrder->getBTW();
					$totalPriceExVat = $webshopOrder->getTotalPriceExVat();
					$webshopOrder->sendOrderConfirmation($amount, $shippingCost, $BTW, $totalPriceExVat);
				}					
				break;
			case "ERR":
				if($tmp['real_order_id'] == null) {
					$webshopOrder->update_real_order_id($orderID , "ERR");
				}			
				break;
			case "REFUND":
				break;
			case "CBACK":
				break;
			
		}
	

	}
	

}
