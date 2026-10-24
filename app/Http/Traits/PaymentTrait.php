<?php
namespace App\Http\Traits;
use Illuminate\Support\Facades\Http;

trait PaymentTrait {

    public function almadar_paid(string $mobile_number, float $total_price, array $products, int $requestID){

        $mobile_number = substr($mobile_number, 1);
        $data = [
            "PhoneNumber" => '00218'.$mobile_number.'',
            "CompanyName" => config('system.company_name'),
            "serviceNo" => 10,
            "productNo" => $products[0]['classification_id'],
            "Value" => $total_price,
            "RequestID" => $requestID,
            "Token" => config('system.almadar_token'),
            "Date" => now()->format("Y-m-d")
        ];
        $response = Http::asMultipart()->post(''.config('system.almadar_api').'/path/pay', $data);
        $responseCode = $response->status();
        return $response;
    }

    public function libyana_paid(string $mobile_number, float $total_price, array $products, int $requestID){

    }
    
}
