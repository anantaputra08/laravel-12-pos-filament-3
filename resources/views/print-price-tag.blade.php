<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Tags with Barcode - 4 in One Row</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 10mm;
            box-sizing: border-box;
        }

        .a4-container {
            width: 297mm;
            /* A4 width in landscape */
            height: 210mm;
            /* A4 height in landscape */
            background-color: white;
            margin: 0 auto;
            padding: 10mm;
            box-sizing: border-box;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            width: 25%;
            padding: 10px;
            box-sizing: border-box;
            border: 2px solid #333;
            border-radius: 8px;
            background-color: white;
            vertical-align: top;
        }

        .header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
            margin: 0;
            padding: 0;
        }

        .category {
            font-size: 14px;
            margin-bottom: 10px;
            color: #7f8c8d;
            font-style: italic;
            margin: 2px 0;
            padding: 0;
        }

        .barcode-container {
            margin: 5px 0;
            text-align: center;
        }

        .barcode-text {
            margin: 0;
            padding: 0;
            font-size: 10px;
            text-align: center;
        }

        .price-list {
            margin: 8px 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .unit-info {
            font-size: 14px;
            color: #6d6d6d;
            text-align: left;
        }

        .price-value {
            font-weight: bold;
            color: #333;
            font-size: 15px;
            text-align: right;
        }

        @media print {
            body {
                background-color: white;
            }

            .a4-container {
                width: 100%;
                height: 100%;
                box-shadow: none;
            }

            td {
                border: 1px solid #333;
            }
        }
    </style>
</head>

<body>
    <div class="a4-container">
        <table>
            <tr>
                @for ($i = 0; $i < 4; $i++)
                    <td>
                        <h4 class="header">{{ $product->name }}</h4>
                        <p class="category">{{ $product->category->name }}</p>
                        <div class="price-list">
                            @foreach ($units as $unit)
                                <div class="price-item">
                                    <span class="unit-info">{{ $unit->name }}/{{ $unit->conversion_rate }}</span>
                                    <span class="price-value">Rp
                                        {{ number_format($unit->selling_price, 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </td>
                @endfor
            </tr>
        </table>
    </div>
</body>

</html>
