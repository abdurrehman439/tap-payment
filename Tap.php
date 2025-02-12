<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tap extends CI_Controller {

    private $api_key  = 'sk_test_XKokBfNWv6FIYuTMg5sLPjhJ';
    private $api_url = 'https://api.tap.company/v2/';

    public function __construct() 
    {
        parent::__construct();
        $this->load->helper('url');
    }

        //create payment 
    public function create_payment_save_card_for_recurring() {

        $amount   = 4;
        $currency = 'KWD';  //USD

        $data = [
            'amount'          => number_format($amount, 2),
            'currency'        => $currency,
            
            'threeDSecure'    => true,  
            'save_card'       => true,  // Save the card for future use
            'three_d_secure'  => true,  // Enable 3D Secure if necessary

            'description'          => 'Demo Order Payment',
            'statement_descriptor' => 'Demo Company Name',
            'metadata' => [
                'order_id'      => uniqid('ORDER_'),
                'customer_name' => 'Customer Name'
            ],
            'reference' => [
                'transaction'   => uniqid('TRANS_'),
                'order'         => uniqid('ORDER_')
            ],
            'receipt' => [
                'email'  => true,
                'sms'    => true
            ],
            'customer' => [
                'first_name'  => 'Demo ',
                'middle_name' => '',
                'last_name'   => 'Name',
                'email'       => 'demo@example.com',
                'phone' => [
                    'country_code' => '965',
                    'number'       => '50000000'
                ]
            ],
            'source' => [
                'id' => 'src_card'
            ],
            'post' => [
                'url' => base_url('tap/payment_callback')
            ],
            'redirect' => [
                'url' => base_url('tap/payment_return')
            ]
        ];

        $response= $this->send_api_request('charges', $data,'POST');
		
        if($response->error){
            echo $response->message;  die;
        }

        redirect($response->data->transaction->url);
    }

    public function payment_callback() 
    {
        $charge_id = $this->input->get('tap_id');
        $response  = $this->send_api_request('charges/' . $charge_id, [], 'GET');
        echo  json_encode($response);
    }

    public function payment_return() 
    {
        $charge_id = $this->input->get('tap_id');
		
        $response  = $this->send_api_request('charges/' . $charge_id, [], 'GET');
		
        if ($response->error or (isset($response->data->status) && $response->data->status !== 'CAPTURED')) {

            echo ($response->message  !='') ? $response->message : $response->data->response->message; die;
        } 

        $card_id              = $response->data->card->id;
        $payment_agreement_id = $response->data->payment_agreement->id;
        $customer_id          = $response->data->customer->id;
    
        echo "Card saved successfully. Card ID: " . $card_id . "<br>";
        echo "Payment Agreement ID: " . $payment_agreement_id . "<br>";
        echo "Customer ID: " . $customer_id . "<br><br><br>";

        echo  json_encode($response);
    }

    //  make recurring payment 
    public function make_recurring_payments() 
    {
        // get from database 
        $payment_agrement = 'payment_agreement_KXs3252580pRJq10901z343'; 
        $customer_id      = 'cus_TS03A2520250800o9X41002334';
        $card_id          = 'card_yeZm222580pynh104o1m731';
        
        $response = $this->regenerate_tokens($card_id, $customer_id);
        
        if($response->error or (isset($response->data->status) and $response->data->status != 'ACTIVE')){

            echo $response->message; die;
        }
    
        $source_id_token  = $response->data->id;  //token expired after 5 mints /one transaction

        $data = [
            "amount"             => 1,
            "currency"           => "KWD",
            "customer_initiated" => false,
            "save_card"          => false,
            "payment_agreement" => [
                "id" => $payment_agrement
            ],
            "description"        => "Test Description",

            // "metadata"=> [
            //   "udf1"=> "Metadata 1"
            // ],
            // "reference"=> [
            //   "transaction"=> "txn_01",
            //   "order"=> "ord_01"
            // ],
            // "receipt"=> [
            //   "email"=> true,
            //   "sms"=> true
            // ],

            "customer" => [
                "id" => $customer_id
            ],
            "source" => [
                "id" => $source_id_token
            ],
            "redirect" => [
                "url" => base_url('tap/payment_save_card_return')
            ]
        ];

        $response = $this->recurring_payment($data);
        if($response->error or (isset($response->data->status) and $response->data->status != 'CAPTURED'))
		{
			echo ($response->message  != '') ? $response->message : $response->data->response->message; die;
		}

        echo  json_encode($response);
    }
    
    public function recurring_payment($data) 
    {
        $url     = 'charges';
        $method  = 'POST';
       
        return $this->send_api_request($url, $data,$method);
    }
    public function regenerate_tokens($card_id,$customer_id) 
    {

        $data = [
            "saved_card" => [
                "card_id"     => $card_id,
                "customer_id" => $customer_id
            ],
        ];

        $url     = 'tokens';
        $method  = 'POST';
       
       return $this->send_api_request($url, $data,$method);
    }

    public function process_payment_refund_form() 
    {
        $this->load->view('tap/refund_payment');
    }

    public function process_refund() 
    {
        $charge_id = $this->input->post('charge_id');
        $amount    = $this->input->post('amount');
		
		$response = $this->verify($charge_id);
		if($response->error  or (isset($response->data->authentication->threeDSecure->status) and $response->data->authentication->threeDSecure->status != 'AUTHENTICATED'))
		{
			echo ($response->message  !='') ? $response->message : $response->data->response->message; die;
		}
		
        $data = [
            'charge_id' => $charge_id,
            'amount'    => number_format($amount, 2),
            'currency'  => 'KWD',
            'reason'    => 'The product is out of stock',
        ];

        $response = $this->refund($data);
        if($response->error  or (isset($response->data->status) and $response->data->status != 'REFUNDED'))
		{
			echo ($response->message  !='') ? $response->message : $response->data->response->message; die;
		}

        echo  json_encode($response);
    }

    public function refund($data)
    {
       $url     = 'refunds';
       $method  = 'POST';
       
       return $this->send_api_request($url, $data,$method);
    }

    public function verify($charge_id) 
    {
        $url    = 'charges/' . $charge_id;
        $method = 'GET';

        return $this->send_api_request($url, [], $method);
    }

    private function send_api_request($endpoint, $data = [], $method = 'POST') 
    {
        $url = $this->api_url . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
        ];

        $curl_options = [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => '',
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_HTTPHEADER      => $headers,

            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
        ];

        if($method === 'POST' && !empty($data)) {
            $curl_options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        $curl = curl_init();
        curl_setopt_array($curl, $curl_options);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        $status   = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        return $this->curl_response($response);
    }
    
    private function curl_response($result)
    {
        $dt = json_decode($result);
		
		$message = 'An error occurred';
        if(isset($dt->errors))
		{
			if(is_array($dt->errors))
			{
				$message = '';
				foreach($dt->errors as $error)
				{
					$message .= $error->description;
				}
			}
			
			$response = array(
				'error'   => true,
				'message' => $message,
				'data'    => $dt
			);
	
			return (object)$response;
        }
		
		$response = array(
			'error'     => false,
			'message'   => '',
			'data'      => $dt
		);

        return (object)$response;
    }
}