<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الزبائن</title>
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
            margin-bottom: 20px;
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

        .min-height-row {
    min-height: 40px; /* Set your desired minimum height */
}

.min-height-row td {
    min-height: 40px; /* Set your desired minimum height */
    line-height: 40px; /* Optional: Ensure the text inside is vertically centered */
    height: 40px; /* Set a standard height to enforce the minimum */
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

            <div class="info">

            {{-- <div><span style="font-size: 24px;">#الفاتورة :</span> <span style="font-size: 24px; color: #282E4D;">{{ $paid->order->code }}</span></div> --}}
            @if ($page == "all")
            <div><span style="font-size: 24px;"></span> <span style="font-size: 24px; color: #282E4D;">طباعة كل الزبائن</span></div>
            @elseif ($page == "depts")
            <div><span style="font-size: 24px;"></span> <span style="font-size: 24px; color: #282E4D;">طباعة الزبائن بالديون</span></div>
            @elseif ($page == "noDepts")
            <div><span style="font-size: 24px;"></span> <span style="font-size: 24px; color: #282E4D;">طباعة الزبائن من غير ديون</span></div>
            @endif
        </div>

        <div class="dates" style="font-size: 14px;">
            <div>
                <p>تاريخ الطباعة: <span style=" color: #282E4D;">{{ now()->format('Y-m-d') }}</span></p>
            </div>
        </div>

        <div class="details"  style="font-size: 14px;">
            <table>
                <tr style=" background: #282E4D; color: white;" class="min-height-row">
                    <th>اسم الزبون</th>
                    <th>رقم الهاتف</th>
                    <th>العنوان</th>
                    <th>المستحقات</th>
                </tr>

                @foreach ($customers as $customer)
                @if ($customer->unpaidOrders->count() > 0)
                <tr style=" background: rgb(251, 228, 226);" class="min-height-row">
                @else
                <tr class="min-height-row">
                @endif
                    <td class=""> {{ $customer->name }} - <a style="color: green"
                        href="https://wa.me/{{ $customer->phone_1 }}"
                        target="_blank">{{ $customer->code }}</a>

                    </td>

                    <td class="">
                        <a
                            href="tel:{{ $customer->phone_1 }}">{{ $customer->phone_1 }}</a>
                        @if ($customer->phone_2)
                            -
                            <a
                                href="tel:{{ $customer->phone_2 }}">{{ $customer->phone_2 }}</a>
                        @endif
                    </td>

                    @if ($customer->address)
                        <td class="">{{ $customer->address }}</td>
                    @else
                        <td class=""> - </td>
                    @endif
                    <td class="" style="color: green;">
                        {{ ($customer->orders->sum('total_price')-$customer->orders->sum('discount')) + $customer->orders->sum('ship_price') - $customer->orders->sum('paid_price') }}
                        DLY</td>

                </tr>
                @endforeach

            </table>
        </div>

        <div class="footer" style="font-size: 14px;">
            <p>العنوان / بنغازي - الرحبة شارع قصر عبد إسماعيل 0920703777 - 0920403444</p>
        </div>
    </div>

    <script>

    </script>
</body>
</html>
