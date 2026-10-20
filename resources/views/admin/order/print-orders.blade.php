<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طلبات جاهزة للتسليم</title>
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

        .dates {
            display: flex;
            justify-content: space-between;
        }

        .dates div {
            text-align: right;
        }

        .footer {
            margin-top: auto; /* Ensures the footer is pushed to the bottom */
            padding-top: 10px;
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

        <div class="info" style="margin-bottom: 10px;">
            {{-- <div><span style="font-size: 24px;">#الفاتورة :</span> <span style="font-size: 24px; color: #282E4D;">{{ $order->code }}</span></div> --}}
            <div><span style="font-size: 15px; color: #282E4D;">طلبات جاهزة للتسليم -
                @if ($from && $to)
                {{ $from }} الي {{ $to }}
                @elseif($from)
                {{ $from }}
                @else
                {{ now()->format('Y-m-d') }}
                @endif

            </span></div>
        </div>

        <div class="details"  style="font-size: 14px;">
            <table>
                <tr style=" background: rgb(225, 224, 224);">
                    <th>كود الزبون</th>
                    <th>العنوان</th>
                    <th>المتبقي</th>
                    <th>قيمة الشحن</th>
                    <th>عدد الطرود والقطع</th>
                    <th>الرحلة</th>
                </tr>
                @foreach ($orders as $order)
                    <tr style=" color: #282E4D;">

                        <td class="">{{ $order->customer->name }}
                            <br>{{ $order->customer->code }}
                        </td>

                        @if ($order->customer->addess)
                            <td class="">{{ $order->customer->addess }}</td>
                        @else
                            <td class=""> - </td>
                        @endif


                        @if ($order->ship_price)
                            <td class="">
                            <span
                               style="color: green;">{{ ($order->ship_price+$order->total_price) - $order->paid_price }}
                               {{ $order->currency_sign }}</span>
                            </td>
                        @else
                            <td class=""><span
                                style="color: green;">{{ $order->total_price - $order->paid_price }}
                                {{ $order->currency_sign }}</span></td>
                        @endif

                        @if ($order->discount)
                            <td class="">
                                <span style="color: red;">{{ $order->discount }} {{ $order->currency_sign }}</span>
                                <br>
                                <span style="color: blue;">{{ $order->discount_reason }}</span>
                                @if ($order->discount_reason_note)
                                    <br><span style="color: gray;">{{ $order->discount_reason_note }}</span>
                                @endif
                            </td>
                        @else
                            <td class=""> - </td>
                        @endif


                        @if ($order->ship_price)
                            <td class="">
                            <span
                               style="color: green;">{{ $order->ship_price }}
                               {{ $order->currency_sign }}</span>
                            </td>
                        @else
                            <td class=""> - </td>
                        @endif

                        @if ($order->bags_num || $order->bags_qty)
                            <td class="">
                                @if ($order->bags_num)
                                    {{ $order->bags_num }}
                                @endif
                                @if ($order->bags_qty)
                                    <br> الكمية في الطرود <span
                                        style="color: blue;">{{ $order->bags_qty }}</span>
                                @endif
                            </td>
                        @else
                            <td class=""> - </td>
                        @endif

                        @if ($order->trip)
                            <td class="">{{ $order->trip->code }}
                                @if ($order->trip->country)
                                 -
                                {{ $order->trip->country }}
                                @endif
                            </td>
                        @else
                            <td class=""> - </td>
                        @endif

                    </tr>
                @endforeach

            </table>
        </div>

    </div>

    <script>

    </script>
</body>
</html>
