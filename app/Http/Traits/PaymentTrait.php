<?php
namespace App\Http\Traits;
use Illuminate\Support\Facades\Http;

trait PaymentTrait {

    public function almadar_paid(string $mobile_number, float $total_price, array $products, int $requestID){

        $mobile_number = substr($mobile_number, 1);
        $data = [
            "PhoneNumber" => '00218'.$mobile_number.'',
            "CompanyName" => env('COMPANY_NAME'),
            "serviceNo" => 10,
            "productNo" => $products[0]['classification_id'],
            "Value" => $total_price,
            "RequestID" => $requestID,
            "Token" => env('ALMADAR_TOKEN'),
            "Date" => now()->format("Y-m-d")
        ];
        $response = Http::asMultipart()->post(''.env('ALMADAR_API').'/path/pay', $data);
        $responseCode = $response->status();
        return $response;
    }

    public function libyana_paid(string $mobile_number, float $total_price, array $products, int $requestID){

    }
    
}
