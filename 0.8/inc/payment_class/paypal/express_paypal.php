<?php
/*
 Plugin Name: Web to Print Plugin for Facebook
 Plugin URI: http://www.delivergo.com
 Description: payment with Paypal, Google, Amazon
 Author: Normprint Ltd.
 Version: 1.0
*/
class EXPRESS_PAYPAL {
    function EXPRESS_PAYPAL() {
        global $vt_config;
        
        $this->TEXTS = $vt_config;
        
        //$_SESSION["Payment_Amount"] = 100;
        /********************************************
        PayPal API Module
         
        Defines all the global variables and the wrapper functions 
        ********************************************/
        $this->PROXY_HOST = '127.0.0.1';
        $this->PROXY_PORT = '808';

        $SandboxFlag = $this->TEXTS["paypal"]["SandboxFlag"];

        //'------------------------------------
        //' PayPal API Credentials
        //' Replace <API_USERNAME> with your API Username
        //' Replace <API_PASSWORD> with your API Password
        //' Replace <API_SIGNATURE> with your Signature
        //'------------------------------------
        $this->API_UserName = $this->TEXTS["paypal"]["API_UserName"];
        $this->API_Password = $this->TEXTS["paypal"]["API_Password"];
        $this->API_Signature = $this->TEXTS["paypal"]["API_Signature"];
        // BN Code     is only applicable for partners
        $this->sBNCode = "PP-ECWizard";
        
        
        /*    
        ' Define the PayPal Redirect URLs.  
        '     This is the URL that the buyer is first sent to do authorize payment with their paypal account
        '     change the URL depending if you are testing on the sandbox or the live PayPal site
        '
        ' For the sandbox, the URL is       https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=
        ' For the live site, the URL is        https://www.paypal.com/webscr&cmd=_express-checkout&token=
        */
        
        if ($SandboxFlag == true) 
        {
            $this->API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
            $this->PAYPAL_URL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
        }
        else
        {
            $this->API_Endpoint = "https://api-3t.paypal.com/nvp";
            $this->PAYPAL_URL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
        }

        $this->USE_PROXY = false;
        $this->version="64";

    }

    /* An express checkout transaction starts with a token, that
       identifies to PayPal your transaction
       In this example, when the script sees a token, the script
       knows that the buyer has already authorized payment through
       paypal.  If no token was found, the action is to send the buyer
       to PayPal to first authorize payment
       */

