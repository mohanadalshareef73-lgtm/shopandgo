@php
    $ph = fn ($p) => (str_contains((string) $p, '@') || strlen(preg_replace('/\D/', '', (string) $p)) > 13) ? '' : $p;
    $c = $d->customer; $fee = $d->fee_payer === 'company' ? 0 : (float) $d->fee; $who = $d->courier_name ?: optional($d->courier)->name;
    $code = $order ? $order->code : ($cart ? 'SG-' . $cart->id : $d->code);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال عربون {{ $code }}</title>
    <style>

        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@500&display=swap');

        * {
            font-family: 'Cairo', sans-serif;
        }

        body {
            direction: rtl;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Ensures the body takes full height of the viewport */
        }

        .container {
            width: 210mm;
            margin: 0 auto;
            padding: 5mm 20mm;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            flex: 1; /* Allows the container to grow and take up available space */
            display: flex;
            flex-direction: column;
        }

        .header, .footer {
            text-align: center;
            margin-bottom: 5px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #545353; /* Adding a bottom border */
        }

        .header img {
            height: 60px;
        }

        .header h5 {
            margin: 0;
            padding-left: 15px;
            font-size: 18px;
            flex-grow: 1;
            text-align: left;
        }

        .details {
            width: 100%;
            margin-bottom: 20px;
        }

        .details table {
            width: 100%;
            border-collapse: collapse;
        }

        .details th, .details td {
            border: 1px solid #000;
            text-align: center;
        }

        .info span, .notes span {
            font-weight: bold;
        }

        .info {
        }

        .dates {
            display: flex;
            justify-content: space-between;
        }

        .dates div {
            text-align: right;
        }

        .footer {
            margin-top: auto; /* Ensures the footer is pushed to the bottom */
            border-top: 2px solid #000; /* Adding a top border */
        }

    </style>
    <style>@media print { body { background: #fff; } .container { box-shadow: none; } .noprint { display: none; } }</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('assets/images/loggo.png') }}" alt="Shop and Go Logo">
            <h5>SHOP AND GO</h5>
        </div>

        <h4 style="text-align: right; margin-top: 0px; margin-bottom: -5px;"> # {{ $code }} </h4>

        <div class="info" style="margin-bottom: 15px;">
            <div><span style="font-size: 24px;">اسم العميل : </span> <span style="font-size: 24px; color: #282E4D;">{{ optional($c)->name ?: $d->recipient_name }}{{ optional($c)->code ? ' - ' . $c->code : '' }}</span></div>
        </div>

        <span style="color: rgb(187, 122, 0); font-size: 14px;">إيصال عربون{{ $cart ? ' · حجز سلة ' . $cart->retailer_label . ' #' . $cart->id : '' }}</span>

        <div class="dates" style="font-size: 14px;">
            <div><p>تاريخ الإيصال: <span style="color: #282E4D;">{{ now()->format('Y-m-d') }}</span></p></div>
            <div><p>الهاتف: <span style="color: #282E4D;">{{ $ph($d->recipient_phone) }}</span></p></div>
        </div>

        <div class="details" style="font-size: 14px;">
            <table>
                <tr>
                    <th>البيان</th>
                    <th>العنوان</th>
                    <th>المندوب</th>
                    <th>القيمة</th>
                </tr>
                <tr style="color: #282E4D;">
                    <td>عربون{{ $cart ? ' (سلة ' . $cart->retailer_label . ')' : '' }}</td>
                    <td>{{ $d->address ?: '-' }}</td>
                    <td>{{ $who ?: '-' }}</td>
                    <td>{{ $f($d->amount) }} د.ل</td>
                </tr>
                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td>التوصيل</td>
                    <td>{{ $f($fee) }} د.ل</td>
                </tr>
                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td style="color: white; background-color: #282E4D;">المطلوب من العميل</td>
                    <td style="color: white; background-color: #282E4D;">{{ $f((float) $d->amount + $fee) }} د.ل</td>
                </tr>
            </table>
        </div>

        @if ($invoice)
        <div class="info" style="font-size: 14px; padding-bottom: 10px;"><div><span>{{ $invoice->welcome }}</span></div></div>
        <div class="notes" style="font-size: 14px;">
            <div><span style="color: #282E4D;">{{ $invoice->note }}</span></div>
            <div style="padding-top: 10px;">{{ $invoice->note2 }}</div>
            <div style="margin-top: 10px;"><span>نحن سعداء لخدمتكم.</span></div>
        </div>
        @endif

        <div class="footer" style="font-size: 14px;">
            <p>{{ optional($invoice)->address }}</p>
        </div>
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
