<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $transaction->id }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 10px;
        }
        .receipt {
            width: 80mm;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
        }
        .info {
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 5px;
            text-align: left;
            border-bottom: 1px dotted #ddd;
        }
        .amount {
            text-align: right;
        }
        .total {
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 10px;
        }
        
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                width: 80mm;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="title">SRC Sri Wartini</div>
            <div>Jl. Kahuripan, 03/03, Sanankulon, Kab. Blitar</div>
            <div>+6285707091008</div>
        </div>
        
        <div class="info">
            <div class="info-row">
                <div>No. Order:</div>
                <div>{{ $transaction->order_id }}</div>
            </div>
            <div class="info-row">
                <div>Tanggal:</div>
                <div>{{ $transaction->created_at->format('d/m/Y H:i') }}</div>
            </div>
            <div class="info-row">
                <div>Kasir:</div>
                <div>{{ $transaction->user->name ?? 'Admin' }}</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th >Qty</th>
                    <th class="amount">Harga</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->productUnit->name ?? 'null' }} x {{ $item->qty }}</td>
                    <td class="amount">{{ number_format($item->product_price, 0, ',', '.') }}</td>
                    <td class="amount">{{ number_format($item->total_price, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="info" style="margin-top: 10px;">
            <div class="info-row total">
                <div>Total:</div>
                <div>Rp. {{ number_format($transaction->gross_amount, 0, ',', '.') }}</div>
            </div>
            <div class="info-row">
                <div>Tunai:</div>
                <div>Rp. {{ number_format($transaction->paid_amount, 0, ',', '.') }}</div>
            </div>
            <div class="info-row">
                <div>Kembali:</div>
                <div>Rp. {{ number_format($transaction->change_amount, 0, ',', '.') }}</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Terima kasih telah berbelanja</p>
            <p>Barang yang sudah dibeli tidak dapat dikembalikan</p>
        </div>
    </div>
    
    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>