    /*   
    '-------------------------------------------------------------------------------------------------------------------------------------------
    ' Purpose:     Prepares the parameters for the SetExpressCheckout API Call.
    ' Inputs:  
    '        paymentAmount:      Total value of the shopping cart
    '        currencyCodeType:     Currency code value the PayPal API
    '        paymentType:         paymentType has to be one of the following values: Sale or Order or Authorization
    '        returnURL:            the page where buyers return to after they are done with the payment review on PayPal
    '        cancelURL:            the page where buyers return to when they cancel the payment review on PayPal
    '--------------------------------------------------------------------------------------------------------------------------------------------    
    */
    function CallShortcutExpressCheckout( $currencyCodeType, $paymentType, $returnURL, $cancelURL, $data_cart_items, $taxamt = 0, $shipamt = 0, $handlingamt = 0, $shipdiscount = 0, $insuranceamt = 0) 
    {
        //------------------------------------------------------------------------------------------------------------------------------------
        // Construct the parameter string that describes the SetExpressCheckout API call in the shortcut implementation
          // simple review your information
       /* $nvpstr = "&PAYMENTREQUEST_0_AMT=". $paymentAmount;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_PAYMENTACTION=" . $paymentType;
        $nvpstr = $nvpstr . "&RETURNURL=" . $returnURL;
        $nvpstr = $nvpstr . "&CANCELURL=" . $cancelURL;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_CURRENCYCODE=" . $currencyCodeType;
       */ 
        
        $nvpstr .= "&RETURNURL={$returnURL}";
        $nvpstr .= "&CANCELURL={$cancelURL}";
        $nvpstr .= "&PAYMENTREQUEST_0_PAYMENTACTION={$paymentType}";
        
        foreach ($data_cart_items as $index => $data) {
            $nvpstr .= "&L_PAYMENTREQUEST_0_NAME{$index}={$data["ITEM_NAME"]}";
            $nvpstr .= "&L_PAYMENTREQUEST_0_NUMBER{$index}={$data["ITEM_SKU"]}";
            $nvpstr .= "&L_PAYMENTREQUEST_0_DESC{$index}={$data["ITEM_DESCRIPTION"]}";
            $nvpstr .= "&L_PAYMENTREQUEST_0_AMT{$index}={$data["ITEM_UNIT_PRICE"]}";
            $nvpstr .= "&L_PAYMENTREQUEST_0_QTY{$index}={$data["ITEM_QTY"]}";
            $total_money += floatval($data["ITEM_UNIT_PRICE"]) * floatval($data["ITEM_QTY"]);
        }   // end for
        $total_amount = $total_money + floatval($taxamt) + floatval($shipamt) + floatval($handlingamt) + floatval($insuranceamt) - floatval($shipdiscount);
        $_SESSION["Payment_Amount"] = $total_amount;
        
        $nvpstr .= "&PAYMENTREQUEST_0_ITEMAMT={$total_money}";
        $nvpstr .= "&PAYMENTREQUEST_0_TAXAMT={$taxamt}";
        $nvpstr .= "&PAYMENTREQUEST_0_SHIPPINGAMT={$shipamt}";
        $nvpstr .= "&PAYMENTREQUEST_0_HANDLINGAMT={$handlingamt}";
        $nvpstr .= "&PAYMENTREQUEST_0_SHIPDISCAMT=-{$shipdiscount}";
        $nvpstr .= "&PAYMENTREQUEST_0_INSURANCEAMT={$insuranceamt}";
        $nvpstr .= "&PAYMENTREQUEST_0_AMT={$total_amount}";
        
        $nvpstr .= "&PAYMENTREQUEST_0_CURRENCYCODE={$currencyCodeType}";
        $nvpstr .= "&ALLOWNOTE=1"; // Provide a value of 1 to indicate that the buyer may enter a note to you on the PayPal Review page during checkout. 
        $nvpstr .= "&PAGESTYLE=Normprint";  // Set PAGESTYLE to the Page Style Name you defined in your account.
        
        // change header background color and header border color
        $nvpstr .= "&HDRBACKCOLOR=ffffff&HDRBORDERCOLOR=ffffff";
        
        // add shipping address when customer choose his address from your website
        if ($this->PAYPAL_VT_SHIPTOSTREET) {
            $nvpstr .= "&REQCONFIRMSHIPPING=1";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTONAME={$this->PAYPAL_VT_SHIPTONAME}";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTOSTREET={$this->PAYPAL_VT_SHIPTOSTREET}";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTOCITY={$this->PAYPAL_VT_SHIPTOCITY}";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTOSTATE={$this->PAYPAL_VT_SHIPTOSTATE}";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE={$this->PAYPAL_VT_SHIPTOCOUNTRYCODE}";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTOZIP={$this->PAYPAL_VT_SHIPTOZIP}";
            $nvpstr .= "&PAYMENTREQUEST_0_SHIPTOPHONENUM={$this->PAYPAL_VT_SHIPTOPHONENUM}";
        }
        // change the language displayed on the PayPal pages
        /*
        AU - Australia
        AT - Austria
        BE - Belgium
        CA - Canada
        CH - Switzerland
        CN - China
        DE - Germany
        ES - Spain
        GB - United Kingdom
        FR - France
        IT - Italy
        NL - Netherlands
        PL - Poland
        US - United States
        */
        if ($this->PAYPAL_VT_LOCALECODE) {
            $nvpstr .= "&LOCALECODE={$this->PAYPAL_VT_LOCALECODE}";
        }
        $_SESSION["currencyCodeType"] = $currencyCodeType;      
        $_SESSION["PaymentType"] = $paymentType;

        //'--------------------------------------------------------------------------------------------------------------- 
        //' Make the API call to PayPal
        //' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.  
        //' If an error occured, show the resulting errors
        //'---------------------------------------------------------------------------------------------------------------
        $resArray = $this->hash_call("SetExpressCheckout", $nvpstr);
        $ack = strtoupper($resArray["ACK"]);
        
        if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING")
        {
            $token = urldecode($resArray["TOKEN"]);
            $_SESSION['TOKEN'] = $token;
        }
           
        return $resArray;
    }

