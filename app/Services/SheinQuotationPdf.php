<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Output\Destination;

class SheinQuotationPdf
{
    private const LAYOUT_VERSION = 'v18q';
    private const AMAZON_LAYOUT_VERSION = 'v2';
    private const IHERB_LAYOUT_VERSION = 'v19q';

    public function __construct(private SheinQuotationCalculator $calculator, private AmazonQuotationCalculator $amazonCalculator)
    {
    }

    public function prepare(CartQuote $cart): CartQuote
    {
        return DB::transaction(function () use ($cart) {
            $record = CartQuote::lockForUpdate()->findOrFail($cart->id);
            if ($record->status !== 'succeeded') {
                throw new CartQuoteException('Quotation is not ready');
            }
            $amazon = $record->retailer === 'amazon';
            if ($amazon) {
                $record = $this->amazonCalculator->prepare($record);
            } elseif (! $record->quotation) {
                $record->quotation = $this->calculator->calculate($record, Setting::findOrFail(1));
                $record->save();
            }
            $layoutVersion = match ($record->retailer) {
                'amazon' => self::AMAZON_LAYOUT_VERSION,
                'iherb' => self::IHERB_LAYOUT_VERSION,
                default => self::LAYOUT_VERSION,
            };
            $path = ($record->retailer.'-quotations').'/cart-'.$record->id.'-'.$layoutVersion.'-'.hash('sha256',
                json_encode([$amazon ? [$record->amazon_pricing, data_get($record->payload, 'result.items')] : $record->quotation,
                    $record->pdf_overrides, $record->operator_status])).'.pdf';
            if ($record->pdf_path !== $path || ! Storage::disk('local')->exists($path)) {
                $bytes = $this->render($record);
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new CartQuoteException('Could not save quotation PDF');
                }
                // النسخة القديمة من نفس السلة ما عادش نحتاجوها (نوفرو مساحة)
                if ($record->pdf_path && $record->pdf_path !== $path && str_ends_with($record->pdf_path, '.pdf')) {
                    try {
                        Storage::disk('local')->delete($record->pdf_path);
                    } catch (\Throwable $e) {
                    }
                }
                $record->update(['pdf_path' => $path]);
            }
            return $record;
        });
    }

    public function quotationForPdf(CartQuote $cart): array
    {
        return $this->calculator->applyPdfOverrides($cart->quotation, $cart->pdf_overrides);
    }

    public function editorValues(CartQuote $cart): array
    {
        return $this->calculator->editorValues($cart->quotation, $cart->pdf_overrides);
    }

    public function revision(CartQuote $cart): string
    {
        return hash('sha256', json_encode([$cart->quotation, $cart->pdf_overrides]));
    }

    public function productImage(CartQuote $cart, int $index): ?string
    {
        $url = data_get($cart->payload, 'result.items.'.$index.'.imageUrl');
        return $this->image(is_string($url) ? $url : null, 480, $cart->retailer);
    }

    private function image(?string $url, int $width = 160, string $retailer = 'shein'): ?string
    {
        $host = strtolower(parse_url($url ?? '', PHP_URL_HOST) ?? '');
        $allowedHost = match ($retailer) {
            'amazon' => in_array($host, ['m.media-amazon.com', 'images-na.ssl-images-amazon.com', 'images-eu.ssl-images-amazon.com'], true),
            'iherb' => in_array($host, ['s3.images-iherb.com', 'cloudinary.images-iherb.com'], true),
            default => str_ends_with($host, '.ltwebstatic.com'),
        };
        if (parse_url($url ?? '', PHP_URL_SCHEME) !== 'https'
            || ! $allowedHost
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS) || parse_url($url, PHP_URL_PORT)) {
            return null;
        }
        $directory = $retailer.'-quotations/images/';
        $path = $directory.hash('sha256', $url).($width === 160 ? '' : '-'.$width).'.jpg';
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }
        try {
            $cachedPng = $directory.hash('sha256', $url).'.png';
            if (Storage::disk('local')->exists($cachedPng)) {
                $bytes = Storage::disk('local')->get($cachedPng);
            } else {
                $response = Http::withOptions(['allow_redirects' => false])->connectTimeout(3)->timeout(5)->get($url);
                if (! $response->successful()) {
                    return null;
                }
                $bytes = $response->body();
            }
            if (strlen($bytes) > 2 * 1024 * 1024) {
                return null;
            }
            $info = @getimagesizefromstring($bytes);
            if (! $info || $info[0] > 4000 || $info[1] > 4000 || $info[0] * $info[1] > 8000000) {
                return null;
            }
            $image = @imagecreatefromstring($bytes);
            if (! $image) {
                return null;
            }
            $thumb = imagescale($image, min($width, $info[0]));
            $background = imagecreatetruecolor(imagesx($thumb), imagesy($thumb));
            imagefill($background, 0, 0, imagecolorallocate($background, 255, 255, 255));
            imagecopy($background, $thumb, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb));
            ob_start();
            imagejpeg($background, null, 70);
            $png = ob_get_clean();
            imagedestroy($thumb);
            imagedestroy($background);
            imagedestroy($image);
            Storage::disk('local')->put($path, $png);
            return Storage::disk('local')->path($path);
        } catch (\Throwable $e) {
            // Product pictures are optional; pricing data must still be delivered.
            return null;
        }
    }

    private function unavailableImage(?string $path, string $retailer = 'shein'): ?string
    {
        if ($path === null) {
            return null;
        }
        $cached = $retailer.'-quotations/images/'.hash('sha256', $path).'-unavailable-v12.jpg';
        if (Storage::disk('local')->exists($cached)) {
            return Storage::disk('local')->path($cached);
        }
        $image = @imagecreatefromjpeg($path);
        if (! $image) {
            return null;
        }
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        $faded = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($faded, 0, 0, imagecolorallocate($faded, 255, 255, 255));
        imagecopymerge($faded, $image, 0, 0, 0, 0, imagesx($image), imagesy($image), 35);
        ob_start();
        imagejpeg($faded, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($faded);
        return Storage::disk('local')->put($cached, $bytes) ? Storage::disk('local')->path($cached) : null;
    }

    public function render(CartQuote $cart): string
    {
        $amazon = $cart->retailer === 'amazon';
        $quotation = $amazon ? $cart->amazon_pricing + ['company_name' => config('app.company_name', 'SHOP AND GO')]
            : $this->quotationForPdf($cart);
        $display = $amazon ? null : $this->calculator->presentation($quotation);
        $images = [];
        foreach ($amazon ? [] : $quotation['items'] as $index => $item) {
            $url = $item['provider']['imageUrl'] ?? null;
            $images[$index] = $this->image(is_string($url) ? $url : null, 160, $cart->retailer);
            if ($item['availability'] === 'unavailable') {
                $images[$index] = $this->unavailableImage($images[$index], $cart->retailer);
            }
        }
        if ($amazon) {
            $images[0] = $this->productImage($cart, 0);
        }
        $temp = storage_path('app/mpdf-temp');
        if (! is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $fontDirectories = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'P',
            'fontDir' => array_merge($fontDirectories, [resource_path('fonts/somar')]),
            'fontdata' => array_merge($fontData, ['somar' => [
                'R' => 'Somar-Regular.ttf', 'B' => 'Somar-Bold.ttf',
                'I' => 'Somar-RegularItalic.ttf', 'BI' => 'Somar-BoldItalic.ttf',
                'useOTL' => 0xFF, 'useKashida' => 75,
            ]]),
            'tempDir' => $temp, 'default_font' => 'somar', 'default_font_size' => 12,
            'margin_top' => 10, 'margin_bottom' => 15, 'margin_left' => 10, 'margin_right' => 10,
            'autoScriptToLang' => true, 'autoLangToFont' => false, 'shrink_tables_to_fit' => 1,
            'percentSubset' => 100]);
        $pdf->SetDirectionality('rtl');
        $pdf->SetTitle($cart->retailer_name.' quotation #'.$cart->id);
        $pdf->SetAuthor($quotation['company_name']);
        $pdf->SetHTMLFooter('<table width="100%" style="border-top:0.5mm solid '.($amazon ? '#ff9900' : '#1c45a3').';font-size:10pt;color:#51617b"><tr><td width="65%" dir="ltr">'.e($quotation['company_name']).' · '.$cart->retailer_name.'</td><td style="text-align:left">سلة #'.$cart->id.' · <span dir="ltr">{PAGENO} / {nbpg}</span></td></tr></table>');
        $pdf->WriteHTML(view($amazon ? 'pdf.amazon-quotation' : 'pdf.shein-quotation', compact('cart', 'quotation', 'display', 'images'))->render());
        return $pdf->Output('', Destination::STRING_RETURN);
    }

}
