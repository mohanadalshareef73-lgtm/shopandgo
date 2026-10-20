<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة INV/2024/00212</title>
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
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{asset('assets/images/loggo.png')}}" alt="Shop and Go Logo">
            <h5>SHOP AND GO</h5>
        </div>

        <h4 style="text-align: right; margin-top: 0px;  margin-bottom: -5px;"> # {{ $order->code }} </h4>

        @if (!$order->ship_price)
        <div class="info">
        @else
        <div class="info" style="margin-bottom: 15px;">
        @endif
            {{-- <div><span style="font-size: 24px;">#الفاتورة :</span> <span style="font-size: 24px; color: #282E4D;">{{ $order->code }}</span></div> --}}
            <div><span style="font-size: 24px;">اسم العميل : </span> <span style="font-size: 24px; color: #282E4D;">{{ $order->customer->name }} - {{ $order->customer->code }}</span></div>
        </div>

        @if (!$order->ship_price)
        <span
            class="text-warning" style="color: rgb(187, 122, 0); font-size: 14px;">ملاحطة
            :: اجمالي القيمة بدون سعر
            الشحن</span>
        @endif

        <div class="dates" style="font-size: 14px;">
            <div>
                <p>تاريخ الفاتورة: <span style=" color: #282E4D;">{{ now()->format('Y-m-d') }}</span></p>
            </div>
            <div>
                <p>تاريخ الطلب:  <span style=" color: #282E4D;">{{ $order->created_at->format('Y-m-d') }}</span></p>
            </div>
        </div>

        <div class="details"  style="font-size: 14px;">
            <table>
                <tr>
                    <th>المنصة</th>
                    <th>الكمية</th>
                    <th>سعر الشحن</th>
                    <th>اجمالي القيمة</th>
                </tr>
                <tr style=" color: #282E4D;">
                    <td>{{ $order->platform->name }}</td>
                    <td>{{ $order->qty }}</td>

                    @if ($order->ship_price)
                        <td>{{ $order->ship_price }} {{ $order->currency_sign }}</td>
                    @else
                        <td> - </td>
                    @endif

                    @if ($order->ship_price)
                        <td>{{ $order->total_price+$order->ship_price }} {{ $order->currency_sign }}
                    @else
                        <td>{{ $order->total_price }} {{ $order->currency_sign }}
                    @endif
                    @if ($order->discount)
                        <span style="color: red;">(تخفيض: {{ $order->discount }} {{ $order->currency_sign }})</span>
                    @endif
                    </td>

                </tr>

                @if ($order->items->count() > 0)
                    @foreach ($order->items as $index => $item)
                        <tr>
                            <td style="background-color: #c6c4c4;">{{ $item->name }}</td>
                            <td style="background-color: #c6c4c4;">{{ $item->quantity }}</td>
                            <td colspan="2" style="background-color: #c6c4c4;">{{ $item->price }} {{ $order->currency_sign }}</td>
                        </tr>
                    @endforeach
                @endif

                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td style="color: white; background-color: #282E4D;">القيمة المدفوعة</td>
                    <td style="color: white; background-color: #282E4D;">{{ $order->paid_price }} {{ $order->currency_sign }}</td>
                </tr>

                @if ($order->paids->count() > 0)
                    <tr>
                        <td colspan="2" style="border: none;"></td>
                        <td>تم الدفع في {{ $order->paids->last()->created_at->format('Y-m-d') }}</td>
                        <td>{{ $order->paids->last()->paid_price }} {{ $order->currency_sign }}</td>
                    </tr>
                @endif

                <tr style=" color: #282E4D;">
                    <td colspan="2" style="border: none;"></td>
                    <td style="background-color: lightgrey;">القيمة المستحقة</td>
                    @if ($order->ship_price)
                        <td style="background-color: lightgrey;">{{ ($order->total_price+$order->ship_price-$order->discount) - $order->paid_price }} {{ $order->currency_sign }}</td>
                    @else
                        <td style="background-color: lightgrey;">{{ ($order->total_price-$order->discount) - $order->paid_price }} {{ $order->currency_sign }}</td>
                    @endif
                </tr>

            </table>
        </div>

        <div class="info" style="font-size: 14px; padding-bottom: 10px;">
            <div><span>{{ $invoice->welcome }}</span></div>
        </div>

        <div class="notes" style="font-size: 14px;">
            <div style=" color: #282E4D;">الشروط:</div>
            <ul>
                <li>دفع قيمة الشراء فوراً</li>
                <li>دفع تكلفة الشحن عند وصول الاصناف اعلاه</li>
            </ul>

            <div><span style=" color: #282E4D;">{{ $invoice->note }}</span></div>
            <div style=" padding-top: 10px;">{{ $invoice->note2 }}</div>

            <div style=" color: #282E4D; margin-top: 10px;">نقدم ضمانات الاتية:</div>
            <ul>
                <li>مطابقة للمواصفات و الكمية المتفق عليها مسبقا</li>
                <li>عدم مطالبة بتكلفة اضافية من الزبون بعد الاتفاق و دفع الفاتورة</li>
            </ul>
            <div><span>نحن سعداء لخدمتكم.</span></div>

            <div style="text-align: left; margin-top: -30px; padding-left: 35px;"><span>الـتـوقـيـع : </span></div>
            <div style="text-align: left; padding-left: 20px; margin-top: 15px;"><span>............................</span></div>


        </div>

        <div class="footer" style="font-size: 14px;">
            <p>{{ $invoice->address }}</p>
        </div>
    </div>

    <script>

    </script>
</body>
</html>