    /*   
    '-------------------------------------------------------------------------------------------------------------------------------------------
    ' Purpose:     Prepares the parameters for the SetExpressCheckout API Call.
    ' Inputs:  
    '        paymentAmount:      Total value of the shopping cart
    '        currencyCodeType:     Currency code value the PayPal API
    '        paymentType:         paymentType has to be one of the following values: Sale or Order or Authorization
    '        returnURL:            the page where buyers return to after they are done with the payment review on PayPal
    '        cancelURL:            the page where buyers return to when they cancel the payment review on PayPal
    '        shipToName:        the Ship to name entered on the merchant's site
    '        shipToStreet:        the Ship to Street entered on the merchant's site
    '        shipToCity:            the Ship to City entered on the merchant's site
    '        shipToState:        the Ship to State entered on the merchant's site
    '        shipToCountryCode:    the Code for Ship to Country entered on the merchant's site
    '        shipToZip:            the Ship to ZipCode entered on the merchant's site
    '        shipToStreet2:        the Ship to Street2 entered on the merchant's site
    '        phoneNum:            the phoneNum  entered on the merchant's site
    '--------------------------------------------------------------------------------------------------------------------------------------------    
    */
    function CallMarkExpressCheckout( $paymentAmount, $currencyCodeType, $paymentType, $returnURL, 
                                      $cancelURL, $shipToName, $shipToStreet, $shipToCity, $shipToState,
                                      $shipToCountryCode, $shipToZip, $shipToStreet2, $phoneNum
                                    ) 
    {
        //------------------------------------------------------------------------------------------------------------------------------------
        // Construct the parameter string that describes the SetExpressCheckout API call in the shortcut implementation
        
        $nvpstr="&PAYMENTREQUEST_0_AMT=". $paymentAmount;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_PAYMENTACTION=" . $paymentType;
        $nvpstr = $nvpstr . "&RETURNURL=" . $returnURL;
        $nvpstr = $nvpstr . "&CANCELURL=" . $cancelURL;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_CURRENCYCODE=" . $currencyCodeType;
        $nvpstr = $nvpstr . "&ADDROVERRIDE=1";
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTONAME=" . $shipToName;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTREET=" . $shipToStreet;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTREET2=" . $shipToStreet2;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOCITY=" . $shipToCity;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTATE=" . $shipToState;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=" . $shipToCountryCode;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOZIP=" . $shipToZip;
        $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOPHONENUM=" . $phoneNum;
        
        $_SESSION["currencyCodeType"] = $currencyCodeType;      
        $_SESSION["PaymentType"] = $paymentType;

        //'--------------------------------------------------------------------------------------------------------------- 
        //' Make the API call to PayPal
        //' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.  
        //' If an error occured, show the resulting errors
        //'---------------------------------------------------------------------------------------------------------------
        $resArray = $this->hash_call("SetExpressCheckout", $nvpstr);
        $ack = strtoupper($resArray["ACK"]);
        if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING")
        {
            $token = urldecode($resArray["TOKEN"]);
            $_SESSION['TOKEN'] = $token;
        }
           
        return $resArray;
    }
    
    /*
    '-------------------------------------------------------------------------------------------
    ' Purpose:     Prepares the parameters for the GetExpressCheckoutDetails API Call.
    '
    ' Inputs:  
    '        None
    ' Returns: 
    '        The NVP Collection object of the GetExpressCheckoutDetails Call Response.
    '-------------------------------------------------------------------------------------------
    */
    function GetShippingDetails( $token )
    {
        //'--------------------------------------------------------------
        //' At this point, the buyer has completed authorizing the payment
        //' at PayPal.  The function will call PayPal to obtain the details
        //' of the authorization, incuding any shipping information of the
        //' buyer.  Remember, the authorization is not a completed transaction
        //' at this state - the buyer still needs an additional step to finalize
        //' the transaction
        //'--------------------------------------------------------------
       
        //'---------------------------------------------------------------------------
        //' Build a second API request to PayPal, using the token as the
        //'  ID to get the details on the payment authorization
        //'---------------------------------------------------------------------------
        $nvpstr = "&TOKEN=" . $token;

        //'---------------------------------------------------------------------------
        //' Make the API call and store the results in an array.  
        //'    If the call was a success, show the authorization details, and provide
        //'     an action to complete the payment.  
        //'    If failed, show the error
        //'---------------------------------------------------------------------------
        $resArray = $this->hash_call("GetExpressCheckoutDetails",$nvpstr);
        $ack = strtoupper($resArray["ACK"]);
        if($ack == "SUCCESS" || $ack=="SUCCESSWITHWARNING")
        {    
            $_SESSION['payer_id'] = $resArray['PAYERID'];
        } 
        return $resArray;
    }
    
