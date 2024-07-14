<?php
/**
 * Created by Dwivedianuj9118.
 * Date: 21/10/2023
 * Time: 22:10
 */
namespace Dwivedianuj9118\PhonePePaymentGateway;

class PhonePe
{

    private const PROD_URL = 'https://api.phonepe.com/apis/hermes/pg/v1/';//PROD URL API
    protected const UAT_URL = 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/';

    private int $salt_index;
    protected string $salt_key;
    protected string $merchant_id;




    public function __construct(string $merchant_id,string $salt_key,int $salt_index)
    {

        try {
            if (empty($merchant_id)) throw new PhonepeException("You must provide an Merchant Id");
            $this->merchant_id = $merchant_id;
            if (empty($salt_key)) throw new PhonepeException("You must provide an Salt Key");
            $this->salt_key = $salt_key;
            if (empty($salt_index)) throw new PhonepeException("You must provide an Salt Index");
            $this->salt_index = $salt_index;
        } catch (PhonepeException $e) {//display error message
            echo $e->errorMessage();
        }
    }

    public function PaymentCall($merchantTransactionId, $merchantUserId, $amount, $redirectUrl, $callbackUrl, $mobileNumber, $mode=null):array
    {
        $paymentMsg="";
        $paymentCode="";
        $payUrl="";
        $payload = array(
            "merchantId" => "$this->merchant_id",
            "merchantTransactionId" => "$merchantTransactionId",
            "merchantUserId" => "$merchantUserId",
            "amount" => $amount,
            "redirectUrl" => "$redirectUrl",
            "redirectMode" => "POST",
            "callbackUrl" => "$callbackUrl",
            "mobileNumber" => "$mobileNumber",
            "paymentInstrument" => array(
                "type" => "PAY_PAGE"
            )
        );

        $payload_str = json_encode($payload);
        $base64_payload = base64_encode($payload_str);
        $hashString = $base64_payload . "/pg/v1/pay" . $this->salt_key;
        $hashedValue = hash('sha256', $hashString);
        $result = $hashedValue . "###" . $this->salt_index;

        if($mode=='UAT')
        $url = self::UAT_URL.'pay';
        else
        $url= self::PROD_URL.'pay';
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'request' => "$base64_payload",
            ]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "accept: application/json",
                "X-VERIFY: $result",
            ],
        ]);
     
        $response = curl_exec($curl);

        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
         return [
             'responseCode'=>400,
             'error'=>$err
         ];
        } else {
            $res = json_decode($response);

            if(isset($res->success) && $res->success=='1'){
                $paymentCode=$res->code;
                $paymentMsg=$res->message;
                $payUrl=$res->data->instrumentResponse->redirectInfo->url;


            }else{
                return[
                    'responseCode'=>$res->code,
                    'url'=>'',
                    'msg'=>$res->message,
                    'status'=>$res->status ?? 'Error from PhonePe Server',
                ];
            }
        }
        return[
            'responseCode'=>200,
            'url'=>$payUrl,
            'msg'=>$paymentMsg,
            'status'=>$paymentCode,
        ];
    }

    public function PaymentStatus($merchantId, $merchantTransactionId, $mode = null):array
    {
        $paymentMsg="";
        $paymentStatus="";
        $txnid="";
        $hashString = "/pg/v1/status/$merchantId/$merchantTransactionId" . $this->salt_key;
        $hashedValue = hash('sha256', $hashString);
        $result = $hashedValue . "###" . $this->salt_index;
        if ($mode == 'UAT')
            $url = self::UAT_URL.'status/'.$merchantId.'/'.$merchantTransactionId;
        else
            $url = self::PROD_URL.'status/'.$merchantId.'/'.$merchantTransactionId;
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "X-MERCHANT-ID:$merchantId",
                "X-VERIFY:$result",
                "accept: application/json",
               
            ],
        ]);
        $response = curl_exec($curl);

        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
         return [
             'responseCode'=>400,
             'error'=>$err
         ];
        } else {
            $res = json_decode($response);

            if(isset($res->success) && $res->success=='1'){
                $paymentStatus=$res->data->responseCode;
                $paymentMsg=$res->message;
                $txnid=$res->data->merchantTransactionId;


            }
        }
        return[
            'responseCode'=>200,
            'txn'=>$txnid,
            'msg'=>$paymentMsg,
            'status'=>$paymentStatus,
        ];
       
    }


}