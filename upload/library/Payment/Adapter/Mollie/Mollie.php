<?php
class Payment_Adapter_Mollie extends Payment_AdapterAbstract implements \Box\InjectionAwareInterface
{

    private $config = array();

    protected $di;
    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function __construct($config)
    {
        $this->config = $config;

        foreach (['live_api_key', 'test_api_key'] as $key) {
            if (!isset($this->config[$key])) {
                throw new \Box_Exception('Payment gateway Mollie is not configured. Please set ' . $key);
            }
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments' => true,
            'description' => 'You can get the API keys directly from Mollie in the dashboard. <a href="https://my.mollie.com/dashboard/signup/16399288">Here</a> you can register with Mollie.<br><br>If you have any questions about the module, please contact us here: info@it-rwo.eu<br><br>',
            'logo' => array(
                'logo' => 'Mollie/Mollie.png',
                'height' => '50px',
                'width' => '50px',
            ),
            'form' => array(
                'live_api_key' => array('text',
                    array(
                        'label' => 'Merchants Live API Key',
                    ),
                ),
                'test_api_key' => array('text',
                    array(
                        'label' => 'Merchants Test API Key',
                    ),
                ),
            ),
        );
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Mollie"');
        $order_id = 'boxbilling-' . $invoice->id;
        $params = array(
            'amount' => array(
                'currency' => $invoice->currency,
                'value' => number_format($invoiceService->getTotalWithTax($invoice), 2, ".", "")
            ),
            'description' => $order_id,
            'webhookUrl' => $this->config['notify_url'],
            'redirectUrl' => $this->config['thankyou_url'],
        );
        $invoiceLink = $this->send_request("https://api.mollie.com/v2/payments", $params);
        return $this->_generateForm($invoiceLink->_links->checkout->href);
    }

    public function send_request($url, $data, $post = 1)
    {
        if($this->config['test_mode'] == '0') {
            $token = $this->config['live_api_key'];
        } else {
            $token = $this->config['test_api_key'];
        }

        $client = $this->getHttpClient()->withOptions([
            'verify_peer'   => false,
            'verify_host'   => false,
            'timeout'       => 600
        ]);

        if($post == 1){
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$token
                ],
                'body' => json_encode($data)
            ]);
        } else {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$token
                ]
            ]);
        }
        return json_decode($response->getContent());
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $url_check = 'https://api.mollie.com/v2/payments/' . $_POST['id'];
        $state = $this->send_request($url_check, array(), 0);
        if ($state->status != 'complete' && $state->status != 'paid') {
            throw new Payment_Exception("Invalid IPN sent");
        }
        if ($this->isIpnDuplicate($state->id, $state->amount->value)) {
            throw new Payment_Exception('IPN is duplicate');
        }

        $invoice = $api_admin->invoice_get(array('id'=>$data['get']['bb_invoice_id']));
        $tx = $this->di['db']->getExistingModelById('Transaction', $id);
        $client_id = $invoice['client']['id'];
        $tx->invoice_id = $invoice['id'];
        $tx->txn_status = $state->status;
        $tx->txn_id = $state->id;
        $tx->amount = $state->amount->value;
        $tx->currency = $state->amount->currency;
        $this->di['db']->store($tx);
        $bd = array(
            'id' =>  $client_id,
            'amount' => $tx->amount,
            'description' => $state->method . ' transaction ' . $state->id,
            'type' => 'transaction',
            'rel_id' => $tx->id
        );
        $api_admin->client_balance_add_funds($bd);
        if($tx->invoice_id) {
            $api_admin->invoice_pay_with_credits(array('id'=>$tx->invoice_id));
        }
        $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));

        $d = array(
            'id'        => $id,
            'error'     => '',
            'error_code'=> '',
            'status'    => 'processed',
            'updated_at'=> date('Y-m-d H:i:s')
        );
        $api_admin->invoice_transaction_update($d);
    }

    protected function _generateForm($checkout)
    {
        $htmlOutput = '<a href="'.$checkout.'" class="btn btn-success btn-sm">Pay now</a>';
        return $htmlOutput;
    }

    public function isIpnDuplicate($txID, $txAmount)
    {
        $sql = 'SELECT id
                FROM transaction
                WHERE txn_id = :transaction_id
                  AND amount = :transaction_amount
                LIMIT 2';

        $bindings = array(
            ':transaction_id' => $txID,
            ':transaction_amount' => $txAmount,
        );

        $rows = $this->di['db']->getAll($sql, $bindings);
        if (count($rows) >= 1) {
            return true;
        }

        return false;
    }
}