    /*
    '-------------------------------------------------------------------------------------------------------------------------------------------
    ' Purpose:     Prepares the parameters for the GetExpressCheckoutDetails API Call.
    '
    ' Inputs:  
    '        sBNCode:    The BN code used by PayPal to track the transactions from a given shopping cart.
    ' Returns: 
    '        The NVP Collection object of the GetExpressCheckoutDetails Call Response.
    '--------------------------------------------------------------------------------------------------------------------------------------------    
    */
    function ConfirmPayment( $FinalPaymentAmt )
    {
        /* Gather the information to make the final call to
           finalize the PayPal payment.  The variable nvpstr
           holds the name value pairs
           */
        
        //Format the other parameters that were stored in the session from the previous calls    
        $token = urlencode($_SESSION['TOKEN']);
        $paymentType = urlencode($_SESSION['PaymentType']);
        $currencyCodeType = urlencode($_SESSION['currencyCodeType']);
        $payerID = urlencode($_SESSION['payer_id']);

        $serverName = urlencode($_SERVER['SERVER_NAME']);

        $nvpstr  = '&TOKEN=' . $token . '&PAYERID=' . $payerID . '&PAYMENTREQUEST_0_PAYMENTACTION=' . $paymentType . '&PAYMENTREQUEST_0_AMT=' . $FinalPaymentAmt;
        $nvpstr .= '&PAYMENTREQUEST_0_CURRENCYCODE=' . $currencyCodeType . '&IPADDRESS=' . $serverName; 

         /* Make the call to PayPal to finalize payment
            If an error occured, show the resulting errors
            */
        $resArray=$this->hash_call("DoExpressCheckoutPayment",$nvpstr);

        /* Display the API response back to the browser.
           If the response from PayPal was a success, display the response parameters'
           If the response was an error, display the errors received using APIError.php.
           */
        $ack = strtoupper($resArray["ACK"]);

        return $resArray;
    }
    
    /*
    '-------------------------------------------------------------------------------------------------------------------------------------------
    ' Purpose:     This function makes a DoDirectPayment API call
    '
    ' Inputs:  
    '        paymentType:        paymentType has to be one of the following values: Sale or Order or Authorization
    '        paymentAmount:      total value of the shopping cart
    '        currencyCode:         currency code value the PayPal API
    '        firstName:            first name as it appears on credit card
    '        lastName:            last name as it appears on credit card
    '        street:                buyer's street address line as it appears on credit card
    '        city:                buyer's city
    '        state:                buyer's state
    '        countryCode:        buyer's country code
    '        zip:                buyer's zip
    '        creditCardType:        buyer's credit card type (i.e. Visa, MasterCard ... )
    '        creditCardNumber:    buyers credit card number without any spaces, dashes or any other characters
    '        expDate:            credit card expiration date
    '        cvv2:                Card Verification Value 
    '        
    '-------------------------------------------------------------------------------------------
    '        
    ' Returns: 
    '        The NVP Collection object of the DoDirectPayment Call Response.
    '--------------------------------------------------------------------------------------------------------------------------------------------    
    */


