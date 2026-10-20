<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title> طـبـاعـة مـصـروفـات الـدفـع </title>
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
            <div><span style="font-size: 24px;"></span> <span style="font-size: 24px; color: #282E4D;">مـصـروفـات الـدفـع</span></div>
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

        <div class="details"  style="font-size: 14px;">
            <table>
                <tr style=" background: #282E4D; color: white;" class="min-height-row">
                    <th>من والي الرحلة</th>
                    <th>المنصة</th>
                    <th>قيمة المصروفات</th>
                    <th>حالة المصروفات</th>
                    <th>تـاريـخ مصروفات الدفع</th>
                    <th>ملاحظات</th>

                </tr>
                @foreach ($expenses as $expense)

                @if (!$expense->status)
                <tr style=" background: rgb(251, 228, 226);" class="min-height-row">
                @else
                <tr class="min-height-row">
                @endif
                    <td class="">{{ $expense->from }} - {{ $expense->to }}</td>
                        <td class="">{{ $expense->platform->name }}</td>
                        <td class="">
                            <span style="color: green;">{{ $expense->cost }}
                                DLY</span>
                        </td>
                        @if ($expense->status)
                            <td class="text-primary">مدفوعة</td>
                        @else
                            <td class="text-danger">غير مدفوعة</td>
                        @endif
                        <td class="">{{ $expense->date }}</td>
                        <td class="">{{ $expense->note }}</td>
                </tr>
                @endforeach

                <tr class="min-height-row" style=" background: rgb(226, 226, 226);">
                    <td class="" colspan="3">إجمالي قيمة المصروفات</td>
                    <td class="" colspan="3">
                        <span style="color: green;">{{ $expenses->sum('cost') }}
                            DLY</span>
                    </td>
                </tr>

                <tr class="min-height-row" style=" background: rgb(226, 226, 226);">
                    <td class="" colspan="3">إجمالي قيمة المصروفات الغير مدفوعة</td>
                    <td class="" colspan="3">
                        <span style="color: green;">{{ $expenses->where('status', false)->sum('cost') }}
                            DLY</span>
                    </td>
                </tr>

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
