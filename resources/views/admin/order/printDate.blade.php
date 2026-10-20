<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title> طـبـاعـة الـطـلـبـات </title>
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
            min-height: 100vh;
            /* Ensures the body takes full height of the viewport */
        }

        .container {
            width: 210mm;
            margin: 0 auto;
            padding: 5mm 20mm;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            flex: 1;
            /* Allows the container to grow and take up available space */
            display: flex;
            flex-direction: column;
        }

        .header,
        .footer {
            text-align: center;
            margin-bottom: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #545353;
            /* Adding a bottom border */
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

        .details th,
        .details td {
            border: 1px solid #000;
            text-align: center;
        }

        .info span,
        .notes span {
            font-weight: bold;
        }

        .min-height-row {
            min-height: 25px;
            /* Set your desired minimum height */
        }

        .min-height-row td {
            min-height: 25px;
            /* Set your desired minimum height */
            line-height: 25px;
            /* Optional: Ensure the text inside is vertically centered */
            height: 25px;
            /* Set a standard height to enforce the minimum */
        }


        .dates {
            display: flex;
            justify-content: space-between;
        }

        .dates div {
            text-align: right;
        }

        .footer {
            margin-top: auto;
            /* Ensures the footer is pushed to the bottom */
            padding-top: 10px;
            border-top: 2px solid #000;
            /* Adding a top border */
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('assets/images/loggo.png') }}" alt="Shop and Go Logo">
            <h5>SHOP AND GO</h5>
        </div>

        <div class="info">
            <div><span style="font-size: 24px;"></span> <span style="font-size: 24px; color: #282E4D;">الـطـلـبـات</span>

                @if (isset($query['status']) && $query['status'] == 'paid_full')
                    <span style="font-size: 24px; color: #282E4D;"> - مدفوعة بالكامل (مع الشحن)</span>
                @elseif (isset($query['status']) && $query['status'] == 'paid')
                    <span style="font-size: 24px; color: #282E4D;"> - مدفوعة</span>
                @elseif (isset($query['status']) && $query['status'] == 'unpaid')
                    <span style="font-size: 24px; color: #282E4D;"> - غير مدفوعة</span>
                @elseif (isset($query['status']) && $query['status'] == 'canceld')
                    <span style="font-size: 24px; color: #282E4D;"> - ملغية</span>
                @elseif (isset($query['status']) && $query['status'] == 'delivered')
                    <span style="font-size: 24px; color: #282E4D;"> - مستلمة</span>
                @endif
            </div>
        </div>

        <div class="dates" style="font-size: 14px;">
            <div>
                @if (isset($query['startDate']) || isset($query['endDate']))
                    <p>التاريخ : <span style=" color: #282E4D;">
                            @if (isset($query['startDate']))
                                - {{ $query['startDate'] }}
                            @endif
                            @if (isset($query['endDate']))
                                - {{ $query['endDate'] }}
                            @endif
                        </span></p>
                @endif
            </div>

            <div>
                <p>تاريخ الطباعة: <span style=" color: #282E4D;">{{ now()->format('Y-m-d') }}</span></p>
            </div>
        </div>

        <div class="details" style="font-size: 14px;">
            <table>
                <tr style=" background: #282E4D; color: white;" class="min-height-row">
                    <th>طلب الزبون</th>
                    <th>حالة الطلب</th>
                    <th>اجمالي سعر البضاعة</th>
                    <th>القيمة المدفوعة</th>
                    <th>التاريخ</th>
                    <th>ملاحظات</th>
                </tr>
                @php
                    $totalPaid = 0;
                @endphp
                @foreach ($orders as $order)
                    @if ($order->status == 'order')
                        <tr style=" background: rgb(251, 228, 226);" class="min-height-row">
                        @else
                        <tr class="min-height-row">
                    @endif
                    @php
                        $totalPaid += $order->paid_price;
                    @endphp

                    <td class="">{{ $order->customer->name }} - <a style="color: green"
                            href="https://wa.me/{{ $order->customer->phone_1 }}"
                            target="_blank">{{ $order->customer->code }}</a>
                        <br>
                        {{ $order->code }} - {{ $order->platform->name }}

                    </td>

                    @if ($order->status == 'paid_full')
                        <td class="text-info"> مدفوعة
                            @if ($order->delivered)
                                <br><span class="text-success">تم التسليم</span>
                            @elseif($order->arrived)
                                <br><span class="text-primary">وصلت</span> - <span
                                    class="text-primary">{{ $order->arrived_at }}</span>
                            @endif
                        </td>
                    @elseif ($order->status == 'unpaid')
                        <td class="text-warning"> غير مدفوعة
                            @if ($order->delivered)
                                <br><span class="text-success">تم التسليم</span>
                            @elseif($order->arrived)
                                <br><span class="text-primary">وصلت</span> - <span
                                    class="text-primary">{{ $order->arrived_at }}</span>
                            @endif
                        </td>
                    @elseif ($order->status == 'paid')
                        <td class="text-info"> مدفوعة من غير الشحن
                            @if ($order->delivered)
                                <br><span class="text-success">تم التسليم</span>
                            @elseif($order->arrived)
                                <br><span class="text-primary">وصلت</span> - <span
                                    class="text-primary">{{ $order->arrived_at }}</span>
                            @endif
                        </td>
                    @elseif ($order->status == 'canceld')
                        <td class="text-danger"> ملغية
                            @if ($order->delivered)
                                <br><span class="text-success">تم التسليم</span>
                            @elseif($order->arrived)
                                <br><span class="text-primary">وصلت</span> - <span
                                    class="text-primary">{{ $order->arrived_at }}</span>
                            @endif
                        </td>
                    @elseif ($order->status == 'delivered')
                        <td class="text-success"> تم التسليم
                            @if ($order->delivered)
                                <br><span class="text-success">تم التسليم</span>
                            @elseif($order->arrived)
                                <br><span class="text-primary">وصلت</span> - <span
                                    class="text-primary">{{ $order->arrived_at }}</span>
                            @endif
                        </td>
                    @endif


                    <td class="" style="color: green;">
                        {{ $order->total_price + $order->ship_price }} {{ $order->currency_sign }}
                        @if ($order->discount)
                        <span style="color: red;">(تخفيض: {{ $order->discount }} {{ $order->currency_sign }})</span>
                    @endif
                        {{-- <br> سعر التكلفة <span
        style="color: green;">{{ $order->cost_price }}
        {{ $order->currency_sign }}</span> --}}
                    </td>

                    <td class=""><span style="color: green;">
                            {{ $order->paid_price }} {{ $order->currency_sign }} </span></td>

                    <td class="">{{ $order->created_at->format('Y-m-d') }}</td>


                    @if ($order->note)
                        <td class="">{{ $order->note }}</td>
                    @else
                        <td class=""> - </td>
                    @endif

                        </tr>
                @endforeach

                <tr class="min-height-row" style=" background: rgb(226, 226, 226);">
                    <td class="" colspan="3">إجمالي القيمة</td>
                    <td class="" colspan="3">
                        <span style="color: green;">{{ ($orders->sum('total_price')-$orders->sum('discount')) + $orders->sum('ship_price') }}
                            {{ $order->currency_sign }}</span>
                    </td>
                </tr>
                <tr class="min-height-row" style=" background: rgb(226, 226, 226);">
                    <td class="" colspan="3">إجمالي القيمة الغير مدفوعة</td>
                    <td class="" colspan="3">
                        <span
                            style="color: green;">{{ ($orders->sum('total_price')-$orders->sum('discount')) + $orders->sum('ship_price') - $totalPaid }}
                            {{ $order->currency_sign }}</span>
                    </td>
                </tr>

            </table>
        </div>

        <div class="footer" style="font-size: 14px;">
            <p>العنوان / بنغازي - الرحبة شارع قصر عبد إسماعيل 0920703777 - 0920403444</p>
        </div>
    </div>

    <script></script>
</body>

</html>