    function DirectPayment( $paymentType, $paymentAmount, $creditCardType, $creditCardNumber,
                            $expDate, $cvv2, $firstName, $lastName, $street, $city, $state, $zip, 
                            $countryCode, $currencyCode )
    {
        //Construct the parameter string that describes DoDirectPayment
        $nvpstr = "&AMT=" . $paymentAmount;
        $nvpstr = $nvpstr . "&CURRENCYCODE=" . $currencyCode;
        $nvpstr = $nvpstr . "&PAYMENTACTION=" . $paymentType;
        $nvpstr = $nvpstr . "&CREDITCARDTYPE=" . $creditCardType;
        $nvpstr = $nvpstr . "&ACCT=" . $creditCardNumber;
        $nvpstr = $nvpstr . "&EXPDATE=" . $expDate;
        $nvpstr = $nvpstr . "&CVV2=" . $cvv2;
        $nvpstr = $nvpstr . "&FIRSTNAME=" . $firstName;
        $nvpstr = $nvpstr . "&LASTNAME=" . $lastName;
        $nvpstr = $nvpstr . "&STREET=" . $street;
        $nvpstr = $nvpstr . "&CITY=" . $city;
        $nvpstr = $nvpstr . "&STATE=" . $state;
        $nvpstr = $nvpstr . "&COUNTRYCODE=" . $countryCode;
        $nvpstr = $nvpstr . "&IPADDRESS=" . $_SERVER['REMOTE_ADDR'];

        $resArray = $this->hash_call("DoDirectPayment", $nvpstr);

        return $resArray;
    }


    /**
      '-------------------------------------------------------------------------------------------------------------------------------------------
      * hash_call: Function to perform the API call to PayPal using API signature
      * @methodName is name of API  method.
      * @nvpStr is nvp string.
      * returns an associtive array containing the response from the server.
      '-------------------------------------------------------------------------------------------------------------------------------------------
    */
    function hash_call($methodName,$nvpStr)
    {

        //setting the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$this->API_Endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        //turning off the server and peer verification(TrustManager Concept).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POST, 1);
        
        //if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
       //Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php 
        if($this->USE_PROXY)
            curl_setopt ($ch, CURLOPT_PROXY, $this->PROXY_HOST. ":" . $this->PROXY_PORT); 

        //NVPRequest for submitting to server
        $nvpreq="METHOD=" . urlencode($methodName) . "&VERSION=" . urlencode($this->version) . "&PWD=" . urlencode($this->API_Password) . "&USER=" . urlencode($this->API_UserName) . "&SIGNATURE=" . urlencode($this->API_Signature) . $nvpStr . "&BUTTONSOURCE=" . urlencode($this->sBNCode);
        
        //setting the nvpreq as POST FIELD to curl
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

        //getting response from server
        $response = curl_exec($ch);

        //convrting NVPResponse to an Associative Array
        $nvpResArray=$this->deformatNVP($response);
        $nvpReqArray=$this->deformatNVP($nvpreq);
        $_SESSION['nvpReqArray']=$nvpReqArray;

        if (curl_errno($ch)) 
        {
            // moving to display page to display curl errors
              $_SESSION['curl_error_no']=curl_errno($ch) ;
              $_SESSION['curl_error_msg']=curl_error($ch);

              //Execute the Error handling module to display errors. 
        } 
        else 
        {
             //closing the curl
              curl_close($ch);
        }

        return $nvpResArray;
    }

    /*'----------------------------------------------------------------------------------
     Purpose: Redirects to PayPal.com site.
     Inputs:  NVP string.
     Returns: 
    ----------------------------------------------------------------------------------
    */
    function RedirectToPayPal ( $token )
    {
        
        // Redirect to paypal.com here
        $payPalURL = $this->PAYPAL_URL . $token;
        //header("Location: ".$payPalURL);
        $this->redirect($payPalURL);
    }
    function redirect($url) {
        print "<html>\n<head>\n<meta http-equiv=\"refresh\" content=\"0;URL=$url\">\n</head>\n</html>";
        exit;
    }
    //=================================================================================================
    
    /*'----------------------------------------------------------------------------------
     * This function will take NVPString and convert it to an Associative Array and it will decode the response.
      * It is usefull to search for a particular key and displaying arrays.
      * @nvpstr is NVPString.
      * @nvpArray is Associative Array.
       ----------------------------------------------------------------------------------
      */
    function deformatNVP($nvpstr)
    {
        $intial = 0;
         $nvpArray = array();

        while(strlen($nvpstr))
        {
            //postion of Key
            $keypos = strpos($nvpstr,'=');
            //position of value
            $valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

            /*getting the Key and Value values and storing in a Associative Array*/
            $keyval = substr($nvpstr,$intial,$keypos);
            $valval = substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
            //decoding the respose
            $nvpArray[urldecode($keyval)] = urldecode( $valval);
            $nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
         }
        return $nvpArray;
    }
//=================================================================================================
}
?>