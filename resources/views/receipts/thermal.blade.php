<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $invoice->bill_number ?? $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 0;
            size: {{ $widthCss }} auto;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $widthCss === '58mm' ? '11px' : '13px' }};
            color: #000;
            margin: 0;
            padding: 4mm;
            width: {{ $widthCss }};
            background: #fff;
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 4px 0;
        }
        .header-logo {
            max-width: 50px;
            max-height: 50px;
            display: block;
            margin: 0 auto 4px auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            padding: 2px 0;
        }
        .token-badge {
            display: inline-block;
            border: 2px solid #000;
            padding: 2px 8px;
            font-size: 16px;
            font-weight: bold;
            margin: 4px 0;
        }
        .qr-wrap {
            margin: 6px 0;
            text-align: center;
        }
        .qr-wrap img {
            width: 100px;
            height: 100px;
        }
    </style>
</head>
<body>

    @if(($config?->print_show_logo ?? true) && $restaurant?->logo_path)
        <img src="{{ asset('storage/' . $restaurant->logo_path) }}" alt="Logo" class="header-logo">
    @endif

    <div class="text-center bold" style="font-size: 15px;">
        {{ $restaurant?->name }}
    </div>

    @if($restaurant?->address)
        <div class="text-center" style="font-size: 10px;">{{ $restaurant->address }}</div>
    @endif

    @if($restaurant?->phone_no)
        <div class="text-center" style="font-size: 10px;">Tel: {{ $restaurant->phone_no }}</div>
    @endif

    @if($restaurant?->gst_no)
        <div class="text-center bold" style="font-size: 10px;">GSTIN: {{ $restaurant->gst_no }}</div>
    @endif

    @if($config?->fssai_lic_no)
        <div class="text-center" style="font-size: 10px;">FSSAI: {{ $config->fssai_lic_no }}</div>
    @endif

    @if($config?->print_header_text)
        <div class="text-center" style="font-style: italic; font-size: 10px; margin-top: 2px;">
            {{ $config->print_header_text }}
        </div>
    @endif

    @if($order?->token_number)
        <div class="text-center">
            <span class="token-badge">TOKEN #{{ $order->token_number }}</span>
        </div>
    @endif

    <div class="divider"></div>

    <table>
        <tr>
            <td>Bill No: <b>{{ $invoice->bill_number ?? $invoice->invoice_number }}</b></td>
            <td class="text-right">{{ $invoice->invoice_date?->format('d/m/Y') ?? date('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>
                @if($order?->table)
                    Table: <b>{{ $order->table->table_number }}</b>
                @elseif($order?->roomSession)
                    Room: <b>{{ $order->roomSession->room?->room_number }}</b>
                @else
                    Type: <b>{{ ucfirst($order?->service_type ?? 'Takeaway') }}</b>
                @endif
            </td>
            <td class="text-right">{{ date('h:i A') }}</td>
        </tr>
        @if($invoice->customer_name)
            <tr>
                <td colspan="2">Customer: {{ $invoice->customer_name }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <table>
        <thead>
            <tr class="bold" style="border-bottom: 1px dashed #000;">
                <th align="left">Item</th>
                <th align="center">Qty</th>
                <th align="right">Price</th>
                <th align="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $items = $invoice->items_snapshot ?? $order?->orderItems ?? [];
            @endphp
            @foreach($items as $item)
                @php
                    $name = is_array($item) ? ($item['name'] ?? $item['item_name'] ?? 'Item') : ($item->item_name ?? $item->name);
                    $qty = is_array($item) ? ($item['quantity'] ?? 1) : ($item->quantity ?? 1);
                    $price = is_array($item) ? ($item['price'] ?? 0) : ($item->price ?? 0);
                    $total = $qty * $price;
                @endphp
                <tr>
                    <td align="left">{{ $name }}</td>
                    <td align="center">{{ $qty }}</td>
                    <td align="right">{{ number_format($price, 2) }}</td>
                    <td align="right">{{ number_format($total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr>
            <td>Subtotal:</td>
            <td class="text-right">Rs. {{ number_format($invoice->subtotal, 2) }}</td>
        </tr>

        @if(($config?->print_show_tax_breakup ?? true) && !empty($taxBreakup))
            @foreach($taxBreakup as $tax)
                <tr>
                    <td>{{ $tax['name'] }} @if(!empty($tax['rate']))({{ $tax['rate'] }}%)@endif:</td>
                    <td class="text-right">Rs. {{ number_format($tax['amount'], 2) }}</td>
                </tr>
            @endforeach
        @elseif($invoice->tax_amount > 0)
            <tr>
                <td>Total Tax:</td>
                <td class="text-right">Rs. {{ number_format($invoice->tax_amount, 2) }}</td>
            </tr>
        @endif

        @if($invoice->extra_charges > 0)
            <tr>
                <td>Service / Extra Charges:</td>
                <td class="text-right">Rs. {{ number_format($invoice->extra_charges, 2) }}</td>
            </tr>
        @endif

        @if($invoice->discount_amount > 0)
            <tr>
                <td>Discount:</td>
                <td class="text-right">- Rs. {{ number_format($invoice->discount_amount, 2) }}</td>
            </tr>
        @endif

        <tr class="bold" style="font-size: 14px;">
            <td style="padding-top: 4px;">TOTAL PAYABLE:</td>
            <td class="text-right" style="padding-top: 4px;">Rs. {{ number_format($invoice->grand_total, 2) }}</td>
        </tr>
    </table>

    <div class="double-divider"></div>

    @if($upiQrData)
        <div class="qr-wrap">
            <div style="font-size: 10px; margin-bottom: 2px;">Scan & Pay via UPI</div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($upiQrData) }}" alt="UPI QR">
            <div style="font-size: 9px; margin-top: 2px;">{{ $restaurant?->upi_id }}</div>
        </div>
    @endif

    @if($config?->print_footer_text)
        <div class="text-center" style="margin-top: 4px; font-size: 10px;">
            {{ $config->print_footer_text }}
        </div>
    @else
        <div class="text-center" style="margin-top: 4px; font-size: 10px;">
            Thank you! Please visit again.
        </div>
    @endif

    <div class="text-center" style="font-size: 9px; color: #666; margin-top: 4px;">
        Powered by AnnSathi
    </div>

</body>
</html>
